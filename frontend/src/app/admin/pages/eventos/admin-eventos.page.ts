import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';

import { AdminEventosService } from '../../services/admin-eventos.service';
import { EventoImagen, EventoListado } from '../../../public/services/eventos.service';

@Component({
  selector: 'app-admin-eventos',
  templateUrl: './admin-eventos.page.html',
  styleUrls: ['./admin-eventos.page.scss'],
  imports: [DatePipe, RouterLink, IonButton, IonSpinner, IonText],
})
export class AdminEventosPage implements OnInit {
  private readonly adminEventosService = inject(AdminEventosService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly eventos = signal<EventoListado[]>([]);
  protected readonly summary = computed(() => ({
    total: this.eventos().length,
    publicados: this.eventos().filter((evento) => evento.estado?.toLowerCase?.() === 'publicado').length,
    otros: this.eventos().filter((evento) => evento.estado?.toLowerCase?.() !== 'publicado').length,
  }));

  ngOnInit(): void {
    this.adminEventosService.obtenerEventos().subscribe({
      next: (eventos) => {
        this.eventos.set(eventos);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar los eventos del panel.');
        this.loading.set(false);
      },
    });
  }

  protected portadaUrl(evento: EventoListado): string | null {
    const portada = this.findImageByType(evento.imagenes ?? [], 'portada');

    if (portada) {
      return portada;
    }

    return evento.imagen?.trim() ? evento.imagen : null;
  }

  protected estadoLabel(evento: EventoListado): string {
    return (evento.estado || 'publicado').replace(/_/g, ' ');
  }

  protected estadoClass(evento: EventoListado): string {
    const estado = (evento.estado || 'publicado').toLowerCase();

    if (estado === 'publicado') {
      return 'status-chip status-chip--published';
    }

    if (estado === 'borrador' || estado === 'pendiente') {
      return 'status-chip status-chip--draft';
    }

    if (estado === 'cancelado' || estado === 'inactivo') {
      return 'status-chip status-chip--inactive';
    }

    return 'status-chip';
  }

  private findImageByType(imagenes: EventoImagen[], tipo: string): string | null {
    const principal = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo && Number(imagen.principal) === 1);
    const fallback = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo);

    return principal?.url ?? fallback?.url ?? null;
  }
}
