import { DatePipe, DecimalPipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import {
  IonButton,
  IonContent,
  IonSpinner,
  IonText,
} from '@ionic/angular/standalone';
import { catchError, forkJoin, of } from 'rxjs';

import {
  EventoImagen,
  EventoListado,
  EventosService,
  TipoBoletoDisponible,
} from '../../services/eventos.service';
import { CheckoutService } from '../../services/checkout.service';

@Component({
  selector: 'app-evento-detalle',
  templateUrl: './evento-detalle.page.html',
  styleUrls: ['./evento-detalle.page.scss'],
  imports: [
    DatePipe,
    DecimalPipe,
    IonButton,
    IonContent,
    IonSpinner,
    IonText,
  ],
})
export class EventoDetallePage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly eventosService = inject(EventosService);
  private readonly checkoutService = inject(CheckoutService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly evento = signal<EventoListado | null>(null);
  protected readonly imagenes = signal<EventoImagen[]>([]);
  protected readonly tiposBoletos = signal<TipoBoletoDisponible[]>([]);
  protected readonly cantidades = signal<Record<number, number>>({});
  protected readonly totalSeleccionado = computed(() =>
    this.tiposBoletos().reduce((total, tipo) => total + (this.cantidades()[tipo.id] ?? 0), 0)
  );
  protected readonly montoSeleccionado = computed(() =>
    this.tiposBoletos().reduce((total, tipo) => total + ((this.cantidades()[tipo.id] ?? 0) * Number(tipo.precio)), 0)
  );
  protected readonly bannerUrl = computed(() => this.findImageByType('banner'));
  protected readonly heroImageUrl = computed(() => this.bannerUrl() ?? this.findImageByType('portada'));

  ngOnInit(): void {
    const eventoId = Number(this.route.snapshot.paramMap.get('id'));

    if (!Number.isInteger(eventoId) || eventoId <= 0) {
      this.errorMessage.set('El evento solicitado es invalido.');
      this.loading.set(false);
      return;
    }

    forkJoin({
      evento: this.eventosService.obtenerEventoPorId(eventoId),
      tipos: this.eventosService.obtenerTiposBoletosPorEvento(eventoId).pipe(catchError(() => of([]))),
      imagenes: this.eventosService.obtenerImagenesPorEvento(eventoId).pipe(catchError(() => of([]))),
    }).subscribe({
      next: ({ evento, tipos, imagenes }) => {
        this.evento.set({ ...evento, imagenes });
        this.imagenes.set(imagenes);
        this.tiposBoletos.set(tipos);
        this.cantidades.set(
          tipos.reduce<Record<number, number>>((acc, tipo) => {
            acc[tipo.id] = 0;
            return acc;
          }, {})
        );
        this.loading.set(false);
        this.eventosService.registrarInteraccion(eventoId, 'vista').pipe(catchError(() => of(null))).subscribe();
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar el detalle del evento.');
        this.loading.set(false);
      },
    });
  }

  protected actualizarCantidad(tipoId: number, event: Event, maximo: number): void {
    const input = event.target as HTMLInputElement | null;
    const value = Number(input?.value ?? 0);
    const normalized = Number.isFinite(value) ? Math.max(0, Math.min(maximo, Math.floor(value))) : 0;

    this.cantidades.set({
      ...this.cantidades(),
      [tipoId]: normalized,
    });
  }

  protected cambiarCantidad(tipoId: number, delta: number, maximo: number): void {
    const actual = this.cantidades()[tipoId] ?? 0;
    const siguiente = Math.max(0, Math.min(maximo, actual + delta));

    this.cantidades.set({
      ...this.cantidades(),
      [tipoId]: siguiente,
    });
  }

  protected maximoSeleccionable(tipo: TipoBoletoDisponible): number {
    const disponible = Math.max(0, Number(tipo.cantidad_disponible ?? 0));
    const maxPorCompra = Number(tipo.max_por_compra ?? disponible ?? 0);

    return Math.max(0, Math.min(disponible, maxPorCompra || disponible));
  }

  protected continuarCompra(): void {
    const evento = this.evento();

    if (!evento || this.totalSeleccionado() === 0) {
      return;
    }

    this.checkoutService.saveSelection(evento, this.tiposBoletos(), this.cantidades());
    this.router.navigate(['/checkout']);
  }

  private findImageByType(tipo: string): string | null {
    const imagenes = this.imagenes();
    const principal = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo && Number(imagen.principal) === 1);
    const fallback = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo);

    return principal?.url ?? fallback?.url ?? null;
  }
}
