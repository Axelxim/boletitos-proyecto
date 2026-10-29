import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';

import { AdminCategoriasService, CategoriaAdmin } from '../../services/admin-categorias.service';

interface CategoriaForm {
  nombre: string;
  descripcion: string;
  estado: string;
}

@Component({
  selector: 'app-admin-categorias',
  templateUrl: './admin-categorias.page.html',
  styleUrls: ['./admin-categorias.page.scss'],
  imports: [DatePipe, FormsModule, IonButton, IonSpinner, IonText],
})
export class AdminCategoriasPage implements OnInit {
  private readonly adminCategoriasService = inject(AdminCategoriasService);

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly apiMessage = signal<string | null>(null);
  protected readonly categorias = signal<CategoriaAdmin[]>([]);
  protected readonly editingCategoriaId = signal<number | null>(null);
  protected readonly validationErrors = signal<Record<string, string>>({});
  protected readonly form = signal<CategoriaForm>({
    nombre: '',
    descripcion: '',
    estado: 'activo',
  });
  protected readonly summary = computed(() => ({
    total: this.categorias().length,
    activas: this.categorias().filter((categoria) => (categoria.estado ?? '').toLowerCase() === 'activo').length,
    inactivas: this.categorias().filter((categoria) => (categoria.estado ?? '').toLowerCase() !== 'activo').length,
  }));

  ngOnInit(): void {
    this.cargarCategorias();
  }

  protected updateField(field: keyof CategoriaForm, value: string): void {
    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected nuevaCategoria(): void {
    this.editingCategoriaId.set(null);
    this.apiMessage.set(null);
    this.validationErrors.set({});
    this.form.set({
      nombre: '',
      descripcion: '',
      estado: 'activo',
    });
  }

  protected editarCategoria(categoria: CategoriaAdmin): void {
    this.editingCategoriaId.set(Number(categoria.id));
    this.apiMessage.set(null);
    this.validationErrors.set({});
    this.form.set({
      nombre: categoria.nombre ?? '',
      descripcion: categoria.descripcion ?? '',
      estado: categoria.estado ?? 'activo',
    });
  }

  protected guardarCategoria(): void {
    const errors = this.validateForm();
    this.validationErrors.set(errors);
    this.apiMessage.set(null);

    if (Object.keys(errors).length > 0) {
      return;
    }

    this.saving.set(true);
    const payload = {
      nombre: this.form().nombre.trim(),
      descripcion: this.form().descripcion.trim(),
      estado: this.form().estado,
    };
    const request = this.editingCategoriaId()
      ? this.adminCategoriasService.actualizarCategoria(this.editingCategoriaId()!, payload)
      : this.adminCategoriasService.crearCategoria(payload);

    request.subscribe({
      next: () => {
        this.saving.set(false);
        this.apiMessage.set(this.editingCategoriaId() ? 'Categoria actualizada correctamente.' : 'Categoria creada correctamente.');
        this.nuevaCategoria();
        this.cargarCategorias();
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.apiMessage.set(this.resolveApiError(error));
      },
    });
  }

  protected eliminarCategoria(categoria: CategoriaAdmin): void {
    if (!window.confirm(`Se eliminara la categoria "${categoria.nombre}". ¿Deseas continuar?`)) {
      return;
    }

    this.apiMessage.set(null);
    this.adminCategoriasService.eliminarCategoria(Number(categoria.id)).subscribe({
      next: () => {
        this.apiMessage.set('Categoria eliminada correctamente.');
        if (this.editingCategoriaId() === Number(categoria.id)) {
          this.nuevaCategoria();
        }
        this.cargarCategorias();
      },
      error: (error: unknown) => {
        this.apiMessage.set(this.resolveApiError(error));
      },
    });
  }

  protected estadoClass(categoria: CategoriaAdmin): string {
    return (categoria.estado ?? '').toLowerCase() === 'activo'
      ? 'status-chip status-chip--active'
      : 'status-chip status-chip--inactive';
  }

  private cargarCategorias(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.adminCategoriasService.obtenerCategorias().subscribe({
      next: (categorias) => {
        this.categorias.set(categorias);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar las categorias.');
        this.loading.set(false);
      },
    });
  }

  private validateForm(): Record<string, string> {
    const errors: Record<string, string> = {};

    if (!this.form().nombre.trim()) {
      errors['nombre'] = 'El nombre es obligatorio.';
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
