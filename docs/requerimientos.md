# Requerimientos del Sistema — POS Inventory System

Sistema de punto de venta e inventario para una tienda minorista. API REST en Laravel + frontend Angular (SPA).

## Roles del sistema

| Rol | Alcance |
|---|---|
| `admin` | Acceso total a todas las funcionalidades |
| `manager` | Todo excepto gestión de usuarios (crear/editar/eliminar) |
| `cashier` | POS, ventas, cajas, devoluciones, inventario (vista), reportes (vista) |
| `warehouse` | Inventario, productos, categorías, proveedores, reportes (vista) |

## Requerimientos funcionales

| # | RF | Descripción |
|---|---|---|
| RF-01 | Autenticación | Login/logout con tokens Sanctum; sesión única por token |
| RF-02 | Gestión de usuarios | CRUD de usuarios con asignación de roles |
| RF-03 | Gestión de roles | CRUD de roles y asignación de permisos por rol |
| RF-04 | Permisos | Autorización por permiso en cada endpoint (`can:<módulo>.<acción>`) |
| RF-05 | Productos | CRUD con SKU, código de barras, precio, costo, stock y stock mínimo |
| RF-06 | Categorías | CRUD de categorías de productos |
| RF-07 | Proveedores | CRUD con datos de contacto, términos de pago y saldo |
| RF-08 | Punto de venta | Carrito, búsqueda por nombre/SKU/código de barras, métodos de pago (efectivo/tarjeta/transferencia), cálculo de vuelto |
| RF-09 | Descuentos | CRUD de descuentos (porcentaje, monto fijo, compra X lleva Y, envío gratis) con vigencia, límites de uso y validación por código en el POS |
| RF-10 | Ticket de venta | Recibo imprimible con datos de la tienda (settings públicos), ítems, totales y cambio |
| RF-11 | Historial de ventas | Listado paginado con filtros y detalle maestro-detalle (venta + ítems) |
| RF-12 | Inventario | Movimientos de entrada/salida con stock anterior/nuevo, referencia y usuario responsable (auditoría) |
| RF-13 | Stock bajo | Reporte/alerta de productos con stock ≤ stock mínimo |
| RF-14 | Devoluciones | Registro y procesamiento de devoluciones con motivo y método de reembolso |
| RF-15 | Cajas registradoras | Apertura con balance inicial, cierre con arqueo (esperado vs contado → diferencia), historial |
| RF-16 | Dashboard | Métricas del día + 3 gráficos (ventas 7 días, top productos, ventas por método de pago) |
| RF-17 | Reportes parametrizados | Reportes filtrables por rango de fechas: ventas, movimientos, stock |
| RF-18 | Exportación | Exporte a Excel (.xlsx) y CSV de ventas, movimientos e inventario |
| RF-19 | Configuración | Parámetros del sistema por clave/grupo, con settings públicos (nombre de tienda, dirección, teléfono) |
| RF-20 | Respaldo de BD | Crear, listar, descargar, eliminar y restaurar copias de seguridad (.sql) desde la interfaz |

## Requerimientos no funcionales

| # | RNF | Descripción |
|---|---|---|
| RNF-01 | Seguridad | Contraseñas con bcrypt; autenticación por Bearer token; autorización por permisos en cada ruta |
| RNF-02 | Integridad | Ventas y devoluciones en transacciones de BD; descuento de stock atómico |
| RNF-03 | Rendimiento | Listados paginados (15/página por defecto); eager loading para evitar N+1 |
| RNF-04 | Validación | Form Requests en backend; mensajes de error por campo en el frontend |
| RNF-05 | Usabilidad | Interfaz en español; notificaciones toast globales; confirmaciones en acciones destructivas |
| RNF-06 | Mantenibilidad | API Resources para respuestas; código formateado con Pint; convenciones Laravel/Angular |
| RNF-07 | Portabilidad | MySQL en desarrollo; SQLite en memoria para tests |
| RNF-08 | Pruebas | Suite de feature tests (70+) cubriendo auth, permisos, ventas, cajas, exports y respaldos |

## Modelo de datos (tablas principales)

`users`, `roles`, `permissions`, `role_user`, `permission_role`, `categories`, `products`, `suppliers`, `sales`, `sale_items`, `inventory_movements`, `return_refunds`, `discounts`, `cash_registers`, `system_settings` (+ tablas del framework: `personal_access_tokens`, `cache`, `jobs`, `sessions`, `migrations`).
