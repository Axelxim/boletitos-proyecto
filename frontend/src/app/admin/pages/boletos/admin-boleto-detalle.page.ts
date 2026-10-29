import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';
import { of, switchMap } from 'rxjs';

import { AdminBoleto, AdminBoletosService } from '../../services/admin-boletos.service';
import { AdminOrden, AdminOrdenesService } from '../../services/admin-ordenes.service';

@Component({
  selector: 'app-admin-boleto-detalle',
  templateUrl: './admin-boleto-detalle.page.html',
  styleUrls: ['./admin-boleto-detalle.page.scss'],
  imports: [DatePipe, RouterLink, IonButton, IonSpinner, IonText],
})
export class AdminBoletoDetallePage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly adminBoletosService = inject(AdminBoletosService);
  private readonly adminOrdenesService = inject(AdminOrdenesService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly boleto = signal<AdminBoleto | null>(null);
  protected readonly orden = signal<AdminOrden | null>(null);
  protected readonly boletoId = computed(() => Number(this.route.snapshot.paramMap.get('id') || 0));
  protected readonly detalleTipoBoleto = computed(() => {
    const boleto = this.boleto();
    const orden = this.orden();

    if (!boleto || !orden) {
      return null;
    }

    return (orden.detalles ?? []).find((detalle) => Number(detalle.tipo_boleto_id) === Number(boleto.tipo_boleto_id)) ?? null;
  });
  protected readonly boletoEnriquecido = computed(() => {
    const boleto = this.boleto();
    const orden = this.orden();

    if (!boleto) {
      return null;
    }

    const detalle = this.detalleTipoBoleto();

    return {
      ...boleto,
      comprador: orden ? {
        nombre: orden.nombre_comprador,
        correo: orden.correo_comprador,
        telefono: orden.telefono_comprador,
      } : null,
      evento_detalle: orden?.evento ? {
        id: orden.evento.id,
        titulo: orden.evento.titulo,
        fecha_inicio: orden.evento.fecha_inicio,
        fecha_fin: orden.evento.fecha_fin,
        lugar: orden.evento.lugar,
        sede: orden.evento.sede,
        imagen: orden.evento.imagen ?? null,
      } : detalle?.evento ? {
        id: detalle.evento.id,
        titulo: detalle.evento.titulo,
        fecha_inicio: detalle.evento.fecha_inicio,
        fecha_fin: detalle.evento.fecha_fin,
        lugar: detalle.evento.lugar,
        sede: detalle.evento.sede,
        imagen: null,
      } : null,
      tipo_boleto_detalle: detalle?.tipo_boleto ? {
        id: detalle.tipo_boleto.id,
        nombre: detalle.tipo_boleto.nombre,
        descripcion: detalle.tipo_boleto.descripcion,
        precio: detalle.tipo_boleto.precio,
        estado: detalle.tipo_boleto.estado,
      } : boleto.tipo_boleto ? {
        id: boleto.tipo_boleto.id,
        nombre: boleto.tipo_boleto.nombre,
        descripcion: undefined,
        precio: boleto.tipo_boleto.precio,
        estado: boleto.tipo_boleto.estado,
      } : null,
      orden_detalle: orden,
    };
  });

  ngOnInit(): void {
    const boletoId = this.boletoId();

    if (boletoId <= 0) {
      this.errorMessage.set('El identificador del boleto es invalido.');
      this.loading.set(false);
      return;
    }

    this.adminBoletosService.obtenerBoletoPorId(boletoId).pipe(
      switchMap((boleto) => {
        this.boleto.set(boleto);

        if (!boleto.orden_id) {
          return of(null);
        }

        return this.adminOrdenesService.obtenerOrdenPorId(boleto.orden_id);
      })
    ).subscribe({
      next: (orden) => {
        if (orden) {
          this.orden.set(orden);
        }
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar el detalle del boleto.');
        this.loading.set(false);
      },
    });
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
}

interface EnrichedBoletoDetalle extends AdminBoleto {
  comprador: {
    nombre: string;
    correo: string;
    telefono: string;
  } | null;
  evento_detalle: {
    id: number;
    titulo: string;
    fecha_inicio?: string;
    fecha_fin?: string;
    lugar?: string;
    sede?: {
      id: number;
      nombre: string;
      direccion?: string;
    } | null;
    imagen?: string | null;
  } | null;
  tipo_boleto_detalle: {
    id: number;
    nombre: string;
    descripcion?: string;
    precio?: number;
    estado?: string;
  } | null;
  orden_detalle: AdminOrden | null;
}
