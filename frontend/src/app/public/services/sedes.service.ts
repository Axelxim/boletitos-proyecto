import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

export interface SedePublica {
  id: number;
  nombre: string;
  direccion: string;
  ciudad?: string;
  estado?: string;
  pais?: string;
  capacidad?: number | string;
  estado_sede?: string;
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
export class SedesService {
  private readonly apiService = inject(ApiService);

  obtenerSedes(): Observable<SedePublica[]> {
    return this.apiService
      .get<ApiResponse<SedePublica[]>>('sedes')
      .pipe(map((response) => response.data ?? []));
  }
}
