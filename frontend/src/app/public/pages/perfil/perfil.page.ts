import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, of } from 'rxjs';

import { AuthService, AuthUser } from '../../../core/services/auth.service';

@Component({
  selector: 'app-perfil',
  templateUrl: './perfil.page.html',
  styleUrls: ['./perfil.page.scss'],
  imports: [DatePipe, FormsModule, IonButton, IonContent, IonSpinner, IonText],
})
export class PerfilPage {
  private readonly authService = inject(AuthService);

  protected readonly loading = signal(true);
  protected readonly savingProfile = signal(false);
  protected readonly savingPassword = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly passwordMessage = signal<string | null>(null);
  protected readonly profile = signal({
    nombre: '',
    apellido: '',
    correo: '',
    telefono: '',
    estado: '',
    correo_verificado_at: null as string | null,
    created_at: '',
    rol: '',
  });
  protected readonly passwordForm = signal({
    password_actual: '',
    password_nuevo: '',
    password_confirmacion: '',
  });
  protected readonly isVerified = computed(() => !!this.profile().correo_verificado_at);

  constructor() {
    this.loadPerfil();
  }

  protected updateField(field: 'nombre' | 'apellido' | 'telefono', value: string): void {
    this.profile.set({
      ...this.profile(),
      [field]: value,
    });
  }

  protected updatePasswordField(field: 'password_actual' | 'password_nuevo' | 'password_confirmacion', value: string): void {
    this.passwordForm.set({
      ...this.passwordForm(),
      [field]: value,
    });
  }

  protected guardarPerfil(): void {
    this.savingProfile.set(true);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    this.authService.updatePerfil({
      nombre: this.profile().nombre.trim(),
      apellido: this.profile().apellido.trim(),
      telefono: this.profile().telefono.trim(),
    }).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.savingProfile.set(false);
        return of(null);
      })
    ).subscribe((perfil) => {
      if (!perfil) {
        return;
      }

      this.authService.setUser(perfil);
      this.mapPerfil(perfil);
      this.successMessage.set('Perfil actualizado correctamente.');
      this.savingProfile.set(false);
    });
  }

  protected cambiarPassword(): void {
    const form = this.passwordForm();

    if (form.password_nuevo !== form.password_confirmacion) {
      this.passwordMessage.set('La confirmacion de la password no coincide.');
      return;
    }

    this.savingPassword.set(true);
    this.passwordMessage.set(null);

    this.authService.updatePassword(form).pipe(
      catchError((error) => {
        this.passwordMessage.set(this.resolveError(error));
        this.savingPassword.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (result === null) {
        return;
      }

      this.passwordForm.set({
        password_actual: '',
        password_nuevo: '',
        password_confirmacion: '',
      });
      this.passwordMessage.set('Password actualizada correctamente.');
      this.savingPassword.set(false);
    });
  }

  private loadPerfil(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.authService.getPerfil().pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.loading.set(false);
        return of(null);
      })
    ).subscribe((perfil) => {
      if (!perfil) {
        return;
      }

      this.authService.setUser(perfil);
      this.mapPerfil(perfil);
      this.loading.set(false);
    });
  }

  private mapPerfil(perfil: AuthUser): void {
    this.profile.set({
      nombre: perfil.nombre || '',
      apellido: perfil.apellido || '',
      correo: perfil.correo || '',
      telefono: perfil.telefono || '',
      estado: perfil.estado || '',
      correo_verificado_at: perfil.correo_verificado_at || null,
      created_at: perfil.created_at || '',
      rol: perfil.rol?.nombre || '',
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

      return error.error?.message || 'No fue posible procesar la solicitud.';
    }

    return 'No fue posible procesar la solicitud.';
  }
}
