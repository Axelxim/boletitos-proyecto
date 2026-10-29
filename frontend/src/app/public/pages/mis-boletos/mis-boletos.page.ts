import { DatePipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { IonButton, IonContent, IonSpinner, IonText } from '@ionic/angular/standalone';
import { catchError, of } from 'rxjs';
import jsPDF from 'jspdf';

import { AuthService } from '../../../core/services/auth.service';
import { OrdenConsulta, OrdenesService } from '../../services/ordenes.service';

@Component({
  selector: 'app-mis-boletos',
  templateUrl: './mis-boletos.page.html',
  styleUrls: ['./mis-boletos.page.scss'],
  imports: [DatePipe, FormsModule, IonButton, IonContent, IonSpinner, IonText],
})
export class MisBoletosPage {
  private readonly ordenesService = inject(OrdenesService);
  private readonly authService = inject(AuthService);

  protected readonly loading = signal(false);
  protected readonly loadingAutenticado = signal(false);
  protected readonly form = signal({
    folio: '',
    correo_comprador: '',
  });
  protected readonly errorMessage = signal<string | null>(null);
  protected readonly consulta = signal<OrdenConsulta | null>(null);
  protected readonly ordenesAutenticadas = signal<OrdenConsulta[]>([]);
  protected readonly selectedBoleto = signal<OrdenConsulta['boletos_generados'][number] | null>(null);
  protected readonly selectedOrden = signal<OrdenConsulta | null>(null);
  protected readonly ticketActionMessage = signal<string | null>(null);
  protected readonly isAuthenticated = signal(this.authService.isAuthenticated());

  constructor() {
    if (this.authService.isAuthenticated()) {
      this.consultarBoletosAutenticados();
    }
  }

  protected updateField(field: 'folio' | 'correo_comprador', value: string): void {
    this.form.set({
      ...this.form(),
      [field]: value,
    });
  }

  protected consultarBoletos(): void {
    const folio = this.form().folio.trim();
    const correo = this.form().correo_comprador.trim();

    if (!folio || !correo) {
      this.errorMessage.set('Debes capturar el folio de orden y el correo del comprador.');
      this.consulta.set(null);
      return;
    }

    this.loading.set(true);
    this.errorMessage.set(null);
    this.consulta.set(null);

    this.ordenesService.consultarMisBoletos({
      folio,
      correo_comprador: correo,
    }).pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.loading.set(false);
        return of(null);
      })
    ).subscribe((response) => {
      if (!response) {
        return;
      }

      this.consulta.set(response);
      this.loading.set(false);
    });
  }

  protected consultarBoletosAutenticados(): void {
    this.loadingAutenticado.set(true);
    this.errorMessage.set(null);

    this.ordenesService.obtenerMisBoletosAutenticado().pipe(
      catchError((error) => {
        this.errorMessage.set(this.resolveError(error));
        this.loadingAutenticado.set(false);
        return of(null);
      })
    ).subscribe((response) => {
      if (!response) {
        return;
      }

      this.ordenesAutenticadas.set(response);
      this.loadingAutenticado.set(false);
    });
  }

  protected verBoleto(boleto: OrdenConsulta['boletos_generados'][number], orden?: OrdenConsulta): void {
    this.ticketActionMessage.set(null);
    this.selectedOrden.set(orden || this.consulta());
    this.selectedBoleto.set(boleto);
  }

  protected cerrarBoleto(): void {
    this.ticketActionMessage.set(null);
    this.selectedOrden.set(null);
    this.selectedBoleto.set(null);
  }

  protected descargarPdfPlaceholder(): void {
    const boleto = this.selectedBoleto();
    const orden = this.selectedOrden() || this.consulta();

    if (!boleto || !orden) {
      this.ticketActionMessage.set('No fue posible generar el PDF del boleto.');
      return;
    }

    const evento = orden.evento?.titulo || boleto.evento?.titulo || 'Evento no disponible';
    const fecha = orden.evento?.fecha_inicio
      ? new Date(orden.evento.fecha_inicio).toLocaleString('es-MX', {
          year: 'numeric',
          month: 'short',
          day: 'numeric',
          hour: '2-digit',
          minute: '2-digit',
        })
      : 'No disponible';
    const sede = orden.evento?.sede?.nombre || orden.evento?.lugar || 'No disponible';
    const tipo = boleto.tipo_boleto?.nombre || 'No disponible';
    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: 'a4',
    });

    pdf.setFillColor(5, 5, 5);
    pdf.rect(0, 0, 210, 297, 'F');

    pdf.setDrawColor(201, 162, 39);
    pdf.setFillColor(14, 14, 14);
    pdf.roundedRect(18, 22, 174, 150, 8, 8, 'FD');

    pdf.setFillColor(201, 162, 39);
    pdf.rect(18, 22, 8, 150, 'F');

    pdf.setTextColor(244, 208, 111);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(10);
    pdf.text('BOLETITO DIGITAL', 34, 36);

    pdf.setTextColor(255, 247, 230);
    pdf.setFontSize(24);
    pdf.text('Boletitos', 34, 48);

    pdf.setFontSize(18);
    pdf.text(evento, 34, 62, { maxWidth: 104 });

    pdf.setTextColor(244, 208, 111);
    pdf.setFontSize(12);
    pdf.text(tipo, 34, 72, { maxWidth: 104 });

    this.drawLabelValue(pdf, 'Folio del boleto', boleto.folio, 34, 86, 88);
    this.drawLabelValue(pdf, 'Fecha', fecha, 34, 106, 88);
    this.drawLabelValue(pdf, 'Sede / lugar', sede, 34, 126, 88);
    this.drawLabelValue(pdf, 'Estado', boleto.estado, 34, 146, 88);

    pdf.setDrawColor(201, 162, 39);
    pdf.setFillColor(17, 17, 17);
    pdf.roundedRect(132, 40, 42, 42, 5, 5, 'FD');
    this.drawQrPlaceholder(pdf, 136, 44, 34);

    this.drawLabelValue(pdf, 'Codigo QR', boleto.codigo_qr, 132, 92, 42);
    this.drawLabelValue(pdf, 'Orden', orden.folio, 132, 124, 42);

    pdf.setTextColor(188, 174, 132);
    pdf.setFontSize(10);
    pdf.text('Boleto generado desde Boletitos.', 34, 186);

    pdf.save(`${boleto.folio}.pdf`);
    this.ticketActionMessage.set('PDF generado correctamente.');
  }

  protected compartirPlaceholder(): void {
    this.ticketActionMessage.set('La opcion para compartir el boleto quedara disponible en una siguiente etapa.');
  }

  private resolveError(error: unknown): string {
    if (error instanceof HttpErrorResponse) {
      return error.error?.message || 'No fue posible consultar tus boletos.';
    }

    return 'No fue posible consultar tus boletos.';
  }

  private drawLabelValue(pdf: jsPDF, label: string, value: string, x: number, y: number, maxWidth: number): void {
    pdf.setTextColor(215, 199, 154);
    pdf.setFont('helvetica', 'bold');
    pdf.setFontSize(9);
    pdf.text(label.toUpperCase(), x, y);

    pdf.setTextColor(255, 247, 230);
    pdf.setFont('helvetica', 'normal');
    pdf.setFontSize(11);
    const lines = pdf.splitTextToSize(value, maxWidth);
    pdf.text(lines, x, y + 7);
  }

  private drawQrPlaceholder(pdf: jsPDF, startX: number, startY: number, size: number): void {
    const cell = size / 7;
    const pattern = [
      [1, 1, 1, 0, 1, 1, 1],
      [1, 0, 1, 0, 1, 0, 1],
      [1, 1, 1, 0, 1, 1, 1],
      [0, 0, 0, 1, 0, 0, 0],
      [1, 1, 0, 1, 0, 1, 1],
      [1, 0, 0, 0, 0, 0, 1],
      [1, 1, 1, 0, 1, 1, 1],
    ];

    pdf.setFillColor(255, 247, 230);
    pdf.rect(startX, startY, size, size, 'F');
    pdf.setFillColor(17, 17, 17);

    pattern.forEach((row, rowIndex) => {
      row.forEach((value, colIndex) => {
        if (value === 1) {
          pdf.rect(startX + (colIndex * cell), startY + (rowIndex * cell), cell, cell, 'F');
        }
      });
    });
  }
}
