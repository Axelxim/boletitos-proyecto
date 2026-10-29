import { Injectable } from '@angular/core';

import { EventoListado, TipoBoletoDisponible } from './eventos.service';
import { OrdenCreada } from './ordenes.service';

export interface CheckoutItem {
  tipo_boleto_id: number;
  nombre: string;
  descripcion?: string;
  precio: number;
  cantidad: number;
  subtotal: number;
  cantidad_disponible: number;
}

export interface CheckoutSelection {
  evento: EventoListado;
  items: CheckoutItem[];
  subtotal: number;
  comision: number;
  total: number;
  orden?: OrdenCreada | null;
}

export interface CheckoutCompletedPurchase {
  folio: string;
  estado: string;
  total: number;
  referenciaPago: string;
  cantidadBoletos: number;
  correoEnviado: boolean;
}

@Injectable({
  providedIn: 'root',
})
export class CheckoutService {
  private readonly storageKey = 'boletitos_checkout_selection';
  private readonly returnUrlKey = 'boletitos_checkout_return_url';
  private readonly completedPurchaseKey = 'boletitos_completed_purchase';

  saveSelection(evento: EventoListado, tipos: TipoBoletoDisponible[], cantidades: Record<number, number>): void {
    const items = tipos
      .map((tipo) => {
        const cantidad = Math.max(0, Number(cantidades[tipo.id] ?? 0));

        return {
          tipo_boleto_id: tipo.id,
          nombre: tipo.nombre,
          descripcion: tipo.descripcion,
          precio: Number(tipo.precio),
          cantidad,
          subtotal: Number((cantidad * Number(tipo.precio)).toFixed(2)),
          cantidad_disponible: Number(tipo.cantidad_disponible),
        } satisfies CheckoutItem;
      })
      .filter((item) => item.cantidad > 0);

    const subtotal = Number(items.reduce((acc, item) => acc + item.subtotal, 0).toFixed(2));
    const comision = 0;
    const total = Number((subtotal + comision).toFixed(2));

    const payload: CheckoutSelection = {
      evento,
      items,
      subtotal,
      comision,
      total,
      orden: null,
    };

    localStorage.setItem(this.storageKey, JSON.stringify(payload));
  }

  getSelection(): CheckoutSelection | null {
    const raw = localStorage.getItem(this.storageKey);

    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw) as CheckoutSelection;
    } catch {
      return null;
    }
  }

  clearSelection(): void {
    localStorage.removeItem(this.storageKey);
  }

  saveOrder(orden: OrdenCreada): void {
    const selection = this.getSelection();

    if (!selection) {
      return;
    }

    localStorage.setItem(this.storageKey, JSON.stringify({
      ...selection,
      orden,
    } satisfies CheckoutSelection));
  }

  saveReturnUrl(url: string): void {
    localStorage.setItem(this.returnUrlKey, url);
  }

  getReturnUrl(): string | null {
    return localStorage.getItem(this.returnUrlKey);
  }

  clearReturnUrl(): void {
    localStorage.removeItem(this.returnUrlKey);
  }

  saveCompletedPurchase(summary: CheckoutCompletedPurchase): void {
    localStorage.setItem(this.completedPurchaseKey, JSON.stringify(summary));
  }

  getCompletedPurchase(): CheckoutCompletedPurchase | null {
    const raw = localStorage.getItem(this.completedPurchaseKey);

    if (!raw) {
      return null;
    }

    try {
      return JSON.parse(raw) as CheckoutCompletedPurchase;
    } catch {
      return null;
    }
  }

  clearCompletedPurchase(): void {
    localStorage.removeItem(this.completedPurchaseKey);
  }
}
