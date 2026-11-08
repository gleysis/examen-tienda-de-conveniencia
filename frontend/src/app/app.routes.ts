import { Routes } from '@angular/router';
import { Login } from './login/login';
import { permissionGuard } from './permission-guard';

export const routes: Routes = [
  { path: '', redirectTo: '/login', pathMatch: 'full' },
  { path: 'login', component: Login },
  { path: 'dashboard', loadComponent: () => import('./dashboard/dashboard').then(m => m.Dashboard), canActivate: [permissionGuard], data: { permissions: ['reports.view'] } },
  { path: 'users', loadComponent: () => import('./users/users').then(m => m.Users), canActivate: [permissionGuard], data: { permissions: ['users.view'] } },
  { path: 'categories', loadComponent: () => import('./categories/categories').then(m => m.Categories), canActivate: [permissionGuard], data: { permissions: ['categories.view'] } },
  { path: 'products', loadComponent: () => import('./products/products').then(m => m.Products), canActivate: [permissionGuard], data: { permissions: ['products.view'] } },
  { path: 'pos', loadComponent: () => import('./pos/pos').then(m => m.Pos), canActivate: [permissionGuard], data: { permissions: ['sales.create'] } },
  { path: 'sales-history', loadComponent: () => import('./sales-history/sales-history').then(m => m.SalesHistory), canActivate: [permissionGuard], data: { permissions: ['sales.view'] } },
  { path: 'inventory', loadComponent: () => import('./inventory/inventory').then(m => m.Inventory), canActivate: [permissionGuard], data: { permissions: ['inventory.view'] } },
  { path: 'reports', loadComponent: () => import('./reports/reports').then(m => m.Reports), canActivate: [permissionGuard], data: { permissions: ['reports.view'] } },
  { path: 'suppliers', loadComponent: () => import('./suppliers/suppliers').then(m => m.Suppliers), canActivate: [permissionGuard], data: { permissions: ['suppliers.view'] } },
  { path: 'returns', loadComponent: () => import('./returns/returns').then(m => m.Returns), canActivate: [permissionGuard], data: { permissions: ['returns.view'] } },
  { path: 'roles', loadComponent: () => import('./roles/roles').then(m => m.Roles), canActivate: [permissionGuard], data: { permissions: ['users.view'] } },
  { path: 'backups', loadComponent: () => import('./backups/backups').then(m => m.Backups), canActivate: [permissionGuard], data: { permissions: ['backups.manage'] } },
  { path: 'discounts', loadComponent: () => import('./discounts/discounts').then(m => m.Discounts), canActivate: [permissionGuard], data: { permissions: ['discounts.view'] } },
  { path: 'cash-registers', loadComponent: () => import('./cash-registers/cash-registers').then(m => m.CashRegisters), canActivate: [permissionGuard], data: { permissions: ['cash-registers.view'] } },
  { path: 'settings', loadComponent: () => import('./settings/settings').then(m => m.Settings), canActivate: [permissionGuard] },
];
