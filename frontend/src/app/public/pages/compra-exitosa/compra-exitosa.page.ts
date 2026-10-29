import { DecimalPipe } from '@angular/common';
import { Component, computed, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { IonButton, IonContent } from '@ionic/angular/standalone';

import { AuthService } from '../../../core/services/auth.service';
import { CheckoutService } from '../../services/checkout.service';

@Component({
  selector: 'app-compra-exitosa',
  templateUrl: './compra-exitosa.page.html',
  styleUrls: ['./compra-exitosa.page.scss'],
  imports: [DecimalPipe, RouterLink, IonButton, IonContent],
})
export class CompraExitosaPage {
  private readonly authService = inject(AuthService);
  private readonly checkoutService = inject(CheckoutService);

  protected readonly isAuthenticated = computed(() => this.authService.isAuthenticated());
  protected readonly completedPurchase = this.checkoutService.getCompletedPurchase();
}
