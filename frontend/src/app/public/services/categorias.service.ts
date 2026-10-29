import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';

export interface CategoriaPublica {
  id: number;
  nombre: string;
  descripcion?: string;
  estado?: string;
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
export class CategoriasService {
  private readonly apiService = inject(ApiService);

  obtenerCategorias(): Observable<CategoriaPublica[]> {
    return this.apiService
      .get<ApiResponse<CategoriaPublica[]>>('categorias')
      .pipe(map((response) => response.data ?? []));
  }
}
