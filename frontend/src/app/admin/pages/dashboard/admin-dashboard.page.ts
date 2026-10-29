import { CurrencyPipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { IonSpinner, IonText } from '@ionic/angular/standalone';

import { AdminDashboardService, DashboardResumen } from '../../services/admin-dashboard.service';

@Component({
  selector: 'app-admin-dashboard',
  templateUrl: './admin-dashboard.page.html',
  styleUrls: ['./admin-dashboard.page.scss'],
  imports: [CurrencyPipe, DecimalPipe, IonSpinner, IonText],
})
export class AdminDashboardPage implements OnInit {
  private readonly dashboardService = inject(AdminDashboardService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly resumen = signal<DashboardResumen | null>(null);
  protected readonly stats = computed(() => {
    const resumen = this.resumen();

    if (!resumen) {
      return [];
    }

    return [
      { label: 'Total eventos', value: resumen.total_eventos },
      { label: 'Eventos publicados', value: resumen.eventos_publicados },
      { label: 'Total órdenes', value: resumen.total_ordenes },
      { label: 'Órdenes pagadas', value: resumen.ordenes_pagadas },
      { label: 'Total ventas', value: resumen.total_ventas, currency: true },
      { label: 'Boletos generados', value: resumen.total_boletos_generados },
      { label: 'Boletos vigentes', value: resumen.boletos_vigentes },
      { label: 'Boletos usados', value: resumen.boletos_usados },
      { label: 'Boletos cancelados', value: resumen.boletos_cancelados },
    ];
  });

  ngOnInit(): void {
    this.dashboardService.obtenerResumen().subscribe({
      next: (resumen) => {
        this.resumen.set(resumen);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar el resumen administrativo.');
        this.loading.set(false);
      },
    });
  }
}
