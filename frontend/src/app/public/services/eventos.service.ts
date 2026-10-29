import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

export interface EventoImagen {
  id: number;
  evento_id?: number;
  ruta: string;
  url: string;
  tipo: 'portada' | 'banner' | 'galeria' | 'miniatura' | string;
  principal: number;
  orden: number;
  estado: string;
  created_at?: string;
  updated_at?: string;
}

export interface TipoBoletoDisponible {
  id: number;
  evento_id: number;
  nombre: string;
  descripcion?: string;
  precio: number;
  cantidad_total: number;
  cantidad_disponible: number;
  max_por_compra?: number;
  estado: string;
}

export interface EventoListado {
  id: number;
  categoria_id?: number | string;
  sede_id?: number | string;
  titulo: string;
  descripcion: string;
  lugar: string;
  direccion?: string;
  fecha_inicio: string;
  fecha_fin: string;
  fecha_evento?: string;
  hora_evento?: string;
  estado?: string;
  imagen?: string;
  imagenes?: EventoImagen[];
  categoria?: {
    id: number;
    nombre: string;
  } | null;
  sede?: {
    id: number;
    nombre: string;
    direccion?: string;
  } | null;
  metricas?: {
    vistas: number;
    clicks: number;
    busquedas: number;
    ventas: number;
    boletos_vendidos: number;
    score: number;
  };
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

@Injectable({
  providedIn: 'root',
})
export class EventosService {
  private readonly apiService = inject(ApiService);

  obtenerEventosDestacados(): Observable<EventoListado[]> {
    return this.apiService
      .get<ApiResponse<EventoListado[]>>('eventos/destacados')
      .pipe(map((response) => response.data ?? []));
  }

  obtenerEventoPorId(eventoId: number): Observable<EventoListado> {
    return this.apiService
      .get<ApiResponse<EventoListado>>(`eventos/${eventoId}`)
      .pipe(map((response) => response.data));
  }

  obtenerImagenesPorEvento(eventoId: number): Observable<EventoImagen[]> {
    return this.apiService
      .get<ApiResponse<EventoImagen[]>>(`eventos/${eventoId}/imagenes`)
      .pipe(map((response) => response.data ?? []));
  }

  obtenerTiposBoletosPorEvento(eventoId: number): Observable<TipoBoletoDisponible[]> {
    return this.apiService
      .get<ApiResponse<TipoBoletoDisponible[]>>(`eventos/${eventoId}/tipos-boletos`)
      .pipe(map((response) => response.data ?? []));
  }

  obtenerEventos(): Observable<EventoListado[]> {
    return this.apiService
      .get<ApiResponse<EventoListado[]>>('eventos')
      .pipe(map((response) => response.data ?? []));
  }

  registrarInteraccion(eventoId: number, tipo: 'vista' | 'click' | 'busqueda' | 'compra'): Observable<unknown> {
    return this.apiService.post(`eventos/${eventoId}/interaccion`, { tipo });
  }
}
