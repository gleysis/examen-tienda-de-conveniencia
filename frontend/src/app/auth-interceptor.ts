import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
import { NotificationService } from './services/notification';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const notifications = inject(NotificationService);
  const token = localStorage.getItem('auth_token');

  if (token) {
    req = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
    });
  }

  return next(req).pipe(
    catchError((error: any) => {
      if (error.status === 401) {
        localStorage.removeItem('auth_token');
        router.navigate(['/login']);
      } else if (error.status === 403) {
        notifications.error(error.error?.message || 'No tienes permiso para realizar esta acción.');
      } else if (error.status === 0) {
        notifications.error('No se puede conectar con el servidor. Verifica tu conexión.');
      } else if (error.status >= 500) {
        notifications.error('Error del servidor. Intenta de nuevo más tarde.');
      }

      return throwError(() => error);
    })
  );
};
