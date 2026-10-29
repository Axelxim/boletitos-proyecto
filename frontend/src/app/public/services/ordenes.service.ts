import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface OrdenCreada {
  id: number;
  folio: string;
  estado: string;
  subtotal: number;
  comision: number;
  total: number;
  nombre_comprador: string;
  correo_comprador: string;
  telefono_comprador: string;
}

export interface PaypalOrderResponse {
  paypal_order_id: string;
  estado: string;
  monto: number;
  moneda: string;
}

export interface PaypalCaptureResponse {
  orden: {
    id: number;
    folio: string;
    estado: string;
    total: number;
  };
  pago: {
    paypal_order_id: string;
    referencia_pago: string;
    estado: string;
    moneda: string;
    monto: number;
  };
  boletos: {
    cantidad_generados: number;
  };
  correo: {
    enviado: boolean;
  };
  warning?: string | null;
}

export interface OrdenConsulta {
  id: number;
  folio: string;
  estado: string;
  subtotal: number;
  comision: number;
  total: number;
  nombre_comprador: string;
  correo_comprador: string;
  telefono_comprador: string;
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
  boletos_generados: Array<{
    id: number;
    folio: string;
    codigo_qr: string;
    estado: string;
    fecha_uso?: string | null;
    tipo_boleto?: {
      id: number;
      nombre: string;
    } | null;
    evento?: {
      id: number;
      titulo: string;
    } | null;
  }>;
}

export interface OrdenPayload {
  nombre_comprador: string;
  correo_comprador: string;
  telefono_comprador: string;
  subtotal: number;
  comision: number;
  total: number;
}

@Injectable({
  providedIn: 'root',
})
export class OrdenesService {
  private readonly apiService = inject(ApiService);

  crearOrden(payload: OrdenPayload): Observable<OrdenCreada> {
    return this.apiService
      .post<ApiResponse<OrdenCreada>>('ordenes', payload)
      .pipe(map((response) => response.data));
  }

  agregarDetalle(ordenId: number, payload: { tipo_boleto_id: number; cantidad: number }): Observable<unknown> {
    return this.apiService.post(`ordenes/${ordenId}/detalles`, payload);
  }

  registrarPagoSimulado(ordenId: number, payload: { estado: 'aprobado' | 'rechazado' | 'cancelado'; referencia_pago?: string }): Observable<unknown> {
    return this.apiService.post(`ordenes/${ordenId}/pago-simulado`, payload);
  }

  obtenerPaypalConfig(): Observable<{ client_id: string; mode: string; currency: string; enabled: boolean }> {
    return this.apiService
      .get<ApiResponse<{ client_id: string; mode: string; currency: string; enabled: boolean }>>('paypal/config')
      .pipe(map((response) => response.data));
  }

  crearOrdenPaypal(ordenId: number): Observable<PaypalOrderResponse> {
    return this.apiService
      .post<ApiResponse<PaypalOrderResponse>>(`ordenes/${ordenId}/paypal/crear`, {})
      .pipe(map((response) => response.data));
  }

  capturarOrdenPaypal(ordenId: number, paypalOrderId: string): Observable<PaypalCaptureResponse> {
    return this.apiService
      .post<ApiResponse<PaypalCaptureResponse>>(`ordenes/${ordenId}/paypal/capturar`, {
        paypal_order_id: paypalOrderId,
      })
      .pipe(map((response) => response.data));
  }

  generarBoletos(ordenId: number): Observable<unknown> {
    return this.apiService.post(`ordenes/${ordenId}/generar-boletos`, {});
  }

  consultarMisBoletos(payload: { folio: string; correo_comprador: string }): Observable<OrdenConsulta> {
    return this.apiService
      .post<ApiResponse<OrdenConsulta>>('mis-boletos/consulta', payload)
      .pipe(map((response) => response.data));
  }

  obtenerMisBoletosAutenticado(): Observable<OrdenConsulta[]> {
    return this.apiService
      .get<ApiResponse<OrdenConsulta[]>>('mis-boletos')
      .pipe(map((response) => response.data));
  }
}
