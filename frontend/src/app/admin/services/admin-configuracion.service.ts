import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface ConfiguracionField {
  key: string;
  label: string;
  type: 'text' | 'textarea' | 'email' | 'url' | 'boolean' | 'number' | 'integer' | 'select' | string;
  editable: boolean;
  value: string | number | boolean;
  description?: string;
  options?: string[] | null;
}

export interface ConfiguracionSection {
  title: string;
  fields: ConfiguracionField[];
  info?: Record<string, string | number | boolean | null>;
}

export interface ConfiguracionPayload {
  editable_keys: string[];
  sections: Record<string, ConfiguracionSection>;
}

@Injectable({
  providedIn: 'root',
})
export class AdminConfiguracionService {
  private readonly apiService = inject(ApiService);

  obtenerConfiguracion(): Observable<ConfiguracionPayload> {
    return this.apiService
      .get<ApiResponse<ConfiguracionPayload>>('admin/configuracion')
      .pipe(map((response) => response.data));
  }

  actualizarConfiguracion(values: Record<string, unknown>): Observable<ConfiguracionPayload> {
    return this.apiService
      .put<ApiResponse<ConfiguracionPayload>>('admin/configuracion', { values })
      .pipe(map((response) => response.data));
  }
}
