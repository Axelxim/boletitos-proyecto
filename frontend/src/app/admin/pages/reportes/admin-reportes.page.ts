import { CurrencyPipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';

import { EventoListado } from '../../../public/services/eventos.service';
import { CategoriaAdmin } from '../../services/admin-categorias.service';
import {
  AdminReportesService,
  ReporteAsistencia,
  ReporteResumenGeneral,
  ReporteVentaPorEvento,
  ReporteVentaPorTipoBoleto,
} from '../../services/admin-reportes.service';

type EstadoOrdenFiltro = 'todos' | 'pagada' | 'pendiente' | 'fallida' | 'cancelada';

interface ResumenCard {
  label: string;
  value: number;
  currency?: boolean;
  suffix?: string;
}

@Component({
  selector: 'app-admin-reportes',
  templateUrl: './admin-reportes.page.html',
  styleUrls: ['./admin-reportes.page.scss'],
  imports: [CurrencyPipe, DecimalPipe, FormsModule, IonButton, IonSpinner, IonText],
})
export class AdminReportesPage implements OnInit {
  private readonly reportesService = inject(AdminReportesService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly exportMessage = signal<string | null>(null);
  protected readonly resumen = signal<ReporteResumenGeneral | null>(null);
  protected readonly eventos = signal<EventoListado[]>([]);
  protected readonly categorias = signal<CategoriaAdmin[]>([]);
  protected readonly ventasPorEvento = signal<ReporteVentaPorEvento[]>([]);
  protected readonly ventasPorTipo = signal<ReporteVentaPorTipoBoleto[]>([]);
  protected readonly asistenciaRows = signal<ReporteAsistencia[]>([]);

  protected readonly fechaDesde = signal('');
  protected readonly fechaHasta = signal('');
  protected readonly eventoFilter = signal('todos');
  protected readonly categoriaFilter = signal('todos');
  protected readonly estadoOrdenFilter = signal<EstadoOrdenFiltro>('todos');

  protected readonly eventosDisponibles = computed(() =>
    this.eventos()
      .map((evento) => ({ id: Number(evento.id), titulo: evento.titulo }))
      .sort((a, b) => a.titulo.localeCompare(b.titulo))
  );

  protected readonly categoriasDisponibles = computed(() =>
    this.categorias()
      .map((categoria) => ({ id: Number(categoria.id), nombre: categoria.nombre }))
      .sort((a, b) => a.nombre.localeCompare(b.nombre))
  );

  protected readonly resumenCards = computed<ResumenCard[]>(() => {
    const resumen = this.resumen();

    if (!resumen) {
      return [];
    }

    return [
      { label: 'Total de ventas', value: Number(resumen.total_ventas ?? 0), currency: true },
      { label: 'Ordenes pagadas', value: Number(resumen.ordenes_pagadas ?? 0) },
      { label: 'Ordenes pendientes', value: Number(resumen.ordenes_pendientes ?? 0) },
      { label: 'Boletos generados', value: Number(resumen.boletos_generados ?? 0) },
      { label: 'Boletos vigentes', value: Number(resumen.boletos_vigentes ?? 0) },
      { label: 'Boletos usados', value: Number(resumen.boletos_usados ?? 0) },
      { label: 'Boletos cancelados', value: Number(resumen.boletos_cancelados ?? 0) },
      { label: 'Porcentaje de asistencia', value: Number(resumen.porcentaje_asistencia ?? 0), suffix: '%' },
    ];
  });

  ngOnInit(): void {
    this.cargarReportes();
  }

  protected updateFechaDesde(value: string): void {
    this.fechaDesde.set(value);
    this.cargarReportes();
  }

  protected updateFechaHasta(value: string): void {
    this.fechaHasta.set(value);
    this.cargarReportes();
  }

  protected updateEvento(value: string): void {
    this.eventoFilter.set(value);
    this.cargarReportes();
  }

  protected updateCategoria(value: string): void {
    this.categoriaFilter.set(value);
    this.cargarReportes();
  }

  protected updateEstadoOrden(value: EstadoOrdenFiltro): void {
    this.estadoOrdenFilter.set(value);
    this.cargarReportes();
  }

  protected exportarCsv(): void {
    this.exportMessage.set('Exportar CSV queda como placeholder. No existe utilidad de exportacion implementada en el proyecto.');
  }

  protected exportarPdf(): void {
    this.exportMessage.set('Exportar PDF queda como placeholder. No existe plantilla de reporte ni flujo de exportacion administrativa implementado.');
  }

  private cargarReportes(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.reportesService.obtenerDatos(this.buildFilters()).subscribe({
      next: ({ reportes, eventos, categorias }) => {
        this.resumen.set(reportes.resumen_general);
        this.eventos.set(eventos);
        this.categorias.set(categorias);
        this.ventasPorEvento.set(reportes.ventas_por_evento ?? []);
        this.ventasPorTipo.set(reportes.ventas_por_tipo_boleto ?? []);
        this.asistenciaRows.set(reportes.asistencia ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar los reportes administrativos.');
        this.loading.set(false);
      },
    });
  }

  private buildFilters(): Record<string, string | number> {
    const filters: Record<string, string | number> = {};

    if (this.fechaDesde()) {
      filters['fecha_desde'] = this.fechaDesde();
    }

    if (this.fechaHasta()) {
      filters['fecha_hasta'] = this.fechaHasta();
    }

    if (this.eventoFilter() !== 'todos') {
      filters['evento_id'] = this.eventoFilter();
    }

    if (this.categoriaFilter() !== 'todos') {
      filters['categoria_id'] = this.categoriaFilter();
    }

    if (this.estadoOrdenFilter() !== 'todos') {
      filters['estado_orden'] = this.estadoOrdenFilter();
    }

    return filters;
  }
}
