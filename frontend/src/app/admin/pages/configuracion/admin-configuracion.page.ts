import { KeyValuePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';

import {
  AdminConfiguracionService,
  ConfiguracionField,
  ConfiguracionPayload,
  ConfiguracionSection,
} from '../../services/admin-configuracion.service';

type ConfigTab = 'general' | 'apariencia' | 'compras' | 'pagos' | 'correo' | 'boletos' | 'eventos';

@Component({
  selector: 'app-admin-configuracion',
  templateUrl: './admin-configuracion.page.html',
  styleUrls: ['./admin-configuracion.page.scss'],
  imports: [FormsModule, IonButton, IonSpinner, IonText, KeyValuePipe],
})
export class AdminConfiguracionPage implements OnInit {
  private readonly configuracionService = inject(AdminConfiguracionService);

  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly apiMessage = signal<string | null>(null);
  protected readonly validationErrors = signal<Record<string, string>>({});
  protected readonly configData = signal<ConfiguracionPayload | null>(null);
  protected readonly activeTab = signal<ConfigTab>('general');
  protected readonly initialValues = signal<Record<string, string | number | boolean>>({});
  protected readonly formValues = signal<Record<string, string | number | boolean>>({});

  protected readonly tabs = [
    { key: 'general', label: 'General' },
    { key: 'apariencia', label: 'Apariencia' },
    { key: 'compras', label: 'Compras' },
    { key: 'pagos', label: 'Pagos' },
    { key: 'correo', label: 'Correo' },
    { key: 'boletos', label: 'Boletos' },
    { key: 'eventos', label: 'Eventos' },
  ] as const;

  protected readonly currentSection = computed<ConfiguracionSection | null>(() => {
    const sections = this.configData()?.sections;
    return sections?.[this.activeTab()] ?? null;
  });

  protected readonly changedKeys = computed(() => {
    const current = this.formValues();
    const initial = this.initialValues();

    return Object.keys(current).filter((key) => current[key] !== initial[key]);
  });

  protected readonly hasChanges = computed(() => this.changedKeys().length > 0);

  ngOnInit(): void {
    this.cargarConfiguracion();
  }

  protected seleccionarTab(tab: ConfigTab): void {
    this.activeTab.set(tab);
    this.apiMessage.set(null);
  }

  protected updateField(field: ConfiguracionField, value: string | number | boolean): void {
    const nextValue = field.type === 'boolean'
      ? value === true || value === 'true'
      : (field.type === 'number' || field.type === 'integer')
        ? (value === '' ? '' : Number(value))
        : value;

    this.formValues.set({
      ...this.formValues(),
      [field.key]: nextValue as string | number | boolean,
    });
  }

  protected guardarCambios(): void {
    const payload = this.changedKeys().reduce<Record<string, unknown>>((carry, key) => {
      carry[key] = this.formValues()[key];
      return carry;
    }, {});

    if (Object.keys(payload).length === 0) {
      this.apiMessage.set('No hay cambios por guardar.');
      return;
    }

    const validation = this.validatePayload(payload);
    this.validationErrors.set(validation);
    this.apiMessage.set(null);

    if (Object.keys(validation).length > 0) {
      return;
    }

    this.saving.set(true);

    this.configuracionService.actualizarConfiguracion(payload).subscribe({
      next: (data) => {
        this.saving.set(false);
        this.apiMessage.set('Configuracion actualizada correctamente.');
        this.hydrateData(data);
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.apiMessage.set(this.resolveApiError(error));
      },
    });
  }

  protected restaurarFormulario(): void {
    this.formValues.set({ ...this.initialValues() });
    this.validationErrors.set({});
    this.apiMessage.set('Se restauraron los valores cargados en el formulario.');
  }

  protected isBooleanField(field: ConfiguracionField): boolean {
    return field.type === 'boolean';
  }

  protected isTextareaField(field: ConfiguracionField): boolean {
    return field.type === 'textarea';
  }

  protected isSelectField(field: ConfiguracionField): boolean {
    return field.type === 'select';
  }

  protected fieldStringValue(field: ConfiguracionField): string {
    return String(this.formValues()[field.key]);
  }

  protected isReadonlySection(): boolean {
    return this.activeTab() === 'correo';
  }

  private cargarConfiguracion(): void {
    this.loading.set(true);
    this.errorMessage.set(null);

    this.configuracionService.obtenerConfiguracion().subscribe({
      next: (data) => {
        this.hydrateData(data);
        this.loading.set(false);
      },
      error: () => {
        this.errorMessage.set('No fue posible cargar la configuracion general.');
        this.loading.set(false);
      },
    });
  }

  private hydrateData(data: ConfiguracionPayload): void {
    const values: Record<string, string | number | boolean> = {};

    Object.values(data.sections).forEach((section) => {
      section.fields.forEach((field) => {
        values[field.key] = field.value;
      });
    });

    this.configData.set(data);
    this.initialValues.set({ ...values });
    this.formValues.set({ ...values });
    this.validationErrors.set({});
  }

  private validatePayload(payload: Record<string, unknown>): Record<string, string> {
    const errors: Record<string, string> = {};

    for (const [key, rawValue] of Object.entries(payload)) {
      const value = rawValue as string | number | boolean;

      if (key === 'comision_servicio' && Number(value) < 0) {
        errors[key] = 'La comision no puede ser negativa.';
      }

      if (key === 'impuesto_porcentaje' && (Number(value) < 0 || Number(value) > 100)) {
        errors[key] = 'El impuesto debe estar entre 0 y 100.';
      }

      if (['max_boletos_orden', 'minutos_expiracion_orden', 'home_destacados_limite', 'eventos_por_pagina'].includes(key) && Number(value) <= 0) {
        errors[key] = 'El valor debe ser mayor a 0.';
      }

      if (key === 'moneda' && !/^[A-Z]{3,5}$/.test(String(value).trim())) {
        errors[key] = 'La moneda debe tener formato valido, por ejemplo MXN.';
      }

      if (key === 'url_publica' && String(value).trim() !== '' && !this.isValidUrl(String(value))) {
        errors[key] = 'La URL publica no es valida.';
      }

      if (['logo_principal', 'logo_oscuro', 'favicon'].includes(key) && String(value).trim() !== '' && !this.isValidUrlOrPath(String(value))) {
        errors[key] = 'Debes capturar una URL o ruta valida.';
      }
    }

    return errors;
  }

  private isValidUrl(value: string): boolean {
    try {
      const url = new URL(value);
      return ['http:', 'https:'].includes(url.protocol);
    } catch {
      return false;
    }
  }

  private isValidUrlOrPath(value: string): boolean {
    return this.isValidUrl(value) || /^\/?[A-Za-z0-9/_\.-]+$/.test(value);
  }

  private resolveApiError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      const apiMessage = error.error?.message;
      if (typeof apiMessage === 'string' && apiMessage.trim()) {
        return apiMessage;
      }
    }

    return 'No fue posible actualizar la configuracion.';
  }
}
