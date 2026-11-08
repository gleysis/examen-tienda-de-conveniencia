import { Component, Input, Output, EventEmitter, inject, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { CommonModule, formatDate } from '@angular/common';
import { PreferencesService } from '../services/preferences';
import { I18nService } from '../services/i18n';
import { AuthService } from '../services/auth';
import { MENU_ITEMS } from '../navigation';
import { APP_PIPES } from '../pipes';

@Component({
  imports: [CommonModule, ...APP_PIPES],
  selector: 'app-layout',
  styleUrl: './layout.css',
  templateUrl: './layout.html',
})
export class Layout implements OnInit, OnDestroy {
  @Input() userName: string = '';
  @Input() pageTitle: string = '';
  @Input() pageSubtitle: string = '';
  @Output() logout = new EventEmitter<void>();

  private readonly pageSubtitles: Record<string, string> = {
    '/dashboard': 'Resumen general del negocio',
    '/pos': 'Registra ventas de forma rápida',
    '/sales-history': 'Consulta las ventas realizadas',
    '/products': 'Administra tu catálogo de productos',
    '/categories': 'Organiza tus productos por categoría',
    '/inventory': 'Controla el stock de tus productos',
    '/suppliers': 'Gestiona tus proveedores',
    '/returns': 'Gestiona las devoluciones de ventas',
    '/discounts': 'Administra promociones y descuentos',
    '/cash-registers': 'Controla la apertura y cierre de cajas',
    '/reports': 'Analiza el rendimiento del negocio',
    '/users': 'Administra los usuarios del sistema',
    '/roles': 'Define roles y permisos',
    '/settings': 'Personaliza el sistema',
    '/backups': 'Respalda y restaura tu información',
  };

  private readonly authService = inject(AuthService);

  currentRoute: string = '';
  user: any = this.authService.getCurrentUser();
  currentDate = '';
  currentTime = '';
  currentWeekday = '';
  private clockInterval: any;

  private readonly preferences = inject(PreferencesService);
  private readonly i18n = inject(I18nService);

  constructor(private router: Router) {
    this.currentRoute = this.router.url;
    this.router.events.subscribe(() => {
      this.currentRoute = this.router.url;
    });
  }

  ngOnInit(): void {
    this.updateClock();
    this.clockInterval = setInterval(() => this.updateClock(), 1000);
  }

  ngOnDestroy(): void {
    if (this.clockInterval) {
      clearInterval(this.clockInterval);
    }
  }

  get menuItems() {
    return MENU_ITEMS.filter((item) => !item.permissions.length || this.authService.hasAnyPermission(item.permissions));
  }

  private get currentMenuItem() {
    const path = this.currentRoute.split(/[?#]/)[0];
    return MENU_ITEMS.find((item) => path === item.route || path.startsWith(item.route + '/'));
  }

  get headerTitle(): string {
    return this.pageTitle || this.currentMenuItem?.label || '';
  }

  get headerSubtitle(): string {
    const route = this.currentMenuItem?.route;
    return this.pageSubtitle || (route ? this.pageSubtitles[route] : '') || '';
  }

  get headerIcon(): string {
    return this.currentMenuItem?.icon || 'home';
  }

  get firstName(): string {
    const name = this.user?.name || this.userName || '';
    return name.split(' ')[0];
  }

  get userInitial(): string {
    return (this.user?.name || this.userName || 'U').charAt(0).toUpperCase();
  }

  get userRole(): string {
    return this.user?.roles?.[0]?.name || 'Usuario';
  }

  private updateClock(): void {
    const now = new Date();
    const locale = this.i18n.locale;
    this.currentDate = formatDate(now, 'longDate', locale);
    this.currentWeekday = formatDate(now, 'EEEE', locale);
    this.currentTime = formatDate(now, this.preferences.timeFormat, locale);
  }

  navigate(route: string) {
    this.router.navigate([route]);
  }

  onLogout() {
    this.logout.emit();
    this.authService.logout().subscribe({
      next: () => {
        window.location.href = '/login';
      },
      error: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.location.href = '/login';
      },
    });
  }

  getIconPath(iconName: string): string {
    const icons: Record<string, string> = {
      home: 'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z',
      users: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 7a4 4 0 1 0 0-8 4 4 0 0 0 0 8z',
      box: 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z M3.27 6.96 12 12.01l8.73-5.05M12 22.08V12',
      archive: 'M21 8v13H3V8 M1 3h22v5H1V3zm9 8h6v2h-6v-2z',
      'chart-bar': 'M12 20V10 M18 20V4 M6 20v-6',
      'shopping-cart': 'M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z',
      truck: 'M1 3h15v13H1z M16 8h4l3 5v3h-7V8z M16 16a2 2 0 1 0 4 0 2 2 0 0 0-4 0z M6 16a2 2 0 1 0 4 0 2 2 0 0 0-4 0z',
      'arrow-rotate-left': 'M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8',
      tag: 'M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z M7 7h.01',
      'cash-register': 'M2 6a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6zm3 0v12 M10 6v12 M16 6v12 M22 6v12',
      cog: 'M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6',
      history: 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    };
    return icons[iconName] || icons['home'];
  }
}
