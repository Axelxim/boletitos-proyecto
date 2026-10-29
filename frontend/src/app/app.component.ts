import { Component, computed, inject } from '@angular/core';
import { NavigationEnd, Router, RouterLink, RouterLinkActive } from '@angular/router';
import { IonApp, IonRouterOutlet } from '@ionic/angular/standalone';
import { toSignal } from '@angular/core/rxjs-interop';
import { filter, map, startWith } from 'rxjs';

import { AuthService } from './core/services/auth.service';

@Component({
  selector: 'app-root',
  templateUrl: 'app.component.html',
  styleUrl: 'app.component.scss',
  imports: [IonApp, IonRouterOutlet, RouterLink, RouterLinkActive],
})
export class AppComponent {
  private readonly router = inject(Router);
  private readonly authService = inject(AuthService);
  private readonly currentUrl = toSignal(
    this.router.events.pipe(
      filter((event): event is NavigationEnd => event instanceof NavigationEnd),
      map((event) => event.urlAfterRedirects),
      startWith(this.router.url)
    ),
    { initialValue: this.router.url }
  );

  protected readonly showPublicHeader = computed(() => !this.currentUrl().startsWith('/admin'));
  protected readonly usuario = this.authService.usuario;
  protected readonly isAuthenticated = this.authService.autenticado;
  protected readonly isAdmin = computed(() => this.authService.isAdmin());
  protected readonly userLabel = computed(() => {
    const usuario = this.usuario();

    if (!usuario) {
      return null;
    }

    return `${usuario.nombre ?? ''} ${usuario.apellido ?? ''}`.trim() || usuario.correo;
  });

  protected cerrarSesion(): void {
    this.authService.logout();
    void this.router.navigate(['/login']);
  }
}
