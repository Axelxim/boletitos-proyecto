import { Injectable } from '@angular/core';

declare global {
  interface Window {
    paypal?: any;
  }
}

export interface PaypalPublicConfig {
  client_id: string;
  mode: string;
  currency: string;
  enabled: boolean;
}

@Injectable({
  providedIn: 'root',
})
export class PaypalService {
  private readonly scriptId = 'boletitos-paypal-sdk';

  async loadSdk(config: PaypalPublicConfig): Promise<any> {
    if (!config.enabled || !config.client_id) {
      throw new Error('PayPal no esta configurado para el entorno actual.');
    }

    if (window.paypal) {
      return window.paypal;
    }

    const existing = document.getElementById(this.scriptId) as HTMLScriptElement | null;

    if (existing) {
      await this.waitForPaypal();
      return window.paypal;
    }

    const script = document.createElement('script');
    script.id = this.scriptId;
    script.src = `https://www.paypal.com/sdk/js?client-id=${encodeURIComponent(config.client_id)}&currency=${encodeURIComponent(config.currency)}&intent=capture&components=buttons`;
    script.async = true;

    const promise = new Promise<void>((resolve, reject) => {
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('No fue posible cargar el SDK de PayPal.'));
    });

    document.body.appendChild(script);
    await promise;
    await this.waitForPaypal();

    return window.paypal;
  }

  private waitForPaypal(): Promise<void> {
    return new Promise((resolve, reject) => {
      const startedAt = Date.now();

      const interval = window.setInterval(() => {
        if (window.paypal) {
          window.clearInterval(interval);
          resolve();
          return;
        }

        if (Date.now() - startedAt > 10000) {
          window.clearInterval(interval);
          reject(new Error('PayPal no estuvo disponible despues de cargar el SDK.'));
        }
      }, 100);
    });
  }
}
