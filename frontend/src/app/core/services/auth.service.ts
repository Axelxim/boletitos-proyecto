import { Injectable, computed, inject, signal } from '@angular/core';
import { Observable, map } from 'rxjs';

import { ApiService } from './api.service';

interface TokenPayload {
  user_id?: string | number;
  expires_at?: string;
  type?: string;
  [key: string]: unknown;
}

export interface AuthUserRole {
  id: number | string;
  nombre: string;
  descripcion?: string;
}

export interface AuthUser {
  id: number | string;
  rol_id?: number | string;
  nombre: string;
  apellido?: string;
  correo: string;
  telefono?: string;
  estado?: string;
  correo_verificado_at?: string | null;
  created_at?: string;
  rol?: AuthUserRole | null;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
  errors: unknown;
}

interface AuthToken {
  type: string;
  value: string;
  expires_at: string;
  note?: string;
  user_id: number | string;
}

export interface LoginPayload {
  correo: string;
  password: string;
}

export interface LoginResult {
  usuario: AuthUser;
  token: AuthToken;
}

export interface RegisterPayload {
  nombre: string;
  apellido?: string;
  correo: string;
  telefono?: string;
  password: string;
}

export interface RegisterResult {
  usuario: AuthUser;
  verification_email_sent: boolean;
}

export interface PerfilPayload {
  nombre: string;
  apellido?: string;
  telefono?: string;
}

export interface PasswordPayload {
  password_actual: string;
  password_nuevo: string;
  password_confirmacion: string;
}

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private readonly apiService = inject(ApiService);
  private readonly tokenStorageKey = 'boletitos_token';
  private readonly userStorageKey = 'boletitos_user';
  private readonly tokenSignal = signal<string | null>(localStorage.getItem(this.tokenStorageKey));
  private readonly userSignal = signal<AuthUser | null>(this.readStoredUser());

  readonly usuario = computed(() => this.userSignal());
  readonly autenticado = computed(() => !!this.tokenSignal());

  getToken(): string | null {
    return this.tokenSignal();
  }

  setToken(token: string): void {
    localStorage.setItem(this.tokenStorageKey, token);
    this.tokenSignal.set(token);
  }

  clearToken(): void {
    localStorage.removeItem(this.tokenStorageKey);
    this.tokenSignal.set(null);
  }

  hasToken(): boolean {
    return this.isAuthenticated();
  }

  getUser(): AuthUser | null {
    return this.userSignal();
  }

  setUser(user: AuthUser): void {
    localStorage.setItem(this.userStorageKey, JSON.stringify(user));
    this.userSignal.set(user);
  }

  clearUser(): void {
    localStorage.removeItem(this.userStorageKey);
    this.userSignal.set(null);
  }

  clearSession(): void {
    this.clearToken();
    this.clearUser();
  }

  login(payload: LoginPayload): Observable<LoginResult> {
    return this.apiService
      .post<ApiResponse<LoginResult>>('auth/login', payload)
      .pipe(map((response) => response.data));
  }

  register(payload: RegisterPayload): Observable<RegisterResult> {
    return this.apiService
      .post<ApiResponse<RegisterResult>>('auth/register', payload)
      .pipe(map((response) => response.data));
  }

  verifyEmail(token: string): Observable<AuthUser> {
    return this.apiService
      .get<ApiResponse<AuthUser>>(`auth/verificar-correo?token=${encodeURIComponent(token)}`)
      .pipe(map((response) => response.data));
  }

  resendVerification(correo: string): Observable<void> {
    return this.apiService
      .post<ApiResponse<unknown>>('auth/reenviar-verificacion', { correo })
      .pipe(map(() => void 0));
  }

  getPerfil(): Observable<AuthUser> {
    return this.apiService
      .get<ApiResponse<AuthUser>>('perfil')
      .pipe(map((response) => response.data));
  }

  updatePerfil(payload: PerfilPayload): Observable<AuthUser> {
    return this.apiService
      .put<ApiResponse<AuthUser>>('perfil', payload)
      .pipe(map((response) => response.data));
  }

  updatePassword(payload: PasswordPayload): Observable<void> {
    return this.apiService
      .put<ApiResponse<unknown>>('perfil/password', payload)
      .pipe(map(() => void 0));
  }

  logout(): void {
    this.clearSession();
  }

  getUsuario(): AuthUser | null {
    return this.getUser();
  }

  isAuthenticated(): boolean {
    return this.getToken() !== null;
  }

  isAdmin(): boolean {
    const roleName = this.getUser()?.rol?.nombre?.toLowerCase?.() ?? '';
    return roleName === 'admin';
  }

  isUsuario(): boolean {
    const roleName = this.getUser()?.rol?.nombre?.toLowerCase?.() ?? '';
    return roleName === 'usuario';
  }

  hasAdminAccess(): boolean {
    return this.isAdmin();
  }

  getTokenPayload(): TokenPayload | null {
    const token = this.getToken();

    if (!token) {
      return null;
    }

    const [encodedPayload] = token.split('.');

    if (!encodedPayload) {
      return null;
    }

    try {
      const normalized = encodedPayload.replace(/-/g, '+').replace(/_/g, '/');
      const padded = normalized + '='.repeat((4 - (normalized.length % 4)) % 4);

      return JSON.parse(atob(padded)) as TokenPayload;
    } catch {
      return null;
    }
  }

  private readStoredUser(): AuthUser | null {
    const raw = localStorage.getItem(this.userStorageKey);

    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw) as AuthUser;
    } catch {
      return null;
    }
  }
}
