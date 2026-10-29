import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface DashboardResumen {
  total_eventos: number;
  eventos_publicados: number;
  total_ordenes: number;
  ordenes_pagadas: number;
  total_ventas: number;
  total_boletos_generados: number;
  boletos_vigentes: number;
  boletos_usados: number;
  boletos_cancelados: number;
}

@Injectable({
  providedIn: 'root',
})
export class AdminDashboardService {
  private readonly apiService = inject(ApiService);

  obtenerResumen(): Observable<DashboardResumen> {
    return this.apiService
      .get<ApiResponse<DashboardResumen>>('admin/dashboard/resumen')
      .pipe(map((response) => response.data));
  }
}
