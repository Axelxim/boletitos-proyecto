import { Injectable, inject } from '@angular/core';
import { forkJoin, map, Observable } from 'rxjs';

import { ApiService } from '../../core/services/api.service';
import { EventoListado } from '../../public/services/eventos.service';
import { AdminCategoriasService, CategoriaAdmin } from './admin-categorias.service';
import { AdminEventosService } from './admin-eventos.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface ReporteResumenGeneral {
  total_ventas: number;
  ordenes_pagadas: number;
  ordenes_pendientes: number;
  boletos_generados: number;
  boletos_vigentes: number;
  boletos_usados: number;
  boletos_cancelados: number;
  porcentaje_asistencia: number;
}

export interface ReporteVentaPorEvento {
  evento_id: number;
  evento: string;
  ordenes_pagadas: number;
  boletos_vendidos: number;
  ingresos: number;
  boletos_disponibles: number;
  porcentaje_vendido: number;
}

export interface ReporteVentaPorTipoBoleto {
  tipo_boleto_id: number;
  evento_id: number;
  evento: string;
  tipo_boleto: string;
  cantidad_vendida: number;
  precio: number;
  ingresos: number;
}

export interface ReporteAsistencia {
  evento_id: number;
  evento: string;
  boletos_emitidos: number;
  boletos_usados: number;
  boletos_pendientes: number;
  porcentaje_acceso: number;
}

export interface ReportesPayload {
  filtros: {
    fecha_desde: string | null;
    fecha_hasta: string | null;
    evento_id: number | null;
    categoria_id: number | null;
    estado_orden: string | null;
  };
  resumen_general: ReporteResumenGeneral;
  ventas_por_evento: ReporteVentaPorEvento[];
  ventas_por_tipo_boleto: ReporteVentaPorTipoBoleto[];
  asistencia: ReporteAsistencia[];
}

export interface ReportesViewData {
  reportes: ReportesPayload;
  eventos: EventoListado[];
  categorias: CategoriaAdmin[];
}

@Injectable({
  providedIn: 'root',
})
export class AdminReportesService {
  private readonly apiService = inject(ApiService);
  private readonly adminEventosService = inject(AdminEventosService);
  private readonly adminCategoriasService = inject(AdminCategoriasService);

  obtenerDatos(filters?: Record<string, string | number>): Observable<ReportesViewData> {
    return forkJoin({
      reportes: this.apiService
        .get<ApiResponse<ReportesPayload>>('admin/reportes', { params: filters })
        .pipe(map((response) => response.data)),
      eventos: this.adminEventosService.obtenerEventos(),
      categorias: this.adminCategoriasService.obtenerCategorias(),
    });
  }
}
