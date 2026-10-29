import { DecimalPipe } from '@angular/common';
import { AfterViewInit, Component, ElementRef, OnInit, ViewChild, computed, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { Router } from '@angular/router';
import { IonButton, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, firstValueFrom, of } from 'rxjs';

import { environment } from '../../../../environments/environment';
import { CheckoutSelection, CheckoutService } from '../../services/checkout.service';
import { OrdenesService, PaypalCaptureResponse } from '../../services/ordenes.service';
import { PaypalPublicConfig, PaypalService } from '../../services/paypal.service';

@Component({
  selector: 'app-checkout-pago',
  templateUrl: './checkout-pago.page.html',
  styleUrls: ['./checkout-pago.page.scss'],
  imports: [
    DecimalPipe,
    IonButton,
    IonContent,
    IonSpinner,
    IonText,
  ],
})
export class CheckoutPagoPage implements OnInit, AfterViewInit {
  @ViewChild('paypalButtonsContainer') private paypalButtonsContainer?: ElementRef<HTMLDivElement>;

  private readonly checkoutService = inject(CheckoutService);
  private readonly ordenesService = inject(OrdenesService);
  private readonly paypalService = inject(PaypalService);
  private readonly router = inject(Router);

  protected readonly loading = signal(true);
  protected readonly processing = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly infoMessage = signal<string | null>(null);
  protected readonly selection = signal<CheckoutSelection | null>(null);
  protected readonly paypalConfig = signal<PaypalPublicConfig | null>(null);
  protected readonly paymentMethod = signal<'paypal' | 'simulado'>('paypal');
  protected readonly paypalReady = signal(false);
  protected readonly paypalOrderId = signal<string | null>(null);
  protected readonly totalBoletos = computed(() =>
    this.selection()?.items.reduce((acc, item) => acc + item.cantidad, 0) ?? 0
  );
  protected readonly canSimulate = computed(() => !environment.production);
  protected readonly canPay = computed(() => {
    const selection = this.selection();

    if (!selection?.orden || this.processing()) {
      return false;
    }

    if (this.paymentMethod() === 'paypal') {
      return this.paypalReady();
    }

    return this.canSimulate();
  });

  ngOnInit(): void {
    const selection = this.checkoutService.getSelection();
    this.selection.set(selection);

    if (!selection?.orden) {
      this.loading.set(false);
      this.errorMessage.set('No hay una orden lista para procesar el pago.');
      return;
    }

    this.ordenesService.obtenerPaypalConfig().pipe(
      catchError(() => {
        this.errorMessage.set('No fue posible cargar la configuracion publica de PayPal.');
        this.loading.set(false);
        return of(null);
      })
    ).subscribe((config) => {
      if (!config) {
        return;
      }

      this.paypalConfig.set(config);
      this.loading.set(false);

      if (config.enabled) {
        queueMicrotask(() => {
          void this.initializePaypalButtons();
        });
      } else if (this.canSimulate()) {
        this.paymentMethod.set('simulado');
      } else {
        this.errorMessage.set('PayPal Sandbox no esta disponible en este entorno.');
      }
    });
  }

  ngAfterViewInit(): void {
    if (this.paypalConfig()?.enabled && this.paymentMethod() === 'paypal') {
      setTimeout(() => {
        void this.initializePaypalButtons();
      }, 0);
    }
  }

  protected setPaymentMethod(method: 'paypal' | 'simulado'): void {
    this.paymentMethod.set(method);
    this.errorMessage.set(null);
    this.infoMessage.set(null);

    if (method === 'paypal') {
      queueMicrotask(() => {
        void this.initializePaypalButtons();
      });
    }
  }

  protected pagarSimulado(): void {
    const orden = this.selection()?.orden;

    if (!orden || !this.canSimulate() || this.processing()) {
      return;
    }

    this.processing.set(true);
    this.errorMessage.set(null);

    this.ordenesService.registrarPagoSimulado(orden.id, {
      estado: 'aprobado',
      referencia_pago: `SIM-${Date.now()}`,
    }).pipe(
      catchError(() => {
        this.errorMessage.set('No fue posible procesar el pago simulado.');
        this.processing.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      const currentOrder = this.selection()?.orden;

      if (currentOrder) {
        this.checkoutService.saveCompletedPurchase({
          folio: currentOrder.folio,
          estado: 'pagada',
          total: currentOrder.total,
          referenciaPago: `SIM-${Date.now()}`,
          cantidadBoletos: this.totalBoletos(),
          correoEnviado: true,
        });
      }

      this.processing.set(false);
      this.checkoutService.clearSelection();
      this.checkoutService.clearReturnUrl();
      void this.router.navigate(['/compra-exitosa']);
    });
  }

  private async initializePaypalButtons(): Promise<void> {
    const config = this.paypalConfig();
    const orden = this.selection()?.orden;
    const container = this.paypalButtonsContainer?.nativeElement;

    if (!config?.enabled || !orden || !container || this.paymentMethod() !== 'paypal') {
      return;
    }

    try {
      const paypal = await this.paypalService.loadSdk(config);
      container.innerHTML = '';

      paypal.Buttons({
        style: {
          layout: 'vertical',
          color: 'gold',
          shape: 'rect',
          label: 'paypal',
        },
        createOrder: async () => {
          this.processing.set(true);
          this.errorMessage.set(null);
          this.infoMessage.set('Creando orden PayPal...');

          try {
            const response = await firstValueFrom(this.ordenesService.crearOrdenPaypal(orden.id));
            this.paypalOrderId.set(response?.paypal_order_id || null);
            this.processing.set(false);
            this.infoMessage.set(null);

            if (!response?.paypal_order_id) {
              throw new Error('No fue posible crear la orden PayPal.');
            }

            return response.paypal_order_id;
          } catch (error) {
            this.processing.set(false);
            this.infoMessage.set(null);
            this.errorMessage.set(this.extractPaypalErrorMessage(error, 'No fue posible crear la orden PayPal.'));
            throw error;
          }
        },
        onApprove: async (data: { orderID: string }) => {
          this.processing.set(true);
          this.errorMessage.set(null);
          this.infoMessage.set('Confirmando pago con PayPal...');

          try {
            const capture = await firstValueFrom(this.ordenesService.capturarOrdenPaypal(orden.id, data.orderID));

            if (!capture) {
              throw new Error('No fue posible confirmar el pago.');
            }

            this.handleCaptureSuccess(capture);
          } catch (error) {
            this.processing.set(false);
            this.infoMessage.set(null);
            this.errorMessage.set(this.extractPaypalErrorMessage(error, 'No fue posible confirmar el pago con PayPal. Puedes reintentar.'));
          }
        },
        onCancel: () => {
          this.processing.set(false);
          this.infoMessage.set('El pago con PayPal fue cancelado por el comprador.');
        },
        onError: () => {
          this.processing.set(false);
          this.errorMessage.set('PayPal reporto un error al intentar procesar el pago.');
        },
      }).render(container);

      this.paypalReady.set(true);
    } catch {
      this.paypalReady.set(false);
      this.errorMessage.set('No fue posible inicializar el boton de PayPal.');
    }
  }

  private handleCaptureSuccess(capture: PaypalCaptureResponse): void {
    this.processing.set(false);
    this.infoMessage.set(capture.warning || 'Pago confirmado correctamente.');
    this.checkoutService.saveCompletedPurchase({
      folio: capture.orden.folio,
      estado: capture.orden.estado,
      total: capture.orden.total,
      referenciaPago: capture.pago.referencia_pago,
      cantidadBoletos: capture.boletos.cantidad_generados,
      correoEnviado: capture.correo.enviado,
    });
    this.checkoutService.clearSelection();
    this.checkoutService.clearReturnUrl();
    void this.router.navigate(['/compra-exitosa']);
  }

  private extractPaypalErrorMessage(error: unknown, fallback: string): string {
    if (error instanceof HttpErrorResponse) {
      const backendMessage = this.extractBackendErrorMessage(error.error);

      if (backendMessage) {
        return backendMessage;
      }

      if (typeof error.message === 'string' && error.message.trim() !== '') {
        return error.message;
      }
    }

    if (error instanceof Error && error.message.trim() !== '') {
      return error.message;
    }

    return fallback;
  }

  private extractBackendErrorMessage(payload: unknown): string | null {
    if (!payload || typeof payload !== 'object') {
      return null;
    }

    const apiPayload = payload as {
      message?: unknown;
      errors?: Record<string, unknown> | null;
    };

    const errors = apiPayload.errors ?? null;

    if (errors && typeof errors === 'object') {
      const detalle = errors['detalle'];
      const paypal = errors['paypal'];

      if (typeof detalle === 'string' && detalle.trim() !== '') {
        return detalle;
      }

      if (typeof paypal === 'string' && paypal.trim() !== '') {
        return paypal;
      }
    }

    if (typeof apiPayload.message === 'string' && apiPayload.message.trim() !== '') {
      return apiPayload.message;
    }

    return null;
  }
}
