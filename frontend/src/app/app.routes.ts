import { Routes } from '@angular/router';

import { adminChildGuard, adminGuard } from './core/guards/admin.guard';
import { authGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: 'home',
    loadComponent: () => import('./home/home.page').then((m) => m.HomePage),
  },
  {
    path: 'login',
    loadComponent: () => import('./auth/pages/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'registro',
    loadComponent: () => import('./auth/pages/register/register.page').then((m) => m.RegisterPage),
  },
  {
    path: 'verificar-correo',
    loadComponent: () => import('./auth/pages/verify-email/verify-email.page').then((m) => m.VerifyEmailPage),
  },
  {
    path: 'verify-email',
    redirectTo: 'verificar-correo',
    pathMatch: 'full',
  },
  {
    path: 'admin',
    canActivate: [adminGuard],
    canActivateChild: [adminChildGuard],
    loadComponent: () => import('./admin/layout/admin-layout.component').then((m) => m.AdminLayoutComponent),
    children: [
      {
        path: '',
        redirectTo: 'dashboard',
        pathMatch: 'full',
      },
      {
        path: 'dashboard',
        loadComponent: () => import('./admin/pages/dashboard/admin-dashboard.page').then((m) => m.AdminDashboardPage),
      },
      {
        path: 'ordenes',
        loadComponent: () => import('./admin/pages/ordenes/admin-ordenes.page').then((m) => m.AdminOrdenesPage),
      },
      {
        path: 'ordenes/:id',
        loadComponent: () => import('./admin/pages/ordenes/admin-orden-detalle.page').then((m) => m.AdminOrdenDetallePage),
      },
      {
        path: 'boletos',
        loadComponent: () => import('./admin/pages/boletos/admin-boletos.page').then((m) => m.AdminBoletosPage),
      },
      {
        path: 'boletos/:id',
        loadComponent: () => import('./admin/pages/boletos/admin-boleto-detalle.page').then((m) => m.AdminBoletoDetallePage),
      },
      {
        path: 'reportes',
        loadComponent: () => import('./admin/pages/reportes/admin-reportes.page').then((m) => m.AdminReportesPage),
      },
      {
        path: 'configuracion',
        loadComponent: () => import('./admin/pages/configuracion/admin-configuracion.page').then((m) => m.AdminConfiguracionPage),
      },
      {
        path: 'validacion',
        loadComponent: () => import('./admin/pages/validacion/admin-validacion.page').then((m) => m.AdminValidacionPage),
      },
      {
        path: 'eventos',
        loadComponent: () => import('./admin/pages/eventos/admin-eventos.page').then((m) => m.AdminEventosPage),
      },
      {
        path: 'eventos/nuevo',
        loadComponent: () => import('./admin/pages/eventos/admin-evento-form.page').then((m) => m.AdminEventoFormPage),
      },
      {
        path: 'eventos/:id',
        loadComponent: () => import('./admin/pages/eventos/admin-evento-accion.page').then((m) => m.AdminEventoAccionPage),
        data: {
          pageTitle: 'Accion administrativa del evento',
          actionLabel: 'Eliminar o revisar evento',
        },
      },
      {
        path: 'eventos/:id/editar',
        loadComponent: () => import('./admin/pages/eventos/admin-evento-form.page').then((m) => m.AdminEventoFormPage),
      },
      {
        path: 'eventos/:id/imagenes',
        loadComponent: () => import('./admin/pages/eventos/admin-evento-imagenes.page').then((m) => m.AdminEventoImagenesPage),
      },
      {
        path: 'eventos/:id/boletos',
        loadComponent: () => import('./admin/pages/eventos/admin-evento-boletos.page').then((m) => m.AdminEventoBoletosPage),
      },
      {
        path: 'categorias',
        loadComponent: () => import('./admin/pages/categorias/admin-categorias.page').then((m) => m.AdminCategoriasPage),
      },
      {
        path: 'sedes',
        loadComponent: () => import('./admin/pages/sedes/admin-sedes.page').then((m) => m.AdminSedesPage),
      },
    ],
  },
  {
    path: 'eventos',
    loadComponent: () => import('./public/pages/eventos/eventos.page').then((m) => m.EventosPage),
  },
  {
    path: 'eventos/:id',
    loadComponent: () => import('./public/pages/evento-detalle/evento-detalle.page').then((m) => m.EventoDetallePage),
  },
  {
    path: 'checkout',
    loadComponent: () => import('./public/pages/checkout/checkout.page').then((m) => m.CheckoutPage),
  },
  {
    path: 'checkout/pago',
    loadComponent: () => import('./public/pages/checkout-pago/checkout-pago.page').then((m) => m.CheckoutPagoPage),
  },
  {
    path: 'mis-boletos',
    loadComponent: () => import('./public/pages/mis-boletos/mis-boletos.page').then((m) => m.MisBoletosPage),
  },
  {
    path: 'perfil',
    canActivate: [authGuard],
    loadComponent: () => import('./public/pages/perfil/perfil.page').then((m) => m.PerfilPage),
  },
  {
    path: 'compra-exitosa',
    loadComponent: () => import('./public/pages/compra-exitosa/compra-exitosa.page').then((m) => m.CompraExitosaPage),
  },
  {
    path: '',
    redirectTo: 'home',
    pathMatch: 'full',
  },
];
