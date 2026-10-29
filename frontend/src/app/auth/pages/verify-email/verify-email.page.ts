import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonButton, IonContent, IonSpinner } from '@ionic/angular/standalone';
import { catchError, of } from 'rxjs';

import { AuthService } from '../../../core/services/auth.service';

type VerificationState = 'loading' | 'success' | 'expired' | 'invalid';

@Component({
  selector: 'app-verify-email',
  templateUrl: './verify-email.page.html',
  styleUrls: ['./verify-email.page.scss'],
  imports: [RouterLink, IonButton, IonContent, IonSpinner],
})
export class VerifyEmailPage {
  private readonly route = inject(ActivatedRoute);
  private readonly authService = inject(AuthService);

  protected readonly state = signal<VerificationState>('loading');
  protected readonly message = signal('Validando tu enlace de verificacion...');
  protected readonly token = computed(() => this.route.snapshot.queryParamMap.get('token') || '');

  constructor() {
    this.verify();
  }

  protected verify(): void {
    const token = this.token();

    if (!token) {
      this.state.set('invalid');
      this.message.set('El enlace de verificacion no es valido.');
      return;
    }

    this.state.set('loading');
    this.message.set('Validando tu enlace de verificacion...');

    this.authService.verifyEmail(token).pipe(
      catchError((error) => {
        this.resolveError(error);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.state.set('success');
      this.message.set('Tu correo fue verificado correctamente. Ya puedes iniciar sesion.');
    });
  }

  private resolveError(error: unknown): void {
    if (error instanceof HttpErrorResponse) {
      if (error.status === 410) {
        this.state.set('expired');
        this.message.set('El enlace de verificacion ya expiro.');
        return;
      }

      this.state.set('invalid');
      this.message.set(error.error?.message || 'No fue posible validar el enlace de verificacion.');
      return;
    }

    this.state.set('invalid');
    this.message.set('No fue posible validar el enlace de verificacion.');
  }
}
