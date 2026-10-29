import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';

import { AdminSedesService, SedeAdmin } from '../../services/admin-sedes.service';

interface SedeForm {
  nombre: string;
  direccion: string;
  ciudad: string;
  estado: string;
  pais: string;
  capacidad: string;
  latitud: string;
  longitud: string;
  mapa_url: string;
  estado_sede: string;
}

@Component({
  selector: 'app-admin-sedes',
  templateUrl: './admin-sedes.page.html',
  styleUrls: ['./admin-sedes.page.scss'],
  imports: [FormsModule, IonButton, IonSpinner, IonText],
})
export class AdminSedesPage implements OnInit {
  private readonly adminSedesService = inject(AdminSedesService);

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly apiMessage = signal<string | null>(null);
  protected readonly sedes = signal<SedeAdmin[]>([]);
  protected readonly editingSedeId = signal<number | null>(null);
  protected readonly validationErrors = signal<Record<string, string>>({});
  protected readonly form = signal<SedeForm>({
    nombre: '',
    direccion: '',
    ciudad: '',
    estado: '',
    pais: '',
    capacidad: '',
    latitud: '',
    longitud: '',
    mapa_url: '',
    estado_sede: 'activo',
  });
  protected readonly summary = computed(() => ({
    total: this.sedes().length,
    activas: this.sedes().filter((sede) => (sede.estado_sede ?? '').toLowerCase() === 'activo').length,
    otras: this.sedes().filter((sede) => (sede.estado_sede ?? '').toLowerCase() !== 'activo').length,
  }));

  ngOnInit(): void {
    this.cargarSedes();
  }

  protected updateField(field: keyof SedeForm, value: string): void {
    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected nuevaSede(): void {
    this.editingSedeId.set(null);
    this.apiMessage.set(null);
    this.validationErrors.set({});
    this.form.set({
      nombre: '',
      direccion: '',
      ciudad: '',
      estado: '',
      pais: '',
      capacidad: '',
      latitud: '',
      longitud: '',
      mapa_url: '',
      estado_sede: 'activo',
    });
  }

  protected editarSede(sede: SedeAdmin): void {
    this.editingSedeId.set(Number(sede.id));
    this.apiMessage.set(null);
    this.validationErrors.set({});
    this.form.set({
      nombre: sede.nombre ?? '',
      direccion: sede.direccion ?? '',
      ciudad: sede.ciudad ?? '',
      estado: sede.estado ?? '',
      pais: sede.pais ?? '',
      capacidad: sede.capacidad !== undefined && sede.capacidad !== null ? String(sede.capacidad) : '',
      latitud: sede.latitud !== undefined && sede.latitud !== null ? String(sede.latitud) : '',
      longitud: sede.longitud !== undefined && sede.longitud !== null ? String(sede.longitud) : '',
      mapa_url: sede.mapa_url ?? '',
      estado_sede: sede.estado_sede ?? 'activo',
    });
  }

  protected guardarSede(): void {
    const errors = this.validateForm();
    this.validationErrors.set(errors);
    this.apiMessage.set(null);

    if (Object.keys(errors).length > 0) {
      return;
    }

    this.saving.set(true);
    const payload = {
      nombre: this.form().nombre.trim(),
      direccion: this.form().direccion.trim(),
      ciudad: this.form().ciudad.trim(),
      estado: this.form().estado.trim(),
      pais: this.form().pais.trim(),
      capacidad: this.form().capacidad ? Number(this.form().capacidad) : 0,
      latitud: this.form().latitud ? Number(this.form().latitud) : null,
      longitud: this.form().longitud ? Number(this.form().longitud) : null,
      mapa_url: this.form().mapa_url.trim(),
      estado_sede: this.form().estado_sede,
    };
    const request = this.editingSedeId()
      ? this.adminSedesService.actualizarSede(this.editingSedeId()!, payload)
      : this.adminSedesService.crearSede(payload);

    request.subscribe({
      next: () => {
        this.saving.set(false);
        this.apiMessage.set(this.editingSedeId() ? 'Sede actualizada correctamente.' : 'Sede creada correctamente.');
        this.nuevaSede();
        this.cargarSedes();
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.apiMessage.set(this.resolveApiError(error));
      },
    });
  }

  protected eliminarSede(sede: SedeAdmin): void {
    if (!window.confirm(`Se eliminara la sede "${sede.nombre}". ¿Deseas continuar?`)) {
      return;
    }

    this.apiMessage.set(null);
    this.adminSedesService.eliminarSede(Number(sede.id)).subscribe({
      next: () => {
        this.apiMessage.set('Sede eliminada correctamente.');
        if (this.editingSedeId() === Number(sede.id)) {
          this.nuevaSede();
        }
        this.cargarSedes();
      },
      error: (error: unknown) => {
        this.apiMessage.set(this.resolveApiError(error));
      },
    });
  }

  protected estadoClass(sede: SedeAdmin): string {
    return (sede.estado_sede ?? '').toLowerCase() === 'activo'
      ? 'status-chip status-chip--active'
      : 'status-chip status-chip--inactive';
  }

  private cargarSedes(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.adminSedesService.obtenerSedes().subscribe({
      next: (sedes) => {
        this.sedes.set(sedes);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar las sedes.');
        this.loading.set(false);
      },
    });
  }

  private validateForm(): Record<string, string> {
    const errors: Record<string, string> = {};
    const { nombre, direccion, capacidad, latitud, longitud } = this.form();

    if (!nombre.trim()) {
      errors['nombre'] = 'El nombre es obligatorio.';
    }

    if (!direccion.trim()) {
      errors['direccion'] = 'La direccion es obligatoria.';
    }

    if (capacidad && Number(capacidad) < 0) {
      errors['capacidad'] = 'La capacidad no puede ser negativa.';
    }

    if (latitud && (Number(latitud) < -90 || Number(latitud) > 90)) {
      errors['latitud'] = 'La latitud debe estar entre -90 y 90.';
    }

    if (longitud && (Number(longitud) < -180 || Number(longitud) > 180)) {
      errors['longitud'] = 'La longitud debe estar entre -180 y 180.';
    }

    return errors;
  }

  private resolveApiError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      const apiMessage = error.error?.message;
      if (typeof apiMessage === 'string' && apiMessage.trim()) {
        return apiMessage;
      }
    }

    return 'No fue posible completar la operacion.';
  }
}
