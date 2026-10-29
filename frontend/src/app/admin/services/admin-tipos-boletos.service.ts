import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface TipoBoletoAdmin {
  id: number;
  evento_id: number;
  nombre: string;
  descripcion?: string;
  precio: number | string;
  cantidad_total: number;
  cantidad_disponible: number;
  max_por_compra?: number | null;
  estado: string;
  created_at?: string;
  updated_at?: string;
  evento?: {
    id: number;
    titulo: string;
    estado?: string;
    fecha_inicio?: string;
    fecha_fin?: string;
  } | null;
}

export interface TipoBoletoPayload {
  evento_id: number;
  nombre: string;
  descripcion: string;
  precio: number;
  cantidad_total: number;
  cantidad_disponible: number;
  max_por_compra: number;
  estado: string;
}

@Injectable({
  providedIn: 'root',
})
export class AdminTiposBoletosService {
  private readonly apiService = inject(ApiService);

  obtenerTodos(): Observable<TipoBoletoAdmin[]> {
    return this.apiService
      .get<ApiResponse<TipoBoletoAdmin[]>>('admin/tipos-boletos')
      .pipe(map((response) => response.data ?? []));
  }

  crear(payload: TipoBoletoPayload): Observable<TipoBoletoAdmin> {
    return this.apiService
      .post<ApiResponse<TipoBoletoAdmin>>('admin/tipos-boletos', payload)
      .pipe(map((response) => response.data));
  }

  actualizar(id: number, payload: TipoBoletoPayload): Observable<TipoBoletoAdmin> {
    return this.apiService
      .put<ApiResponse<TipoBoletoAdmin>>(`admin/tipos-boletos/${id}`, payload)
      .pipe(map((response) => response.data));
  }

  eliminar(id: number): Observable<unknown> {
    return this.apiService.delete(`admin/tipos-boletos/${id}`);
  }
}
