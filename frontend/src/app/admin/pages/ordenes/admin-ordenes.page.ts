import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, of } from 'rxjs';

import { AdminOrden, AdminOrdenesService } from '../../services/admin-ordenes.service';

type EstadoFiltro = 'todos' | 'pendiente' | 'pagada' | 'fallida' | 'cancelada';

@Component({
  selector: 'app-admin-ordenes',
  templateUrl: './admin-ordenes.page.html',
  styleUrls: ['./admin-ordenes.page.scss'],
  imports: [CurrencyPipe, DatePipe, FormsModule, RouterLink, IonSpinner, IonText],
})
export class AdminOrdenesPage implements OnInit {
  private readonly adminOrdenesService = inject(AdminOrdenesService);

  protected readonly loading = signal(true);
  protected readonly cancellingId = signal<number | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly ordenes = signal<AdminOrden[]>([]);
  protected readonly searchTerm = signal('');
  protected readonly estadoFilter = signal<EstadoFiltro>('todos');
  protected readonly fechaDesde = signal('');
  protected readonly fechaHasta = signal('');

  protected readonly filteredOrdenes = computed(() => {
    const term = this.searchTerm().trim().toLowerCase();
    const estado = this.estadoFilter();
    const desde = this.fechaDesde();
    const hasta = this.fechaHasta();

    return this.ordenes().filter((orden) => {
      const matchesTerm = term === ''
        || orden.folio?.toLowerCase().includes(term)
        || orden.nombre_comprador?.toLowerCase().includes(term)
        || orden.correo_comprador?.toLowerCase().includes(term)
        || orden.telefono_comprador?.toLowerCase().includes(term);

      const matchesEstado = estado === 'todos' || (orden.estado ?? '').toLowerCase() === estado;
      const fechaBase = (orden.created_at ?? '').slice(0, 10);
      const matchesDesde = desde === '' || fechaBase >= desde;
      const matchesHasta = hasta === '' || fechaBase <= hasta;

      return matchesTerm && matchesEstado && matchesDesde && matchesHasta;
    });
  });

  ngOnInit(): void {
    this.cargarOrdenes();
  }

  protected updateSearchTerm(value: string): void {
    this.searchTerm.set(value);
  }

  protected updateEstado(value: EstadoFiltro): void {
    this.estadoFilter.set(value);
  }

  protected updateFechaDesde(value: string): void {
    this.fechaDesde.set(value);
  }

  protected updateFechaHasta(value: string): void {
    this.fechaHasta.set(value);
  }

  protected getEventoLabel(orden: AdminOrden): string {
    return orden.evento?.titulo || orden.detalles?.[0]?.evento?.titulo || 'Evento sin referencia';
  }

  protected getCantidadBoletos(orden: AdminOrden): number {
    if (typeof orden.cantidad_boletos === 'number') {
      return orden.cantidad_boletos;
    }

    return (orden.detalles ?? []).reduce((carry, detalle) => carry + Number(detalle.cantidad ?? 0), 0);
  }

  protected estadoClass(estado: string): string {
    switch ((estado || '').toLowerCase()) {
      case 'pagada':
        return 'status-chip status-chip--paid';
      case 'pendiente':
        return 'status-chip status-chip--pending';
      case 'fallida':
        return 'status-chip status-chip--failed';
      case 'cancelada':
        return 'status-chip status-chip--cancelled';
      default:
        return 'status-chip';
    }
  }

  protected cancelarOrden(orden: AdminOrden): void {
    if ((orden.estado ?? '').toLowerCase() !== 'pendiente') {
      this.errorMessage.set('Solo las ordenes pendientes pueden cancelarse desde este modulo.');
      this.successMessage.set(null);
      return;
    }

    this.cancellingId.set(orden.id);
    this.errorMessage.set(null);
    this.successMessage.set(null);

    this.adminOrdenesService.cancelarOrdenPendiente(orden.id).pipe(
      catchError(() => {
        this.errorMessage.set('No fue posible cancelar la orden seleccionada.');
        this.cancellingId.set(null);
        return of(null);
      })
    ).subscribe((result) => {
      if (result === null) {
        return;
      }

      this.successMessage.set(`La orden ${orden.folio} fue cancelada correctamente.`);
      this.cancellingId.set(null);
      this.cargarOrdenes();
    });
  }

  private cargarOrdenes(): void {
    this.adminOrdenesService.obtenerOrdenes().subscribe({
      next: (ordenes) => {
        this.ordenes.set(ordenes);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar las ordenes del panel.');
        this.loading.set(false);
      },
    });
  }
}
