import { Injectable, inject } from '@angular/core';

import {
  AuthService,
  LoginPayload,
  LoginResult,
  RegisterPayload,
  AuthUser,
} from '../../core/services/auth.service';

@Injectable({
  providedIn: 'root',
})
export class FrontendAuthService {
  private readonly authService = inject(AuthService);

  login(payload: LoginPayload) {
    return this.authService.login(payload);
  }

  register(payload: RegisterPayload) {
    return this.authService.register(payload);
  }

  logout(): void {
    this.authService.logout();
  }

  getUsuario(): AuthUser | null {
    return this.authService.getUsuario();
  }
}
