import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { IonButton, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, forkJoin, of, switchMap } from 'rxjs';

import { AuthService } from '../../../core/services/auth.service';
import { CheckoutSelection, CheckoutService } from '../../services/checkout.service';
import { OrdenCreada, OrdenesService } from '../../services/ordenes.service';

type CheckoutMode = 'choose' | 'guest' | 'authenticated';

@Component({
  selector: 'app-checkout',
  templateUrl: './checkout.page.html',
  styleUrls: ['./checkout.page.scss'],
  imports: [
    DatePipe,
    DecimalPipe,
    FormsModule,
    IonButton,
    IonContent,
    IonSpinner,
    IonText,
  ],
})
export class CheckoutPage implements OnInit {
  private static readonly PHONE_MAX_LENGTH = 10;

  private readonly checkoutService = inject(CheckoutService);
  private readonly ordenesService = inject(OrdenesService);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly loading = signal(true);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly selection = signal<CheckoutSelection | null>(null);
  protected readonly ordenCreada = signal<OrdenCreada | null>(null);
  protected readonly mode = signal<CheckoutMode>('choose');
  protected readonly authenticatedUser = this.authService.usuario;
  protected readonly form = signal({
    nombre_comprador: '',
    correo_comprador: '',
    telefono_comprador: '',
  });
  protected readonly hasSelection = computed(() => (this.selection()?.items.length ?? 0) > 0);
  protected readonly isAuthenticated = computed(() => this.authService.isAuthenticated());
  protected readonly isAuthenticatedMode = computed(() => this.mode() === 'authenticated');
  protected readonly canSubmit = computed(() => {
    const form = this.form();
    const validEmail = this.isAuthenticatedMode()
      ? /\S+@\S+\.\S+/.test(form.correo_comprador.trim())
      : /\S+@\S+\.\S+/.test(form.correo_comprador.trim());

    return this.hasSelection()
      && (this.mode() === 'guest' || this.mode() === 'authenticated')
      && form.nombre_comprador.trim().length >= 2
      && validEmail
      && form.telefono_comprador.trim().length >= 7
      && !this.submitting()
      && !this.ordenCreada();
  });

  ngOnInit(): void {
    const selection = this.checkoutService.getSelection();

    this.selection.set(selection);
    this.loading.set(false);

    if (!selection || selection.items.length === 0) {
      this.errorMessage.set('No hay una seleccion activa para continuar con la compra.');
      return;
    }

    this.checkoutService.saveReturnUrl('/checkout');

    if (this.authService.isAuthenticated()) {
      this.mode.set('authenticated');
      this.precargarUsuarioAutenticado();
      return;
    }

    this.mode.set('choose');
  }

  protected updateField(field: 'nombre_comprador' | 'correo_comprador' | 'telefono_comprador', event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const rawValue = input?.value ?? '';
    const value = field === 'telefono_comprador'
      ? rawValue.replace(/\D/g, '').slice(0, CheckoutPage.PHONE_MAX_LENGTH)
      : rawValue;

    if (input && field === 'telefono_comprador' && input.value !== value) {
      input.value = value;
    }

    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected elegirInvitado(): void {
    this.mode.set('guest');
    this.errorMessage.set(null);
  }

  protected irALogin(): void {
    this.checkoutService.saveReturnUrl('/checkout');
    void this.router.navigate(['/login'], {
      queryParams: {
        returnUrl: '/checkout',
      },
    });
  }

  protected irARegistro(): void {
    this.checkoutService.saveReturnUrl('/checkout');
    void this.router.navigate(['/registro'], {
      queryParams: {
        returnUrl: '/checkout',
      },
    });
  }

  protected cerrarSesionYSeguirComoInvitado(): void {
    this.authService.logout();
    this.mode.set('guest');
    this.form.set({
      nombre_comprador: '',
      correo_comprador: '',
      telefono_comprador: '',
    });
  }

  protected confirmarCompra(): void {
    const selection = this.selection();

    if (!selection || !this.canSubmit()) {
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set(null);

    this.ordenesService.crearOrden({
      nombre_comprador: this.form().nombre_comprador.trim(),
      correo_comprador: this.form().correo_comprador.trim(),
      telefono_comprador: this.form().telefono_comprador.trim(),
      subtotal: selection.subtotal,
      comision: selection.comision,
      total: selection.total,
    }).pipe(
      switchMap((orden) => {
        const requests = selection.items.map((item) =>
          this.ordenesService.agregarDetalle(orden.id, {
            tipo_boleto_id: item.tipo_boleto_id,
            cantidad: item.cantidad,
          })
        );

        if (requests.length === 0) {
          return of({ orden });
        }

        return forkJoin(requests).pipe(
          switchMap(() => of({ orden }))
        );
      }),
      catchError(() => {
        this.errorMessage.set('No fue posible confirmar la compra. Verifica los datos e intenta nuevamente.');
        this.submitting.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.ordenCreada.set(result.orden);
      this.checkoutService.saveOrder(result.orden);
      this.successMessage.set(`Orden creada correctamente con folio ${result.orden.folio}.`);
      this.submitting.set(false);
    });
  }

  protected volverAlEvento(): void {
    const eventoId = this.selection()?.evento.id;

    if (eventoId) {
      void this.router.navigate(['/eventos', eventoId]);
      return;
    }

    void this.router.navigate(['/eventos']);
  }

  protected irAPagoSimulado(): void {
    if (!this.ordenCreada()) {
      return;
    }

    void this.router.navigate(['/checkout/pago']);
  }

  private precargarUsuarioAutenticado(): void {
    const usuario = this.authService.getUsuario();

    if (!usuario) {
      return;
    }

    this.form.set({
      nombre_comprador: `${usuario.nombre ?? ''} ${usuario.apellido ?? ''}`.trim(),
      correo_comprador: usuario.correo ?? '',
      telefono_comprador: usuario.telefono ?? '',
    });
  }
}
