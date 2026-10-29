import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { HttpErrorResponse } from '@angular/common/http';
import { catchError, of } from 'rxjs';

import { AuthService } from '../../../core/services/auth.service';
import { CheckoutService } from '../../../public/services/checkout.service';

@Component({
  selector: 'app-login',
  templateUrl: './login.page.html',
  styleUrls: ['./login.page.scss'],
  imports: [FormsModule, RouterLink, IonButton, IonContent, IonSpinner, IonText],
})
export class LoginPage {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly activatedRoute = inject(ActivatedRoute);
  private readonly checkoutService = inject(CheckoutService);

  protected readonly submitting = signal(false);
  protected readonly showPassword = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly infoMessage = signal<string | null>(null);
  protected readonly canResendVerification = signal(false);
  protected readonly form = signal({
    correo: '',
    password: '',
  });
  protected readonly canSubmit = computed(() => {
    const form = this.form();
    return /\S+@\S+\.\S+/.test(form.correo.trim()) && form.password.trim().length >= 8 && !this.submitting();
  });

  constructor() {
    const redirect = this.activatedRoute.snapshot.queryParamMap.get('redirect');
    const returnUrl = this.activatedRoute.snapshot.queryParamMap.get('returnUrl');
    const registered = this.activatedRoute.snapshot.queryParamMap.get('registered');

    if (returnUrl) {
      this.checkoutService.saveReturnUrl(returnUrl);
    }

    if (registered === '1') {
      this.infoMessage.set('Tu cuenta fue creada correctamente. Ahora puedes iniciar sesion.');
    }

    if (this.authService.isAuthenticated()) {
      void this.redirectByRole(returnUrl || redirect);
    }
  }

  protected updateField(field: 'correo' | 'password', event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const value = input?.value ?? '';

    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected togglePasswordVisibility(): void {
    this.showPassword.update((value) => !value);
  }

  protected iniciarSesion(): void {
    if (!this.canSubmit()) {
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set(null);
    this.infoMessage.set(null);
    this.canResendVerification.set(false);

    this.authService.login({
      correo: this.form().correo.trim(),
      password: this.form().password,
    }).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.canResendVerification.set(this.shouldOfferResend(error));
        this.submitting.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.authService.setToken(result.token.value);
      this.authService.setUser(result.usuario);
      this.submitting.set(false);

      const returnUrl = this.activatedRoute.snapshot.queryParamMap.get('returnUrl');
      const redirect = this.activatedRoute.snapshot.queryParamMap.get('redirect');
      void this.redirectByRole(returnUrl || redirect);
    });
  }

  private async redirectByRole(redirectUrl: string | null): Promise<void> {
    const returnUrl = redirectUrl || this.checkoutService.getReturnUrl();

    if (this.authService.isAdmin()) {
      this.checkoutService.clearReturnUrl();
      await this.router.navigateByUrl(returnUrl || '/admin/dashboard');
      return;
    }

    if (this.authService.isUsuario()) {
      if (returnUrl) {
        this.checkoutService.clearReturnUrl();
        await this.router.navigateByUrl(returnUrl);
        return;
      }

      await this.router.navigateByUrl('/mis-boletos');
      return;
    }

    this.infoMessage.set('Tu sesion fue iniciada, pero tu cuenta no tiene un rol reconocido.');
    await this.router.navigateByUrl('/eventos');
  }

  private resolveError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      return error.error?.message || 'No fue posible iniciar sesion. Verifica tus credenciales.';
    }

    return 'No fue posible iniciar sesion. Verifica tus credenciales.';
  }

  protected reenviarVerificacion(): void {
    const correo = this.form().correo.trim();

    if (!/\S+@\S+\.\S+/.test(correo)) {
      this.errorMessage.set('Captura el correo de tu cuenta para reenviar la verificacion.');
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set(null);
    this.infoMessage.set(null);

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
      this.infoMessage.set('Si el correo puede recibir verificacion, enviaremos un nuevo enlace.');
    });
  }

  private shouldOfferResend(error: unknown): boolean {
    if (!(error instanceof HttpErrorResponse)) {
      return false;
    }

    return String(error.error?.message || '').toLowerCase().includes('confirmar tu correo');
  }
}
