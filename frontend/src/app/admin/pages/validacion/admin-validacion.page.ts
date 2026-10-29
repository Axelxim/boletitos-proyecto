import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, ElementRef, OnDestroy, ViewChild, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonSpinner, IonText } from '@ionic/angular/standalone';
import { BrowserQRCodeReader, IScannerControls } from '@zxing/browser';
import { catchError, of } from 'rxjs';

import { AdminValidacionService, ValidacionBoletoResponse } from '../../services/admin-validacion.service';

@Component({
  selector: 'app-admin-validacion',
  templateUrl: './admin-validacion.page.html',
  styleUrls: ['./admin-validacion.page.scss'],
  imports: [DatePipe, FormsModule, IonButton, IonSpinner, IonText],
})
export class AdminValidacionPage implements OnDestroy {
  private readonly adminValidacionService = inject(AdminValidacionService);
  @ViewChild('scannerVideo') private scannerVideo?: ElementRef<HTMLVideoElement>;
  private mediaStream: MediaStream | null = null;
  private readonly qrReader = new BrowserQRCodeReader();
  private scannerControls: IScannerControls | null = null;
  private scannerRunning = false;

  protected readonly codigoQr = signal('');
  protected readonly loading = signal(false);
  protected readonly cameraActive = signal(false);
  protected readonly cameraSupported = signal(false);
  protected readonly cameraError = signal<string | null>(null);
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly resultado = signal<{
    tone: 'success' | 'warning' | 'danger';
    title: string;
    message: string;
    data: ValidacionBoletoResponse;
  } | null>(null);

  public constructor() {
    this.cameraSupported.set(this.isCameraSupported());
  }

  public ngOnDestroy(): void {
    this.stopCamera();
  }

  protected validarBoleto(): void {
    const codigo = this.codigoQr().trim();

    if (!codigo) {
      this.errorMessage.set('Debes escribir o pegar un codigo para validar.');
      this.resultado.set(null);
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);
    this.resultado.set(null);

    this.adminValidacionService.validarCodigoQr(codigo).pipe(
      catchError((error) => {
        const fallback = this.resolveError(error);
        this.resultado.set(fallback);
        this.loading.set(false);
        return of(null);
      })
    ).subscribe((response) => {
      if (!response) {
        return;
      }

      const tone = this.mapTone(response.data.resultado);
      const title = this.mapTitle(response.data.resultado);

      this.resultado.set({
        tone,
        title,
        message: response.message,
        data: response.data,
      });
      this.loading.set(false);
    });
  }

  protected validarOtro(): void {
    this.stopCamera();
    this.codigoQr.set('');
    this.errorMessage.set(null);
    this.cameraError.set(null);
    this.resultado.set(null);
  }

  protected async activarCamara(): Promise<void> {
    if (!this.cameraSupported()) {
      this.cameraError.set('Este navegador no soporta acceso a camara para escaneo QR.');
      return;
    }

    this.stopCamera();
    this.cameraError.set(null);

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: {
            ideal: 'environment',
          },
        },
        audio: false,
      });

      this.mediaStream = stream;
      this.cameraActive.set(true);

      const video = this.scannerVideo?.nativeElement;

      if (!video) {
        this.cameraError.set('No fue posible inicializar la vista previa de la camara.');
        this.stopCamera();
        return;
      }

      video.srcObject = stream;
      await video.play();
      await this.startScanning(video);
    } catch {
      this.cameraError.set('No fue posible acceder a la camara del dispositivo.');
      this.stopCamera();
    }
  }

  protected desactivarCamara(): void {
    this.stopCamera();
  }

  private mapTone(resultado: string): 'success' | 'warning' | 'danger' {
    switch (resultado) {
      case 'valido':
        return 'success';
      case 'usado':
        return 'warning';
      default:
        return 'danger';
    }
  }

  private mapTitle(resultado: string): string {
    switch (resultado) {
      case 'valido':
        return 'Boleto valido';
      case 'usado':
        return 'Boleto ya usado';
      case 'cancelado':
        return 'Boleto cancelado';
      default:
        return 'Boleto invalido';
    }
  }

  private resolveError(error: unknown): {
    tone: 'success' | 'warning' | 'danger';
    title: string;
    message: string;
    data: ValidacionBoletoResponse;
  } {
    if (error instanceof HttpErrorResponse) {
      const apiError = error.error as {
        message?: string;
        data?: ValidacionBoletoResponse;
        errors?: ValidacionBoletoResponse;
      } | null;

      const data = apiError?.data ?? apiError?.errors ?? { resultado: 'invalido' as const };
      const resultado = data.resultado ?? 'invalido';

      return {
        tone: this.mapTone(resultado),
        title: this.mapTitle(resultado),
        message: apiError?.message?.trim() || 'No fue posible validar el boleto.',
        data,
      };
    }

    return {
      tone: 'danger',
      title: 'Error de validacion',
      message: 'No fue posible validar el boleto.',
      data: { resultado: 'invalido' },
    };
  }

  private isCameraSupported(): boolean {
    return typeof window !== 'undefined'
      && !!navigator.mediaDevices?.getUserMedia;
  }

  private async startScanning(video: HTMLVideoElement): Promise<void> {
    if (this.scannerRunning) {
      return;
    }

    this.scannerRunning = true;

    this.scannerControls = await this.qrReader.decodeFromVideoElement(video, (result, error, controls) => {
      this.scannerControls = controls;

      if (!this.cameraActive() || this.loading()) {
        return;
      }

      if (result?.getText()?.trim()) {
        this.codigoQr.set(result.getText().trim());
        this.stopCamera();
        this.validarBoleto();
        return;
      }

      if (error && !this.cameraError()) {
        this.cameraError.set('Escaneando QR... apunta la camara al codigo del boleto.');
      }
    });
  }

  private stopCamera(): void {
    if (this.scannerControls) {
      this.scannerControls.stop();
      this.scannerControls = null;
    }

    this.scannerRunning = false;

    this.cameraActive.set(false);

    if (this.scannerVideo?.nativeElement) {
      this.scannerVideo.nativeElement.pause();
      this.scannerVideo.nativeElement.srcObject = null;
    }

    if (this.mediaStream) {
      this.mediaStream.getTracks().forEach((track) => track.stop());
      this.mediaStream = null;
    }
  }
}
