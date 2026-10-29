import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonCard, IonCardContent, IonCardHeader, IonCardSubtitle, IonCardTitle, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, map, of, switchMap } from 'rxjs';

import { CategoriaPublica, CategoriasService } from '../public/services/categorias.service';
import { EventoListado, EventosService } from '../public/services/eventos.service';

@Component({
  selector: 'app-home',
  templateUrl: 'home.page.html',
  styleUrls: ['home.page.scss'],
  imports: [DatePipe, RouterLink, IonButton, IonCard, IonCardContent, IonCardHeader, IonCardSubtitle, IonCardTitle, IonContent, IonSpinner, IonText],
})
export class HomePage implements OnInit {
  private readonly eventosService = inject(EventosService);
  private readonly categoriasService = inject(CategoriasService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly featuredEvents = signal<EventoListado[]>([]);
  protected readonly eventos = signal<EventoListado[]>([]);
  protected readonly categorias = signal<CategoriaPublica[]>([]);
  protected readonly currentFeaturedIndex = signal(0);
  protected readonly featuredCarouselEvents = computed(() => this.featuredEvents().slice(0, 5));
  protected readonly currentFeaturedEvent = computed(() => {
    const events = this.featuredCarouselEvents();

    if (events.length === 0) {
      return null;
    }

    const index = Math.min(this.currentFeaturedIndex(), events.length - 1);

    return events[index] ?? null;
  });
  protected readonly upcomingEvents = computed(() => {
    const featuredIds = new Set(this.featuredCarouselEvents().map((evento) => evento.id));

    return this.eventos().filter((evento) => !featuredIds.has(evento.id)).slice(0, 6);
  });

  ngOnInit(): void {
    this.eventosService.obtenerEventosDestacados().pipe(
      catchError(() => of([])),
      switchMap((destacados) => this.eventosService.obtenerEventos().pipe(
        map((eventos) => ({ destacados, eventos }))
      ))
    ).subscribe({
      next: ({ destacados, eventos }: { destacados: EventoListado[]; eventos: EventoListado[] }) => {
        this.eventos.set(eventos);
        this.featuredEvents.set(destacados.length > 0 ? destacados : eventos);
        this.currentFeaturedIndex.set(0);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar los eventos destacados.');
        this.loading.set(false);
      },
    });

    this.categoriasService.obtenerCategorias().subscribe({
      next: (categorias) => {
        this.categorias.set(categorias.slice(0, 6));
      },
      error: () => {
        this.categorias.set([]);
      },
    });
  }

  protected previousFeatured(): void {
    const events = this.featuredCarouselEvents();

    if (events.length <= 1) {
      return;
    }

    const nextIndex = this.currentFeaturedIndex() === 0
      ? events.length - 1
      : this.currentFeaturedIndex() - 1;

    this.currentFeaturedIndex.set(nextIndex);
  }

  protected nextFeatured(): void {
    const events = this.featuredCarouselEvents();

    if (events.length <= 1) {
      return;
    }

    this.currentFeaturedIndex.set((this.currentFeaturedIndex() + 1) % events.length);
  }

  protected goToFeatured(index: number): void {
    this.currentFeaturedIndex.set(index);
  }

  protected onFeaturedAction(evento: EventoListado): void {
    this.registrarClick(evento.id);
  }

  protected onUpcomingAction(evento: EventoListado): void {
    this.registrarClick(evento.id);
  }

  protected featuredBannerUrl(evento: EventoListado): string | null {
    return this.findImageByType(evento, 'banner');
  }

  protected upcomingCoverUrl(evento: EventoListado): string | null {
    return this.findImageByType(evento, 'portada');
  }

  private findImageByType(evento: EventoListado, tipo: string): string | null {
    const imagenes = evento.imagenes ?? [];
    const principal = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo && Number(imagen.principal) === 1);
    const fallback = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo);

    return principal?.url ?? fallback?.url ?? null;
  }

  private registrarClick(eventoId: number): void {
    this.eventosService.registrarInteraccion(eventoId, 'click').pipe(
      catchError(() => of(null))
    ).subscribe();
  }
}
