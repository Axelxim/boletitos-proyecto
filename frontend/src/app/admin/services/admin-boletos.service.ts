import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface AdminBoleto {
  id: number;
  orden_id: number;
  tipo_boleto_id: number;
  folio: string;
  codigo_qr: string;
  estado: string;
  fecha_uso?: string | null;
  created_at?: string;
  updated_at?: string;
  orden?: {
    id: number;
    folio: string;
    estado?: string;
    total?: number;
  } | null;
  tipo_boleto?: {
    id: number;
    nombre: string;
    precio?: number;
    estado?: string;
    evento_id?: number;
  } | null;
  evento?: {
    id: number;
    titulo: string;
    fecha_inicio?: string;
    fecha_fin?: string;
    estado?: string;
  } | null;
}

@Injectable({
  providedIn: 'root',
})
export class AdminBoletosService {
  private readonly apiService = inject(ApiService);

  obtenerBoletos(): Observable<AdminBoleto[]> {
    return this.apiService
      .get<ApiResponse<AdminBoleto[]>>('admin/boletos')
      .pipe(map((response) => response.data ?? []));
  }

  obtenerBoletoPorId(id: number): Observable<AdminBoleto> {
    return this.apiService
      .get<ApiResponse<AdminBoleto>>(`admin/boletos/${id}`)
      .pipe(map((response) => response.data));
  }
}
