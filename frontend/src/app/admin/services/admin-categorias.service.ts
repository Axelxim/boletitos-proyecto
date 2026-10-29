import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from '../../core/services/api.service';
import { CategoriaPublica } from '../../public/services/categorias.service';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

export interface CategoriaAdmin extends CategoriaPublica {
  created_at?: string;
  updated_at?: string;
}

@Injectable({
  providedIn: 'root',
})
export class AdminCategoriasService {
  private readonly apiService = inject(ApiService);

  obtenerCategorias(): Observable<CategoriaAdmin[]> {
    return this.apiService
      .get<ApiResponse<CategoriaAdmin[]>>('categorias')
      .pipe(map((response) => response.data ?? []));
  }

  crearCategoria(payload: Record<string, unknown>): Observable<CategoriaAdmin> {
    return this.apiService
      .post<ApiResponse<CategoriaAdmin>>('admin/categorias', payload)
      .pipe(map((response) => response.data));
  }

  actualizarCategoria(categoriaId: number, payload: Record<string, unknown>): Observable<CategoriaAdmin> {
    return this.apiService
      .put<ApiResponse<CategoriaAdmin>>(`admin/categorias/${categoriaId}`, payload)
      .pipe(map((response) => response.data));
  }

  eliminarCategoria(categoriaId: number): Observable<unknown> {
    return this.apiService.delete(`admin/categorias/${categoriaId}`);
  }
}
