import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { IonSpinner, IonText } from '@ionic/angular/standalone';
import { forkJoin } from 'rxjs';

import { AdminBoleto, AdminBoletosService } from '../../services/admin-boletos.service';
import { AdminOrden, AdminOrdenesService } from '../../services/admin-ordenes.service';

type EstadoBoletoFiltro = 'todos' | 'vigente' | 'usado' | 'cancelado';

@Component({
  selector: 'app-admin-boletos',
  templateUrl: './admin-boletos.page.html',
  styleUrls: ['./admin-boletos.page.scss'],
  imports: [DatePipe, FormsModule, RouterLink, IonSpinner, IonText],
})
export class AdminBoletosPage implements OnInit {
  private readonly adminBoletosService = inject(AdminBoletosService);
  private readonly adminOrdenesService = inject(AdminOrdenesService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly boletos = signal<AdminBoleto[]>([]);
  protected readonly ordenes = signal<AdminOrden[]>([]);
  protected readonly boletoFolioFilter = signal('');
  protected readonly ordenFolioFilter = signal('');
  protected readonly compradorFilter = signal('');
  protected readonly eventoFilter = signal('todos');
  protected readonly estadoFilter = signal<EstadoBoletoFiltro>('todos');
  protected readonly fechaDesde = signal('');
  protected readonly fechaHasta = signal('');

  private readonly ordenesMap = computed(() => {
    const map = new Map<number, AdminOrden>();

    for (const orden of this.ordenes()) {
      map.set(orden.id, orden);
    }

    return map;
  });

  protected readonly eventosDisponibles = computed(() => {
    const eventos = new Map<number, string>();

    for (const boleto of this.enrichedBoletos()) {
      const eventoId = Number(boleto.evento?.id ?? 0);
      const eventoTitulo = boleto.evento?.titulo?.trim();

      if (eventoId > 0 && eventoTitulo) {
        eventos.set(eventoId, eventoTitulo);
      }
    }

    return Array.from(eventos.entries())
      .map(([id, titulo]) => ({ id, titulo }))
      .sort((a, b) => a.titulo.localeCompare(b.titulo));
  });

  protected readonly enrichedBoletos = computed(() => this.boletos().map((boleto) => this.enrichBoleto(boleto)));

  protected readonly filteredBoletos = computed(() => {
    const boletoFolio = this.boletoFolioFilter().trim().toLowerCase();
    const ordenFolio = this.ordenFolioFilter().trim().toLowerCase();
    const comprador = this.compradorFilter().trim().toLowerCase();
    const evento = this.eventoFilter();
    const estado = this.estadoFilter();
    const desde = this.fechaDesde();
    const hasta = this.fechaHasta();

    return this.enrichedBoletos().filter((boleto) => {
      const matchesBoletoFolio = boletoFolio === ''
        || boleto.folio?.toLowerCase().includes(boletoFolio);

      const matchesOrdenFolio = ordenFolio === ''
        || boleto.orden?.folio?.toLowerCase().includes(ordenFolio);

      const matchesComprador = comprador === ''
        || boleto.comprador?.nombre?.toLowerCase().includes(comprador)
        || boleto.comprador?.correo?.toLowerCase().includes(comprador);

      const matchesEvento = evento === 'todos' || String(boleto.evento?.id ?? '') === evento;
      const matchesEstado = estado === 'todos' || (boleto.estado ?? '').toLowerCase() === estado;
      const fechaBase = (boleto.created_at ?? '').slice(0, 10);
      const matchesDesde = desde === '' || fechaBase >= desde;
      const matchesHasta = hasta === '' || fechaBase <= hasta;

      return matchesBoletoFolio
        && matchesOrdenFolio
        && matchesComprador
        && matchesEvento
        && matchesEstado
        && matchesDesde
        && matchesHasta;
    });
  });

  ngOnInit(): void {
    this.cargarBoletos();
  }

  protected updateBoletoFolio(value: string): void {
    this.boletoFolioFilter.set(value);
  }

  protected updateOrdenFolio(value: string): void {
    this.ordenFolioFilter.set(value);
  }

  protected updateEvento(value: string): void {
    this.eventoFilter.set(value);
  }

  protected updateEstado(value: EstadoBoletoFiltro): void {
    this.estadoFilter.set(value);
  }

  protected updateComprador(value: string): void {
    this.compradorFilter.set(value);
  }

  protected updateFechaDesde(value: string): void {
    this.fechaDesde.set(value);
  }

  protected updateFechaHasta(value: string): void {
    this.fechaHasta.set(value);
  }

  protected estadoClass(estado: string): string {
    switch ((estado || '').toLowerCase()) {
      case 'vigente':
        return 'status-chip status-chip--active';
      case 'usado':
        return 'status-chip status-chip--used';
      case 'cancelado':
        return 'status-chip status-chip--cancelled';
      default:
        return 'status-chip';
    }
  }

  protected canCancel(boleto: EnrichedAdminBoleto): boolean {
    return false;
  }

  private cargarBoletos(): void {
    forkJoin({
      boletos: this.adminBoletosService.obtenerBoletos(),
      ordenes: this.adminOrdenesService.obtenerOrdenes(),
    }).subscribe({
      next: ({ boletos, ordenes }) => {
        this.boletos.set(boletos);
        this.ordenes.set(ordenes);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar los boletos del panel.');
        this.loading.set(false);
      },
    });
  }

  private enrichBoleto(boleto: AdminBoleto): EnrichedAdminBoleto {
    const orden = boleto.orden_id ? this.ordenesMap().get(boleto.orden_id) ?? null : null;
    const eventoId = orden?.evento?.id ?? boleto.evento?.id;
    const eventoTitulo = orden?.evento?.titulo || boleto.evento?.titulo || 'Evento sin referencia';

    return {
      ...boleto,
      comprador: orden ? {
        nombre: orden.nombre_comprador,
        correo: orden.correo_comprador,
        telefono: orden.telefono_comprador,
      } : null,
      evento: eventoId ? {
        ...boleto.evento,
        id: eventoId,
        titulo: eventoTitulo,
      } : boleto.evento ?? null,
      orden: {
        ...boleto.orden,
        id: orden?.id ?? boleto.orden?.id ?? boleto.orden_id,
        folio: orden?.folio || boleto.orden?.folio || 'Sin orden',
        estado: orden?.estado || boleto.orden?.estado,
        total: orden?.total ?? boleto.orden?.total,
        subtotal: orden?.subtotal,
        comision: orden?.comision,
      },
    };
  }
}

interface EnrichedAdminBoleto extends AdminBoleto {
  comprador: {
    nombre: string;
    correo: string;
    telefono: string;
  } | null;
  orden?: (NonNullable<AdminBoleto['orden']> & {
    subtotal?: number;
    comision?: number;
  }) | null;
}
