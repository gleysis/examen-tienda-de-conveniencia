import { Injectable, signal } from '@angular/core';

export type NotificationType = 'success' | 'error' | 'info';

export interface Notification {
  id: number;
  message: string;
  type: NotificationType;
}

@Injectable({
  providedIn: 'root',
})
export class NotificationService {
  private nextId = 0;
  readonly notifications = signal<Notification[]>([]);

  show(message: string, type: NotificationType = 'info', durationMs = 4000): void {
    const notification: Notification = { id: ++this.nextId, message, type };
    this.notifications.update((list) => [...list, notification]);

    setTimeout(() => this.dismiss(notification.id), durationMs);
  }

  success(message: string): void {
    this.show(message, 'success');
  }

  error(message: string): void {
    this.show(message, 'error', 6000);
  }

  dismiss(id: number): void {
    this.notifications.update((list) => list.filter((n) => n.id !== id));
  }
}
