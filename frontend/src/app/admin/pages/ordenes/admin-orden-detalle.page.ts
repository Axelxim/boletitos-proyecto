import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';

import { AdminOrden, AdminOrdenBoleto, AdminOrdenDetalle, AdminOrdenesService } from '../../services/admin-ordenes.service';

@Component({
  selector: 'app-admin-orden-detalle',
  templateUrl: './admin-orden-detalle.page.html',
  styleUrls: ['./admin-orden-detalle.page.scss'],
  imports: [CurrencyPipe, DatePipe, RouterLink, IonButton, IonSpinner, IonText],
})
export class AdminOrdenDetallePage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly adminOrdenesService = inject(AdminOrdenesService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly orden = signal<AdminOrden | null>(null);
  protected readonly selectedQr = signal<AdminOrdenBoleto | null>(null);
  protected readonly ordenId = computed(() => Number(this.route.snapshot.paramMap.get('id') || 0));

  ngOnInit(): void {
    const ordenId = this.ordenId();

    if (ordenId <= 0) {
      this.errorMessage.set('El identificador de la orden es invalido.');
      this.loading.set(false);
      return;
    }

    this.adminOrdenesService.obtenerOrdenPorId(ordenId).subscribe({
      next: (orden) => {
        this.orden.set(orden);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar el detalle de la orden.');
        this.loading.set(false);
      },
    });
  }

  protected get detalles(): AdminOrdenDetalle[] {
    return this.orden()?.detalles ?? [];
  }

  protected get boletos(): AdminOrdenBoleto[] {
    return this.orden()?.boletos_generados ?? [];
  }

  protected openQr(boleto: AdminOrdenBoleto): void {
    this.selectedQr.set(boleto);
  }

  protected closeQr(): void {
    this.selectedQr.set(null);
  }

  protected estadoClass(estado: string): string {
    switch ((estado || '').toLowerCase()) {
      case 'pagada':
        return 'status-chip status-chip--paid';
      case 'pendiente':
        return 'status-chip status-chip--pending';
      case 'fallida':
        return 'status-chip status-chip--failed';
      case 'cancelada':
        return 'status-chip status-chip--cancelled';
      case 'vigente':
        return 'status-chip status-chip--active';
      case 'usado':
        return 'status-chip status-chip--used';
      default:
        return 'status-chip';
    }
  }
}
