import { Component, computed, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { IonButton, IonContent } from '@ionic/angular/standalone';

import { AuthService } from '../../core/services/auth.service';

interface AdminNavItem {
  label: string;
  path?: string;
  disabled?: boolean;
}

@Component({
  selector: 'app-admin-layout',
  templateUrl: './admin-layout.component.html',
  styleUrls: ['./admin-layout.component.scss'],
  imports: [RouterLink, RouterLinkActive, RouterOutlet, IonButton, IonContent],
})
export class AdminLayoutComponent {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly navItems: AdminNavItem[] = [
    { label: 'Dashboard', path: '/admin/dashboard' },
    { label: 'Eventos', path: '/admin/eventos' },
    { label: 'Categorias', path: '/admin/categorias' },
    { label: 'Sedes', path: '/admin/sedes' },
    { label: 'Ordenes', path: '/admin/ordenes' },
    { label: 'Boletos', path: '/admin/boletos' },
    { label: 'Validacion', path: '/admin/validacion' },
    { label: 'Reportes', path: '/admin/reportes' },
    { label: 'Configuracion', path: '/admin/configuracion' },
  ];
  protected readonly hasSession = computed(() => this.authService.hasToken() && this.authService.hasAdminAccess());
  protected readonly currentUserLabel = computed(() => {
    const user = this.authService.getUser();

    if (!user) {
      return 'Sesion administrativa';
    }

    const fullName = `${user.nombre ?? ''} ${user.apellido ?? ''}`.trim();
    return fullName || user.correo || `Usuario #${user.id}`;
  });

  protected salir(): void {
    this.authService.logout();
    void this.router.navigate(['/login']);
  }
}
