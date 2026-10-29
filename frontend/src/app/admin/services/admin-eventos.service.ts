import { Injectable, inject } from '@angular/core';
import { Observable, catchError, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';
import { EventoListado } from '../../public/services/eventos.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

@Injectable({
  providedIn: 'root',
})
export class AdminEventosService {
  private readonly apiService = inject(ApiService);

  obtenerEventos(): Observable<EventoListado[]> {
    return this.apiService
      .get<ApiResponse<EventoListado[]>>('admin/eventos')
      .pipe(
        map((response) => response.data ?? []),
        catchError(() =>
          this.apiService
            .get<ApiResponse<EventoListado[]>>('eventos')
            .pipe(map((response) => response.data ?? []))
        )
      );
  }

  obtenerEventoPorId(eventoId: number): Observable<EventoListado> {
    return this.apiService
      .get<ApiResponse<EventoListado>>(`eventos/${eventoId}`)
      .pipe(map((response) => response.data));
  }

  crearEvento(payload: Record<string, unknown>): Observable<EventoListado> {
    return this.apiService
      .post<ApiResponse<EventoListado>>('admin/eventos', payload)
      .pipe(map((response) => response.data));
  }

  actualizarEvento(eventoId: number, payload: Record<string, unknown>): Observable<EventoListado> {
    return this.apiService
      .put<ApiResponse<EventoListado>>(`admin/eventos/${eventoId}`, payload)
      .pipe(map((response) => response.data));
  }
}
