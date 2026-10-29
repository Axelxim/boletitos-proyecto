import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpHeaders, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root',
})
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly apiBaseUrl = environment.apiBaseUrl;

  get<T>(endpoint: string, options?: { params?: HttpParams | Record<string, string | number | boolean>; headers?: HttpHeaders | Record<string, string | string[]> }): Observable<T> {
    return this.http.get<T>(this.buildUrl(endpoint), options);
  }

  post<T>(endpoint: string, body: unknown, options?: { params?: HttpParams | Record<string, string | number | boolean>; headers?: HttpHeaders | Record<string, string | string[]> }): Observable<T> {
    return this.http.post<T>(this.buildUrl(endpoint), body, options);
  }

  put<T>(endpoint: string, body: unknown, options?: { params?: HttpParams | Record<string, string | number | boolean>; headers?: HttpHeaders | Record<string, string | string[]> }): Observable<T> {
    return this.http.put<T>(this.buildUrl(endpoint), body, options);
  }

  patch<T>(endpoint: string, body: unknown, options?: { params?: HttpParams | Record<string, string | number | boolean>; headers?: HttpHeaders | Record<string, string | string[]> }): Observable<T> {
    return this.http.patch<T>(this.buildUrl(endpoint), body, options);
  }

  delete<T>(endpoint: string, options?: { params?: HttpParams | Record<string, string | number | boolean>; headers?: HttpHeaders | Record<string, string | string[]> }): Observable<T> {
    return this.http.delete<T>(this.buildUrl(endpoint), options);
  }

  private buildUrl(endpoint: string): string {
    return `${this.apiBaseUrl}/${endpoint.replace(/^\/+/, '')}`;
  }
}
