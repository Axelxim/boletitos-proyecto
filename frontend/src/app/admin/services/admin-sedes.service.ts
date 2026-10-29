import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';
import { SedePublica } from '../../public/services/sedes.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface SedeAdmin extends SedePublica {
  latitud?: number | string;
  longitud?: number | string;
  mapa_url?: string;
  created_at?: string;
  updated_at?: string;
}

@Injectable({
  providedIn: 'root',
})
export class AdminSedesService {
  private readonly apiService = inject(ApiService);

  obtenerSedes(): Observable<SedeAdmin[]> {
    return this.apiService
      .get<ApiResponse<SedeAdmin[]>>('sedes')
      .pipe(map((response) => response.data ?? []));
  }

  crearSede(payload: Record<string, unknown>): Observable<SedeAdmin> {
    return this.apiService
      .post<ApiResponse<SedeAdmin>>('admin/sedes', payload)
      .pipe(map((response) => response.data));
  }

  actualizarSede(sedeId: number, payload: Record<string, unknown>): Observable<SedeAdmin> {
    return this.apiService
      .put<ApiResponse<SedeAdmin>>(`admin/sedes/${sedeId}`, payload)
      .pipe(map((response) => response.data));
  }

  eliminarSede(sedeId: number): Observable<unknown> {
    return this.apiService.delete(`admin/sedes/${sedeId}`);
  }
}
