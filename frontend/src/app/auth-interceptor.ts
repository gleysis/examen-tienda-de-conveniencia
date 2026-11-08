import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';
import { NotificationService } from './services/notification';
import { tr } from './services/i18n';

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
      const isLoginRequest = req.url.endsWith('/login');

      if (error.status === 401) {
        if (localStorage.getItem('auth_token')) {
          notifications.error(tr('Tu sesión ha expirado. Inicia sesión nuevamente.'));
        }
        localStorage.removeItem('auth_token');
        router.navigate(['/login']);
      } else if (error.status === 403) {
        notifications.error(error.error?.message || tr('No tienes permiso para realizar esta acción.'));
      } else if (error.status === 429 && !isLoginRequest) {
        notifications.error(tr('Demasiadas solicitudes. Espera un momento e intenta de nuevo.'));
      } else if (error.status === 0) {
        notifications.error(tr('No se puede conectar con el servidor. Verifica tu conexión a internet.'));
      } else if (error.status >= 500) {
        notifications.error(tr('Ocurrió un error inesperado en el servidor. Intenta de nuevo más tarde.'));
      }

      return throwError(() => error);
    })
  );
};
