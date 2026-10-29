import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, of } from 'rxjs';

import { AuthService, RegisterResult } from '../../../core/services/auth.service';
import { CheckoutService } from '../../../public/services/checkout.service';

@Component({
  selector: 'app-register',
  templateUrl: './register.page.html',
  styleUrls: ['./register.page.scss'],
  imports: [FormsModule, RouterLink, IonButton, IonContent, IonSpinner, IonText],
})
export class RegisterPage {
  private static readonly PHONE_MAX_LENGTH = 10;

  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly activatedRoute = inject(ActivatedRoute);
  private readonly checkoutService = inject(CheckoutService);

  protected readonly submitting = signal(false);
  protected readonly showPassword = signal(false);
  protected readonly showPasswordConfirmation = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly registeredResult = signal<RegisterResult | null>(null);
  protected readonly form = signal({
    nombre: '',
    apellido: '',
    correo: '',
    telefono: '',
    password: '',
    confirmacion: '',
  });
  protected readonly canSubmit = computed(() => {
    const form = this.form();
    return !!form.nombre.trim()
      && /\S+@\S+\.\S+/.test(form.correo.trim())
      && form.password.trim().length >= 8
      && form.password === form.confirmacion
      && !this.submitting();
  });

  constructor() {
    const returnUrl = this.activatedRoute.snapshot.queryParamMap.get('returnUrl');

    if (returnUrl) {
      this.checkoutService.saveReturnUrl(returnUrl);
    }

    if (this.authService.isAuthenticated()) {
      void this.router.navigateByUrl(this.authService.isAdmin() ? '/admin/dashboard' : '/mis-boletos');
    }
  }

  protected updateField(
    field: 'nombre' | 'apellido' | 'correo' | 'telefono' | 'password' | 'confirmacion',
    event: Event
  ): void {
    const input = event.target as HTMLInputElement | null;
    const rawValue = input?.value ?? '';
    const value = field === 'telefono'
      ? rawValue.replace(/\D/g, '').slice(0, RegisterPage.PHONE_MAX_LENGTH)
      : rawValue;

    if (input && field === 'telefono' && input.value !== value) {
      input.value = value;
    }

    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected togglePasswordVisibility(field: 'password' | 'confirmacion'): void {
    if (field === 'password') {
      this.showPassword.update((value) => !value);
      return;
    }

    this.showPasswordConfirmation.update((value) => !value);
  }

  protected registrarse(): void {
    if (!this.canSubmit()) {
      this.errorMessage.set('Completa correctamente los campos obligatorios antes de continuar.');
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const form = this.form();

    this.authService.register({
      nombre: form.nombre.trim(),
      apellido: form.apellido.trim(),
      correo: form.correo.trim(),
      telefono: form.telefono.trim(),
      password: form.password,
    }).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.submitting.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.submitting.set(false);
      this.registeredResult.set(result);
      this.successMessage.set(
        result.verification_email_sent
          ? 'Te enviamos un correo para confirmar tu cuenta.'
          : 'Tu cuenta fue creada, pero no pudimos enviar el correo de verificacion en este momento.'
      );
    });
  }

  protected irALogin(): void {
    const returnUrl = this.checkoutService.getReturnUrl();

    void this.router.navigate(['/login'], {
      queryParams: {
        registered: '1',
        ...(returnUrl ? { returnUrl } : {}),
      },
    });
  }

  protected reenviarVerificacion(): void {
    const correo = this.registeredResult()?.usuario?.correo || this.form().correo.trim();

    if (!correo) {
      this.errorMessage.set('Captura un correo valido para reenviar la verificacion.');
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set(null);

    this.authService.resendVerification(correo).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.submitting.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (result === null) {
        return;
      }

      this.submitting.set(false);
      this.successMessage.set('Si el correo puede recibir verificacion, enviaremos un nuevo enlace.');
    });
  }

  private resolveError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      if (error.error?.errors && typeof error.error.errors === 'object') {
        const firstError = Object.values(error.error.errors as Record<string, string>).find(Boolean);

        if (typeof firstError === 'string') {
          return firstError;
        }
      }

      return error.error?.message || 'No fue posible completar tu registro.';
    }

    return 'No fue posible completar tu registro.';
  }
}
