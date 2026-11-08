export interface MenuItem {
  icon: string;
  label: string;
  route: string;
  permissions: string[];
}

export const MENU_ITEMS: MenuItem[] = [
  { icon: 'home', label: 'Inicio', route: '/dashboard', permissions: ['reports.view'] },
  { icon: 'shopping-cart', label: 'Punto de Venta', route: '/pos', permissions: ['sales.create'] },
  { icon: 'history', label: 'Historial de Ventas', route: '/sales-history', permissions: ['sales.view'] },
  { icon: 'box', label: 'Productos', route: '/products', permissions: ['products.view'] },
  { icon: 'tag', label: 'Categorías', route: '/categories', permissions: ['categories.view'] },
  { icon: 'archive', label: 'Inventario', route: '/inventory', permissions: ['inventory.view'] },
  { icon: 'truck', label: 'Proveedores', route: '/suppliers', permissions: ['suppliers.view'] },
  { icon: 'arrow-rotate-left', label: 'Devoluciones', route: '/returns', permissions: ['returns.view'] },
  { icon: 'tag', label: 'Descuentos', route: '/discounts', permissions: ['discounts.view'] },
  { icon: 'cash-register', label: 'Cajas', route: '/cash-registers', permissions: ['cash-registers.view'] },
  { icon: 'chart-bar', label: 'Reportes', route: '/reports', permissions: ['reports.view'] },
  { icon: 'users', label: 'Usuarios', route: '/users', permissions: ['users.view'] },
  { icon: 'users', label: 'Roles', route: '/roles', permissions: ['users.view'] },
  { icon: 'cog', label: 'Configuración', route: '/settings', permissions: [] },
  { icon: 'archive', label: 'Respaldos', route: '/backups', permissions: ['backups.manage'] },
];
