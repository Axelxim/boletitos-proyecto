import { CurrencyPipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, forkJoin, of } from 'rxjs';

import { AdminEventosService } from '../../services/admin-eventos.service';
import {
  AdminTiposBoletosService,
  TipoBoletoAdmin,
  TipoBoletoPayload,
} from '../../services/admin-tipos-boletos.service';
import { EventoListado } from '../../../public/services/eventos.service';

interface TipoBoletoFormState {
  nombre: string;
  descripcion: string;
  precio: number;
  cantidad_total: number;
  cantidad_disponible: number;
  max_por_compra: number;
  estado: string;
}

@Component({
  selector: 'app-admin-evento-boletos',
  templateUrl: './admin-evento-boletos.page.html',
  styleUrls: ['./admin-evento-boletos.page.scss'],
  imports: [CurrencyPipe, DecimalPipe, FormsModule, RouterLink, IonButton, IonSpinner, IonText],
})
export class AdminEventoBoletosPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly adminEventosService = inject(AdminEventosService);
  private readonly adminTiposBoletosService = inject(AdminTiposBoletosService);

  protected readonly loading = signal(true);
  protected readonly submitting = signal(false);
  protected readonly panelOpen = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly successMessage = signal<string | null>(null);
  protected readonly formErrors = signal<Record<string, string>>({});
  protected readonly evento = signal<EventoListado | null>(null);
  protected readonly tiposBoletos = signal<TipoBoletoAdmin[]>([]);
  protected readonly editingId = signal<number | null>(null);
  protected readonly eventoId = computed(() => Number(this.route.snapshot.paramMap.get('id') || 0));
  protected readonly panelTitle = computed(() => this.editingId() ? 'Editar tipo de boleto' : 'Nuevo tipo de boleto');
  protected readonly panelAction = computed(() => this.editingId() ? 'Guardar cambios' : 'Crear tipo');
  protected readonly form = signal<TipoBoletoFormState>(this.getDefaultForm());

  ngOnInit(): void {
    this.cargarDatos();
  }

  protected get estadoOptions(): string[] {
    return ['activo', 'inactivo'];
  }

  protected resumenDisponibilidad(tipo: TipoBoletoAdmin): string {
    return `${tipo.cantidad_disponible} de ${tipo.cantidad_total}`;
  }

  protected asNumber(value: unknown): number {
    const normalized = Number(value);
    return Number.isFinite(normalized) ? normalized : 0;
  }

  protected abrirNuevo(): void {
    this.editingId.set(null);
    this.form.set(this.getDefaultForm());
    this.formErrors.set({});
    this.errorMessage.set(null);
    this.successMessage.set(null);
    this.panelOpen.set(true);
  }

  protected editarTipo(tipo: TipoBoletoAdmin): void {
    this.editingId.set(tipo.id);
    this.form.set({
      nombre: tipo.nombre ?? '',
      descripcion: tipo.descripcion ?? '',
      precio: Number(tipo.precio ?? 0),
      cantidad_total: Number(tipo.cantidad_total ?? 0),
      cantidad_disponible: Number(tipo.cantidad_disponible ?? 0),
      max_por_compra: Number(tipo.max_por_compra ?? 1),
      estado: tipo.estado || 'activo',
    });
    this.formErrors.set({});
    this.errorMessage.set(null);
    this.successMessage.set(null);
    this.panelOpen.set(true);
  }

  protected cerrarPanel(): void {
    this.panelOpen.set(false);
    this.editingId.set(null);
    this.form.set(this.getDefaultForm());
    this.formErrors.set({});
  }

  protected updateField<K extends keyof TipoBoletoFormState>(field: K, value: TipoBoletoFormState[K]): void {
    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected updateNumberField(field: 'precio' | 'cantidad_total' | 'cantidad_disponible' | 'max_por_compra', value: unknown): void {
    this.updateField(field, this.asNumber(value) as TipoBoletoFormState[typeof field]);
  }

  protected stepField(field: 'cantidad_total' | 'cantidad_disponible' | 'max_por_compra', delta: number, min = 0): void {
    const current = this.asNumber(this.form()[field]);
    const next = Math.max(min, current + delta);
    this.updateField(field, next as TipoBoletoFormState[typeof field]);
  }

  protected guardarTipo(): void {
    const validationErrors = this.validateForm();

    if (Object.keys(validationErrors).length > 0) {
      this.formErrors.set(validationErrors);
      return;
    }

    const payload: TipoBoletoPayload = {
      evento_id: this.eventoId(),
      nombre: this.form().nombre.trim(),
      descripcion: this.form().descripcion.trim(),
      precio: Number(this.form().precio),
      cantidad_total: Number(this.form().cantidad_total),
      cantidad_disponible: Number(this.form().cantidad_disponible),
      max_por_compra: Number(this.form().max_por_compra),
      estado: this.form().estado,
    };

    this.submitting.set(true);
    this.formErrors.set({});
    this.errorMessage.set(null);
    this.successMessage.set(null);

    const request$ = this.editingId()
      ? this.adminTiposBoletosService.actualizar(this.editingId() as number, payload)
      : this.adminTiposBoletosService.crear(payload);

    request$.pipe(
      catchError((error) => {
        const resolved = this.resolveApiError(error);
        this.formErrors.set(resolved.errors);
        this.errorMessage.set(resolved.message);
        this.submitting.set(false);
        return of(null);
      })
    ).subscribe((result) => {
      if (!result) {
        return;
      }

      this.submitting.set(false);
      this.successMessage.set(this.editingId() ? 'Tipo de boleto actualizado correctamente.' : 'Tipo de boleto creado correctamente.');
      this.cerrarPanel();
      this.cargarTiposBoletos();
    });
  }

  protected eliminarTipo(tipo: TipoBoletoAdmin): void {
    this.errorMessage.set(null);
    this.successMessage.set(null);

    this.adminTiposBoletosService.eliminar(tipo.id).pipe(
      catchError((error) => {
        const resolved = this.resolveApiError(error);
        this.errorMessage.set(resolved.message || 'No fue posible eliminar el tipo de boleto.');
        return of(null);
      })
    ).subscribe((result) => {
      if (result === null) {
        return;
      }

      this.successMessage.set('Tipo de boleto eliminado correctamente.');
      this.cargarTiposBoletos();
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
      tipos: this.adminTiposBoletosService.obtenerTodos().pipe(catchError(() => of([] as TipoBoletoAdmin[]))),
    }).subscribe({
      next: ({ evento, tipos }) => {
        if (!evento) {
          this.errorMessage.set('No fue posible cargar el evento solicitado.');
          this.loading.set(false);
          return;
        }

        this.evento.set(evento);
        this.tiposBoletos.set(this.filterTiposPorEvento(tipos, eventoId));
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar los tipos de boletos del evento.');
        this.loading.set(false);
      },
    });
  }

  private cargarTiposBoletos(): void {
    this.adminTiposBoletosService.obtenerTodos().pipe(
      catchError(() => {
        this.errorMessage.set('No fue posible refrescar los tipos de boletos.');
        return of([] as TipoBoletoAdmin[]);
      })
    ).subscribe((tipos) => {
      this.tiposBoletos.set(this.filterTiposPorEvento(tipos, this.eventoId()));
    });
  }

  private filterTiposPorEvento(tipos: TipoBoletoAdmin[], eventoId: number): TipoBoletoAdmin[] {
    return tipos
      .filter((tipo) => Number(tipo.evento_id) === eventoId)
      .sort((left, right) => left.id - right.id);
  }

  private validateForm(): Record<string, string> {
    const form = this.form();
    const errors: Record<string, string> = {};

    if (!form.nombre.trim()) {
      errors['nombre'] = 'El nombre es obligatorio.';
    }

    if (Number(form.precio) < 0) {
      errors['precio'] = 'El precio no puede ser negativo.';
    }

    if (Number(form.cantidad_total) < 0) {
      errors['cantidad_total'] = 'La cantidad total no puede ser negativa.';
    }

    if (Number(form.cantidad_disponible) < 0) {
      errors['cantidad_disponible'] = 'La cantidad disponible no puede ser negativa.';
    }

    if (Number(form.cantidad_disponible) > Number(form.cantidad_total)) {
      errors['cantidad_disponible'] = 'La cantidad disponible no puede ser mayor que la cantidad total.';
    }

    if (Number(form.max_por_compra) <= 0) {
      errors['max_por_compra'] = 'El maximo por compra debe ser mayor a 0.';
    }

    return errors;
  }

  private getDefaultForm(): TipoBoletoFormState {
    return {
      nombre: '',
      descripcion: '',
      precio: 0,
      cantidad_total: 0,
      cantidad_disponible: 0,
      max_por_compra: 1,
      estado: 'activo',
    };
  }

  private resolveApiError(error: unknown): { message: string; errors: Record<string, string> } {
    if (error instanceof HttpErrorResponse) {
      const apiError = error.error as {
        message?: string;
        errors?: Record<string, string | string[]>;
      } | null;

      const normalizedErrors = Object.entries(apiError?.errors ?? {}).reduce<Record<string, string>>((acc, [key, value]) => {
        acc[key] = Array.isArray(value) ? (value[0] ?? '') : value;
        return acc;
      }, {});

      return {
        message: apiError?.message?.trim() || 'No fue posible completar la operacion.',
        errors: normalizedErrors,
      };
    }

    return {
      message: 'No fue posible completar la operacion.',
      errors: {},
    };
  }
}
