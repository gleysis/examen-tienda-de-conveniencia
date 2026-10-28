# POS Inventory System

Sistema de punto de venta e inventario: API REST en **Laravel** (PHP 8.5, Sanctum) + frontend **Angular** (standalone components).

## Estructura

```
backend/    API Laravel (auth, roles/permisos, ventas, inventario, cajas, reportes)
frontend/   SPA Angular (POS, productos, inventario, ventas, reportes, settings)
```

## Requisitos

- PHP 8.5 + Composer
- Node.js + npm
- MySQL (desarrollo local); SQLite en memoria para tests

## Backend

```bash
cd backend
composer install
cp .env.example .env       # ajustar DB_* si es necesario (default: mysql / tienda_db)
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://127.0.0.1:8000
```

Credenciales del seeder (admin): `admin@pos.com` / `password`

Tests:

```bash
php artisan test           # PHPUnit + SQLite en memoria
vendor/bin/pint            # estilo de código
```

## Frontend

```bash
cd frontend
npm install
ng serve                   # http://localhost:4200 (API en 127.0.0.1:8000)
npm run build
```

## Notas de la API

- Autenticación por token Sanctum (`Authorization: Bearer <token>`).
- Autorización por permisos (`can:products.view`, etc.) sobre roles.
- Los listados principales responden paginados: `{ data, meta: { current_page, last_page, per_page, total, from, to } }`.
- Exportes CSV en `/api/reports/{sales,inventory-movements,inventory-stock}/export`.
- Settings públicos (`store_name`, `store_address`, `store_phone`) alimentan el encabezado del ticket del POS.
