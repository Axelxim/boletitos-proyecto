import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';
import { EventoImagen } from '../../public/services/eventos.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface EventoImagenPayload {
  tipo: 'portada' | 'banner' | 'galeria' | 'miniatura';
  principal?: 0 | 1;
  orden?: number;
  estado?: 'activo' | 'inactivo';
}

@Injectable({
  providedIn: 'root',
})
export class AdminEventoImagenesService {
  private readonly apiService = inject(ApiService);

  obtenerImagenes(eventoId: number): Observable<EventoImagen[]> {
    return this.apiService
      .get<ApiResponse<EventoImagen[]>>(`eventos/${eventoId}/imagenes`)
      .pipe(map((response) => response.data ?? []));
  }

  subirImagen(eventoId: number, file: File, payload: EventoImagenPayload): Observable<EventoImagen> {
    const formData = new FormData();
    formData.append('imagen', file);
    formData.append('tipo', payload.tipo);
    formData.append('principal', String(payload.principal ?? 0));
    formData.append('orden', String(payload.orden ?? 0));

    return this.apiService
      .post<ApiResponse<EventoImagen>>(`admin/eventos/${eventoId}/imagenes`, formData)
      .pipe(map((response) => response.data));
  }

  actualizarImagen(eventoId: number, imagenId: number, payload: EventoImagenPayload): Observable<EventoImagen> {
    return this.apiService
      .put<ApiResponse<EventoImagen>>(`admin/eventos/${eventoId}/imagenes/${imagenId}`, payload)
      .pipe(map((response) => response.data));
  }

  eliminarImagen(eventoId: number, imagenId: number): Observable<unknown> {
    return this.apiService.delete(`admin/eventos/${eventoId}/imagenes/${imagenId}`);
  }
}
