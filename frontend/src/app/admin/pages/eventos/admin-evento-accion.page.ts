import { Component, computed, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { IonButton, IonContent } from '@ionic/angular/standalone';

@Component({
  selector: 'app-admin-evento-accion',
  templateUrl: './admin-evento-accion.page.html',
  styleUrls: ['./admin-evento-accion.page.scss'],
  imports: [RouterLink, IonButton, IonContent],
})
export class AdminEventoAccionPage {
  private readonly route = inject(ActivatedRoute);

  protected readonly eventoId = computed(() => this.route.snapshot.paramMap.get('id'));
  protected readonly actionLabel = computed(() => this.route.snapshot.data['actionLabel'] as string ?? 'Gestion');
  protected readonly pageTitle = computed(() => this.route.snapshot.data['pageTitle'] as string ?? 'Modulo en preparacion');
}
