import { Routes } from '@angular/router';
import { Login } from './login/login';
import { authGuard } from './auth-guard';

export const routes: Routes = [
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: 'login', component: Login },
  { path: 'dashboard', loadComponent: () => import('./dashboard/dashboard').then(m => m.Dashboard), canActivate: [authGuard] },
  { path: 'users', loadComponent: () => import('./users/users').then(m => m.Users), canActivate: [authGuard] },
  { path: 'categories', loadComponent: () => import('./categories/categories').then(m => m.Categories), canActivate: [authGuard] },
  { path: 'products', loadComponent: () => import('./products/products').then(m => m.Products), canActivate: [authGuard] },
  { path: 'pos', loadComponent: () => import('./pos/pos').then(m => m.Pos), canActivate: [authGuard] },
  { path: 'sales-history', loadComponent: () => import('./sales-history/sales-history').then(m => m.SalesHistory), canActivate: [authGuard] },
  { path: 'inventory', loadComponent: () => import('./inventory/inventory').then(m => m.Inventory), canActivate: [authGuard] },
  { path: 'reports', loadComponent: () => import('./reports/reports').then(m => m.Reports), canActivate: [authGuard] },
  { path: 'suppliers', loadComponent: () => import('./suppliers/suppliers').then(m => m.Suppliers), canActivate: [authGuard] },
  { path: 'returns', loadComponent: () => import('./returns/returns').then(m => m.Returns), canActivate: [authGuard] },
  { path: 'roles', loadComponent: () => import('./roles/roles').then(m => m.Roles), canActivate: [authGuard] },
  { path: 'backups', loadComponent: () => import('./backups/backups').then(m => m.Backups), canActivate: [authGuard] },
  { path: 'discounts', loadComponent: () => import('./discounts/discounts').then(m => m.Discounts), canActivate: [authGuard] },
  { path: 'cash-registers', loadComponent: () => import('./cash-registers/cash-registers').then(m => m.CashRegisters), canActivate: [authGuard] },
  { path: 'settings', loadComponent: () => import('./settings/settings').then(m => m.Settings), canActivate: [authGuard] },
];
