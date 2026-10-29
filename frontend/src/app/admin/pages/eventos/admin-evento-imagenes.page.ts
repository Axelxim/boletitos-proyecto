import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, forkJoin, map, of, switchMap } from 'rxjs';

import { AdminEventoImagenesService } from '../../services/admin-evento-imagenes.service';
import { AdminEventosService } from '../../services/admin-eventos.service';
import { EventoImagen, EventoListado } from '../../../public/services/eventos.service';

@Component({
  selector: 'app-admin-evento-imagenes',
  templateUrl: './admin-evento-imagenes.page.html',
  styleUrls: ['./admin-evento-imagenes.page.scss'],
  imports: [DatePipe, FormsModule, RouterLink, IonButton, IonSpinner, IonText],
})
export class AdminEventoImagenesPage implements OnInit {
  private static readonly ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  private static readonly MAX_IMAGE_SIZE = 2 * 1024 * 1024;

  private readonly route = inject(ActivatedRoute);
  private readonly adminEventosService = inject(AdminEventosService);
  private readonly imagenesService = inject(AdminEventoImagenesService);

  protected readonly loading = signal(true);
  protected readonly uploading = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly evento = signal<EventoListado | null>(null);
  protected readonly imagenes = signal<EventoImagen[]>([]);
  protected readonly ordenForm = signal<Record<number, number>>({});
  protected readonly eventoId = computed(() => Number(this.route.snapshot.paramMap.get('id') || 0));
  protected readonly portada = computed(() => this.findSingleByType('portada'));
  protected readonly banner = computed(() => this.findSingleByType('banner'));
  protected readonly galeria = computed(() => {
    return this.imagenes()
      .filter((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === 'galeria')
      .sort((a, b) => (a.orden - b.orden) || (a.id - b.id));
  });

  ngOnInit(): void {
    this.cargarDatos();
  }

  protected getFileName(imagen: EventoImagen): string {
    return imagen.ruta.split('/').pop() || 'imagen';
  }

  protected updateOrden(imagenId: number, event: Event): void {
    const value = Number((event.target as HTMLInputElement | null)?.value ?? 0);
    this.ordenForm.set({
      ...this.ordenForm(),
      [imagenId]: Number.isFinite(value) ? Math.max(0, Math.floor(value)) : 0,
    });
  }

  protected cambiarPortada(event: Event): void {
    this.uploadSingle(event, 'portada', 1, 0);
  }

  protected cambiarBanner(event: Event): void {
    this.uploadSingle(event, 'banner', 1, 0);
  }

  protected agregarGaleria(event: Event): void {
    const input = event.target as HTMLInputElement | null;
    const files = Array.from(input?.files ?? []);

    if (files.length === 0 || this.eventoId() <= 0) {
      return;
    }

    this.successMessage.set(null);

    const invalidFile = files.find((file) => this.validateFile(file) !== null);

    if (invalidFile) {
      this.errorMessage.set(this.validateFile(invalidFile));
      this.resetInput(input);
      return;
    }

    this.uploading.set(true);
    this.errorMessage.set(null);

    const ordenBase = this.galeria().length;
    forkJoin(
      files.map((file, index) =>
        this.imagenesService.subirImagen(this.eventoId(), file, {
          tipo: 'galeria',
          principal: 0,
          orden: ordenBase + index,
        })
      )
    ).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveUploadError(error, 'No fue posible agregar las imagenes de galeria.'));
        this.successMessage.set(null);
        this.uploading.set(false);
        this.resetInput(input);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.uploading.set(false);
      this.resetInput(input);
      this.successMessage.set('Imagenes de galeria cargadas correctamente.');
      this.cargarImagenes();
    });
  }

  protected guardarOrden(imagen: EventoImagen): void {
    const orden = this.ordenForm()[imagen.id] ?? imagen.orden;
    this.successMessage.set(null);

    this.imagenesService.actualizarImagen(this.eventoId(), imagen.id, {
      tipo: (imagen.tipo as 'portada' | 'banner' | 'galeria' | 'miniatura'),
      principal: imagen.principal === 1 ? 1 : 0,
      orden,
      estado: imagen.estado === 'inactivo' ? 'inactivo' : 'activo',
    }).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveUploadError(error, 'No fue posible actualizar el orden de la imagen.'));
        this.successMessage.set(null);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.errorMessage.set(null);
      this.successMessage.set('Orden de imagen actualizado correctamente.');
      this.cargarImagenes();
    });
  }

  protected eliminarImagen(imagen: EventoImagen): void {
    this.successMessage.set(null);

    this.imagenesService.eliminarImagen(this.eventoId(), imagen.id).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveUploadError(error, 'No fue posible eliminar la imagen seleccionada.'));
        this.successMessage.set(null);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result && result !== null) {
        return;
      }

      this.errorMessage.set(null);
      this.successMessage.set('Imagen eliminada correctamente.');
      this.cargarImagenes();
    });
  }

  private cargarDatos(): void {
    const eventoId = this.eventoId();

    if (eventoId <= 0) {
      this.errorMessage.set('El identificador del evento es invalido.');
      this.loading.set(false);
      return;
    }

    forkJoin({
      evento: this.adminEventosService.obtenerEventoPorId(eventoId).pipe(catchError(() => of(null))),
      imagenes: this.imagenesService.obtenerImagenes(eventoId).pipe(catchError(() => of([]))),
    }).subscribe({
      next: ({ evento, imagenes }) => {
        if (!evento) {
          this.errorMessage.set('No fue posible cargar el evento con los endpoints disponibles.');
          this.loading.set(false);
          return;
        }

        this.evento.set(evento);
        this.imagenes.set(imagenes);
        this.syncOrdenForm(imagenes);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar las imagenes del evento.');
        this.loading.set(false);
      },
    });
  }

  private cargarImagenes(): void {
    this.imagenesService.obtenerImagenes(this.eventoId()).pipe(
      catchError(() => {
        this.errorMessage.set('No fue posible refrescar las imagenes del evento.');
        return of([] as EventoImagen[]);
      })
    ).subscribe((imagenes) => {
      this.imagenes.set(imagenes);
      this.syncOrdenForm(imagenes);
    });
  }

  private uploadSingle(event: Event, tipo: 'portada' | 'banner', principal: 0 | 1, orden: number): void {
    const input = event.target as HTMLInputElement | null;
    const file = input?.files?.[0];

    if (!file || this.eventoId() <= 0) {
      return;
    }

    this.successMessage.set(null);

    const fileError = this.validateFile(file);

    if (fileError) {
      this.errorMessage.set(fileError);
      this.resetInput(input);
      return;
    }

    this.uploading.set(true);
    this.errorMessage.set(null);

    this.imagenesService.subirImagen(this.eventoId(), file, {
      tipo,
      principal,
      orden,
    }).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveUploadError(error, `No fue posible actualizar la ${tipo}.`));
        this.successMessage.set(null);
        this.uploading.set(false);
        this.resetInput(input);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.uploading.set(false);
      this.resetInput(input);
      this.successMessage.set(`La ${tipo} se cargo correctamente.`);
      this.cargarImagenes();
    });
  }

  private findSingleByType(tipo: 'portada' | 'banner'): EventoImagen | null {
    const activas = this.imagenes().filter((imagen) => imagen.estado !== 'inactivo' && imagen.tipo === tipo);
    const principal = activas.find((imagen) => imagen.principal === 1);
    return principal ?? activas[0] ?? null;
  }

  private syncOrdenForm(imagenes: EventoImagen[]): void {
    this.ordenForm.set(
      imagenes.reduce<Record<number, number>>((acc, imagen) => {
        acc[imagen.id] = imagen.orden;
        return acc;
      }, {})
    );
  }

  private resetInput(input: HTMLInputElement | null): void {
    if (input) {
      input.value = '';
    }
  }

  private validateFile(file: File): string | null {
    if (!AdminEventoImagenesPage.ALLOWED_IMAGE_TYPES.includes(file.type)) {
      return 'Solo se permiten imagenes JPG, PNG o WEBP.';
    }

    if (file.size > AdminEventoImagenesPage.MAX_IMAGE_SIZE) {
      return 'La imagen supera el tamano maximo permitido de 2 MB.';
    }

    return null;
  }

  private resolveUploadError(error: unknown, fallbackMessage: string): string {
    if (error instanceof HttpErrorResponse) {
      const apiError = error.error as {
        message?: string;
        errors?: Record<string, string | string[]>;
      } | null;

      const firstFieldError = apiError?.errors
        ? Object.values(apiError.errors).reduce<string | null>((found, value) => {
          if (found) {
            return found;
          }

          if (Array.isArray(value)) {
            return value.find((item) => typeof item === 'string' && item.trim().length > 0) ?? null;
          }

          return typeof value === 'string' && value.trim().length > 0 ? value : null;
        }, null)
        : null;

      if (typeof firstFieldError === 'string' && firstFieldError.trim().length > 0) {
        return firstFieldError;
      }

      if (typeof apiError?.message === 'string' && apiError.message.trim().length > 0) {
        return apiError.message;
      }
    }

    return fallbackMessage;
  }
}
