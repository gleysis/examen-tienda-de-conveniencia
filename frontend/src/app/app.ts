import { Component, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterOutlet } from '@angular/router';
import { NotificationService } from './services/notification';

@Component({
  imports: [CommonModule, RouterOutlet],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
  protected readonly title = signal('frontend');
  private readonly notificationService = inject(NotificationService);

  readonly notifications = this.notificationService.notifications;

  dismiss(id: number): void {
    this.notificationService.dismiss(id);
  }
}
