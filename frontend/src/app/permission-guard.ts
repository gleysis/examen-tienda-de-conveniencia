import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { inject } from '@angular/core';
import { of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';
import { AuthService } from './services/auth';
import { MENU_ITEMS } from './navigation';

function firstAllowedRoute(authService: AuthService, router: Router): UrlTree {
  const first = MENU_ITEMS.find((item) => authService.hasAnyPermission(item.permissions));
  return router.createUrlTree([first?.route ?? '/settings']);
}

export const permissionGuard: CanActivateFn = (route) => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (!authService.isAuthenticated()) {
    return router.createUrlTree(['/login']);
  }

  const required = route.data?.['permissions'] as string[] | undefined;
  if (!required?.length || authService.hasAnyPermission(required)) {
    return true;
  }

  // Sessions created before permissions were included in the stored user
  // have no permission data locally; refresh once from the API.
  if (!authService.hasPermissionsLoaded()) {
    return authService.getUser().pipe(
      map(() =>
        authService.hasAnyPermission(required) ? true : firstAllowedRoute(authService, router)
      ),
      catchError(() => of(firstAllowedRoute(authService, router)))
    );
  }

  return firstAllowedRoute(authService, router);
};
