import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, forkJoin, of } from 'rxjs';

import { AdminEventosService } from '../../services/admin-eventos.service';
import { CategoriaPublica, CategoriasService } from '../../../public/services/categorias.service';
import { EventoListado } from '../../../public/services/eventos.service';
import { SedePublica, SedesService } from '../../../public/services/sedes.service';

type EventoFormField =
  | 'categoria_id'
  | 'sede_id'
  | 'titulo'
  | 'descripcion'
  | 'lugar'
  | 'direccion'
  | 'fecha_inicio'
  | 'fecha_fin'
  | 'fecha_evento'
  | 'hora_evento'
  | 'estado';

interface EventoFormState {
  categoria_id: string;
  sede_id: string;
  titulo: string;
  descripcion: string;
  lugar: string;
  direccion: string;
  fecha_inicio: string;
  fecha_fin: string;
  fecha_evento: string;
  hora_evento: string;
  estado: string;
}

@Component({
  selector: 'app-admin-evento-form',
  templateUrl: './admin-evento-form.page.html',
  styleUrls: ['./admin-evento-form.page.scss'],
  imports: [FormsModule, RouterLink, IonButton, IonSpinner, IonText],
})
export class AdminEventoFormPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly adminEventosService = inject(AdminEventosService);
  private readonly categoriasService = inject(CategoriasService);
  private readonly sedesService = inject(SedesService);

  protected readonly loading = signal(true);
  protected readonly submitting = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly categorias = signal<CategoriaPublica[]>([]);
  protected readonly sedes = signal<SedePublica[]>([]);
  protected readonly validationErrors = signal<Record<string, string>>({});
  protected readonly form = signal<EventoFormState>({
    categoria_id: '',
    sede_id: '',
    titulo: '',
    descripcion: '',
    lugar: '',
    direccion: '',
    fecha_inicio: '',
    fecha_fin: '',
    fecha_evento: '',
    hora_evento: '',
    estado: 'borrador',
  });
  protected readonly eventoId = computed(() => Number(this.route.snapshot.paramMap.get('id') || 0));
  protected readonly isEditMode = computed(() => this.eventoId() > 0);
  protected readonly pageTitle = computed(() => this.isEditMode() ? 'Editar evento' : 'Nuevo evento');
  protected readonly pageDescription = computed(() => this.isEditMode()
    ? 'Actualiza la informacion principal del evento.'
    : 'Completa la informacion base para registrar un nuevo evento.');

  ngOnInit(): void {
    const eventoId = this.eventoId();

    forkJoin({
      categorias: this.categoriasService.obtenerCategorias().pipe(catchError(() => of([]))),
      sedes: this.sedesService.obtenerSedes().pipe(catchError(() => of([]))),
      evento: this.isEditMode()
        ? this.adminEventosService.obtenerEventoPorId(eventoId).pipe(catchError(() => of(null)))
        : of(null),
    }).subscribe({
      next: ({ categorias, sedes, evento }) => {
        this.categorias.set(categorias);
        this.sedes.set(sedes);

        if (this.isEditMode() && !evento) {
          this.errorMessage.set('No fue posible cargar el evento para editar.');
          this.loading.set(false);
          return;
        }

        if (evento) {
          this.patchForm(evento);
        }

        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible preparar el formulario del evento.');
        this.loading.set(false);
      },
    });
  }

  protected updateField(field: EventoFormField, event: Event): void {
    const target = event.target as HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement | null;
    const value = target?.value ?? '';

    this.form.set({
      ...this.form(),
      [field]: value,
    });

    if (this.validationErrors()[field]) {
      const nextErrors = { ...this.validationErrors() };
      delete nextErrors[field];
      this.validationErrors.set(nextErrors);
    }
  }

  protected updateSelectField(field: EventoFormField, value: string): void {
    this.form.set({
      ...this.form(),
      [field]: value ?? '',
    });

    if (this.validationErrors()[field]) {
      const nextErrors = { ...this.validationErrors() };
      delete nextErrors[field];
      this.validationErrors.set(nextErrors);
    }
  }

  protected guardar(): void {
    const errors = this.validateForm();

    if (Object.keys(errors).length > 0) {
      this.validationErrors.set(errors);
      return;
    }

    this.submitting.set(true);
    this.errorMessage.set(null);
    this.validationErrors.set({});

    const payload = this.buildPayload();
    const request = this.isEditMode()
      ? this.adminEventosService.actualizarEvento(this.eventoId(), payload)
      : this.adminEventosService.crearEvento(payload);

    request.pipe(
      catchError(() => {
        this.errorMessage.set('No fue posible guardar el evento. Verifica la informacion e intenta nuevamente.');
        this.submitting.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.submitting.set(false);
      void this.router.navigate(['/admin/eventos']);
    });
  }

  private patchForm(evento: EventoListado): void {
    this.form.set({
      categoria_id: String(evento.categoria_id ?? evento.categoria?.id ?? ''),
      sede_id: String(evento.sede_id ?? evento.sede?.id ?? ''),
      titulo: evento.titulo ?? '',
      descripcion: evento.descripcion ?? '',
      lugar: evento.lugar ?? '',
      direccion: evento.direccion ?? '',
      fecha_inicio: this.toDateTimeLocal(evento.fecha_inicio),
      fecha_fin: this.toDateTimeLocal(evento.fecha_fin),
      fecha_evento: this.toDateInput(evento.fecha_evento ?? evento.fecha_inicio),
      hora_evento: this.toTimeInput(evento.hora_evento ?? evento.fecha_inicio),
      estado: evento.estado ?? 'borrador',
    });
  }

  private validateForm(): Record<string, string> {
    const form = this.form();
    const errors: Record<string, string> = {};

    if (!form.categoria_id) {
      errors['categoria_id'] = 'La categoria es obligatoria.';
    }

    if (!form.sede_id) {
      errors['sede_id'] = 'La sede es obligatoria.';
    }

    if (!form.titulo.trim()) {
      errors['titulo'] = 'El titulo es obligatorio.';
    }

    if (!form.lugar.trim()) {
      errors['lugar'] = 'El lugar es obligatorio.';
    }

    if (!form.fecha_inicio) {
      errors['fecha_inicio'] = 'La fecha de inicio es obligatoria.';
    }

    if (!form.fecha_fin) {
      errors['fecha_fin'] = 'La fecha de fin es obligatoria.';
    }

    if (form.fecha_inicio && form.fecha_fin && new Date(form.fecha_fin) < new Date(form.fecha_inicio)) {
      errors['fecha_fin'] = 'La fecha de fin no puede ser menor que la fecha de inicio.';
    }

    return errors;
  }

  private buildPayload(): Record<string, unknown> {
    const form = this.form();

    return {
      categoria_id: Number(form.categoria_id),
      sede_id: Number(form.sede_id),
      titulo: form.titulo.trim(),
      descripcion: form.descripcion.trim(),
      lugar: form.lugar.trim(),
      direccion: form.direccion.trim(),
      fecha_inicio: this.fromDateTimeLocal(form.fecha_inicio),
      fecha_fin: this.fromDateTimeLocal(form.fecha_fin),
      fecha_evento: form.fecha_evento,
      hora_evento: form.hora_evento,
      estado: form.estado,
    };
  }

  private toDateTimeLocal(value?: string): string {
    if (!value) {
      return '';
    }

    return value.replace(' ', 'T').slice(0, 16);
  }

  private toDateInput(value?: string): string {
    if (!value) {
      return '';
    }

    return value.slice(0, 10);
  }

  private toTimeInput(value?: string): string {
    if (!value) {
      return '';
    }

    if (value.includes(':') && value.length <= 8) {
      return value.slice(0, 5);
    }

    const dateTime = value.replace(' ', 'T');
    return dateTime.length >= 16 ? dateTime.slice(11, 16) : '';
  }

  private fromDateTimeLocal(value: string): string {
    return value ? value.replace('T', ' ') + ':00' : '';
  }
}
