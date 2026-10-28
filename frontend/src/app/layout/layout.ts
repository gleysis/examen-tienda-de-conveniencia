import { Component, Input, Output, EventEmitter } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { CommonModule } from '@angular/common';

@Component({
  imports: [CommonModule],
  selector: 'app-layout',
  styleUrl: './layout.css',
  templateUrl: './layout.html',
})
export class Layout {
  @Input() userRole: 'admin' | 'seller' | 'warehouse' = 'admin';
  @Input() userName: string = '';
  @Output() logout = new EventEmitter<void>();

  sidebarCollapsed = false;
  currentRoute: string = '';

  constructor(private router: Router, private route: ActivatedRoute) {
    this.router.events.subscribe(() => {
      this.currentRoute = this.router.url;
    });
  }

  adminMenuItems = [
    { icon: 'home', label: 'Inicio', route: '/dashboard' },
    { icon: 'users', label: 'Usuarios', route: '/users' },
    { icon: 'users', label: 'Roles', route: '/roles' },
    { icon: 'box', label: 'Productos', route: '/products' },
    { icon: 'archive', label: 'Inventario', route: '/inventory' },
    { icon: 'chart-bar', label: 'Reportes', route: '/reports' },
    { icon: 'shopping-cart', label: 'Ventas', route: '/pos' },
    { icon: 'truck', label: 'Proveedores', route: '/suppliers' },
    { icon: 'arrow-rotate-left', label: 'Devoluciones', route: '/returns' },
    { icon: 'tag', label: 'Descuentos', route: '/discounts' },
    { icon: 'cash-register', label: 'Cajas', route: '/cash-registers' },
    { icon: 'cog', label: 'Configuración', route: '/settings' },
    { icon: 'archive', label: 'Respaldos', route: '/backups' },
  ];

  sellerMenuItems = [
    { icon: 'home', label: 'Inicio', route: '/dashboard' },
    { icon: 'shopping-cart', label: 'Ventas', route: '/pos' },
    { icon: 'history', label: 'Historial de Ventas', route: '/sales-history' },
    { icon: 'box', label: 'Productos', route: '/products' },
    { icon: 'chart-bar', label: 'Reportes básicos', route: '/reports' },
  ];

  warehouseMenuItems = [
    { icon: 'home', label: 'Inicio', route: '/dashboard' },
    { icon: 'archive', label: 'Inventario', route: '/inventory' },
    { icon: 'box', label: 'Productos', route: '/products' },
    { icon: 'truck', label: 'Proveedores', route: '/suppliers' },
  ];

  get menuItems() {
    switch (this.userRole) {
      case 'seller':
        return this.sellerMenuItems;
      case 'warehouse':
        return this.warehouseMenuItems;
      default:
        return this.adminMenuItems;
    }
  }

  navigate(route: string) {
    this.router.navigate([route]);
  }

  onLogout() {
    this.logout.emit();
  }

  toggleSidebar() {
    this.sidebarCollapsed = !this.sidebarCollapsed;
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
      cog: 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z',
      history: 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
    };
    return icons[iconName] || icons['home'];
  }

  getPageTitle(): string {
    const routeTitles: Record<string, string> = {
      '/dashboard': 'Panel de Administrador',
      '/users': 'Usuarios',
      '/roles': 'Roles y Permisos',
      '/products': 'Gestión de Productos',
      '/inventory': 'Inventario',
      '/reports': 'Reportes',
      '/pos': 'Área de Venta',
      '/suppliers': 'Proveedores',
      '/returns': 'Devoluciones',
      '/discounts': 'Descuentos',
      '/cash-registers': 'Cajas',
      '/backups': 'Respaldos',
      '/settings': 'Configuración',
    };
    return routeTitles[this.currentRoute] || 'Tienda Express';
  }
}
