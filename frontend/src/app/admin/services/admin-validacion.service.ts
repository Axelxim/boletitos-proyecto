import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface ValidacionBoletoResponse {
  resultado: 'valido' | 'usado' | 'cancelado' | 'invalido';
  boleto?: {
    id: number;
    folio: string;
    estado: string;
    fecha_uso?: string | null;
    orden?: {
      id: number;
      folio: string;
      estado: string;
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
  } | null;
  validacion?: {
    boleto_id?: number | null;
    usuario_validador_id?: number | null;
    resultado?: string;
    observacion?: string;
    fecha_validacion?: string;
  } | null;
}

@Injectable({
  providedIn: 'root',
})
export class AdminValidacionService {
  private readonly apiService = inject(ApiService);

  validarCodigoQr(codigoQr: string): Observable<{ success: boolean; message: string; data: ValidacionBoletoResponse }> {
    return this.apiService
      .post<ApiResponse<ValidacionBoletoResponse>>('admin/boletos/validar', { codigo_qr: codigoQr })
      .pipe(
        map((response) => ({
          success: response.success,
          message: response.message,
          data: response.data,
        }))
      );
  }
}
