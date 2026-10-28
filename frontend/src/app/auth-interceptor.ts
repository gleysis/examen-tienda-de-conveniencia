import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError } from 'rxjs/operators';
import { throwError } from 'rxjs';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const token = localStorage.getItem('auth_token');

  console.log('Interceptor - Request URL:', req.url);
  console.log('Interceptor - Token exists:', !!token);
  console.log('Interceptor - Token value:', token ? token.substring(0, 20) + '...' : 'none');

  if (token) {
    req = req.clone({
      setHeaders: {
        Authorization: `Bearer ${token}`,
      },
    });
    console.log('Interceptor - Authorization header added');
  }

  return next(req).pipe(
    catchError((error: any) => {
      console.error('Interceptor - Error:', error);
      console.error('Interceptor - Error status:', error.status);
      console.error('Interceptor - Error message:', error.message);

      if (error.status === 401) {
        console.log('Unauthorized - redirecting to login');
        localStorage.removeItem('auth_token');
        router.navigate(['/login']);
      } else if (error.status === 403) {
        console.log('Forbidden - access denied');
      } else if (error.status === 500) {
        console.log('Server error');
      }

      return throwError(() => error);
    })
  );
};
