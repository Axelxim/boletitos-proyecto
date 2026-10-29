import { inject } from '@angular/core';
import { CanActivateChildFn, CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../services/auth.service';

function buildAdminRedirectUrlTree(currentUrl: string) {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isAuthenticated()) {
    return router.createUrlTree(['/login'], {
      queryParams: {
        redirect: currentUrl,
      },
    });
  }

  if (authService.isAdmin()) {
    return true;
  }

  return router.createUrlTree(['/eventos']);
}

export const adminGuard: CanActivateFn = (_route, state) => buildAdminRedirectUrlTree(state.url);

export const adminChildGuard: CanActivateChildFn = (_route, state) => buildAdminRedirectUrlTree(state.url);
