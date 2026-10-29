import { DatePipe } from '@angular/common';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import {
  IonButton,
  IonCard,
  IonCardContent,
  IonCardHeader,
  IonCardSubtitle,
  IonCardTitle,
  IonContent,
  IonSpinner,
  IonText,
} from '@ionic/angular/standalone';
import { catchError, forkJoin, map, of, switchMap } from 'rxjs';

import { CategoriaPublica, CategoriasService } from '../../services/categorias.service';
import { EventoImagen, EventoListado, EventosService } from '../../services/eventos.service';

@Component({
  selector: 'app-eventos',
  templateUrl: './eventos.page.html',
  styleUrls: ['./eventos.page.scss'],
  imports: [
    DatePipe,
    RouterLink,
    IonButton,
    IonCard,
    IonCardContent,
    IonCardHeader,
    IonCardSubtitle,
    IonCardTitle,
    IonContent,
    IonSpinner,
    IonText,
  ],
})
export class EventosPage implements OnInit {
  private readonly eventosService = inject(EventosService);
  private readonly categoriasService = inject(CategoriasService);

  protected readonly loading = signal(true);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly eventos = signal<EventoListado[]>([]);
  protected readonly categorias = signal<CategoriaPublica[]>([]);
  protected readonly searchTerm = signal('');
  protected readonly selectedCategoryId = signal<number | null>(null);
  protected readonly filteredEventos = computed(() =>
    this.eventos().filter((evento) => {
      const normalizedSearch = this.searchTerm().trim().toLowerCase();
      const matchesSearch = normalizedSearch === ''
        || [evento.titulo, evento.descripcion, evento.lugar]
          .filter(Boolean)
          .some((value) => value.toLowerCase().includes(normalizedSearch));

      const matchesCategory = this.selectedCategoryId() === null
        || Number(evento.categoria?.id) === this.selectedCategoryId();

      return matchesSearch && matchesCategory;
    })
  );

  ngOnInit(): void {
    this.eventosService.obtenerEventos().pipe(
      switchMap((eventos) => {
        if (eventos.length === 0) {
          return of([] as EventoListado[]);
        }

        return forkJoin(
          eventos.map((evento) => {
            if ((evento.imagenes?.length ?? 0) > 0) {
              return of(evento);
            }

            return this.eventosService.obtenerImagenesPorEvento(evento.id).pipe(
              map((imagenes) => ({ ...evento, imagenes })),
              catchError(() => of({ ...evento, imagenes: [] }))
            );
          })
        );
      })
    ).subscribe({
      next: (eventos) => {
        this.eventos.set(eventos);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar los eventos disponibles.');
        this.loading.set(false);
      },
    });

    this.categoriasService.obtenerCategorias().subscribe({
      next: (categorias) => {
        this.categorias.set(categorias);
      },
      error: () => {
        this.categorias.set([]);
      },
    });
  }

  protected updateSearchTerm(event: Event): void {
    const value = (event.target as HTMLInputElement | null)?.value ?? '';
    this.searchTerm.set(value);
  }

  protected selectCategory(categoryId: number | null): void {
    this.selectedCategoryId.set(categoryId);
  }

  protected coverUrl(evento: EventoListado): string | null {
    const portada = this.findImageByType(evento.imagenes ?? [], 'portada');

    if (portada) {
      return portada;
    }

    return evento.imagen?.trim() ? evento.imagen : null;
  }

  private findImageByType(imagenes: EventoImagen[], tipo: string): string | null {
    const principal = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo && Number(imagen.principal) === 1);
    const fallback = imagenes.find((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo);

    return principal?.url ?? fallback?.url ?? null;
  }
}
