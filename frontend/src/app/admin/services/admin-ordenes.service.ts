import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface AdminOrdenDetalle {
  id: number;
  orden_id: number;
  tipo_boleto_id: number;
  cantidad: number;
  precio_unitario: number;
  subtotal: number;
  tipo_boleto?: {
    id: number;
    nombre: string;
    descripcion?: string;
    precio?: number;
    estado?: string;
    evento_id?: number;
  } | null;
  evento?: {
    id: number;
    titulo: string;
    fecha_inicio?: string;
    fecha_fin?: string;
    lugar?: string;
    sede?: {
      id: number;
      nombre: string;
      direccion?: string;
    } | null;
  } | null;
}

export interface AdminOrdenBoleto {
  id: number;
  orden_id: number;
  tipo_boleto_id: number;
  folio: string;
  codigo_qr: string;
  estado: string;
  fecha_uso?: string | null;
  created_at?: string;
  updated_at?: string;
  tipo_boleto?: {
    id: number;
    nombre: string;
  } | null;
  evento?: {
    id: number;
    titulo: string;
  } | null;
}

export interface AdminOrden {
  id: number;
  usuario_id?: number | null;
  folio: string;
  nombre_comprador: string;
  correo_comprador: string;
  telefono_comprador: string;
  subtotal: number;
  comision: number;
  total: number;
  estado: string;
  created_at: string;
  updated_at?: string;
  cantidad_boletos?: number;
  detalles?: AdminOrdenDetalle[];
  boletos_generados?: AdminOrdenBoleto[];
  evento?: {
    id: number;
    titulo: string;
    fecha_inicio?: string;
    fecha_fin?: string;
    lugar?: string;
    sede?: {
      id: number;
      nombre: string;
      direccion?: string;
    } | null;
    imagen?: string | null;
  } | null;
}

@Injectable({
  providedIn: 'root',
})
export class AdminOrdenesService {
  private readonly apiService = inject(ApiService);

  obtenerOrdenes(): Observable<AdminOrden[]> {
    return this.apiService
      .get<ApiResponse<AdminOrden[]>>('admin/ordenes')
      .pipe(map((response) => response.data ?? []));
  }

  obtenerOrdenPorId(id: number): Observable<AdminOrden> {
    return this.apiService
      .get<ApiResponse<AdminOrden>>(`admin/ordenes/${id}`)
      .pipe(map((response) => response.data));
  }

  cancelarOrdenPendiente(id: number): Observable<unknown> {
    return this.apiService.post(`ordenes/${id}/pago-simulado`, {
      estado: 'cancelado',
      referencia_pago: 'admin_cancelacion',
    });
  }
}
