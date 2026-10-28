# POS Inventory Management System - Setup Guide

## Prerequisites

Before installing the project, you need to install the following software on your system:

### 1. PHP (Required for Laravel)
- **Version**: PHP 8.1 or higher
- **Download**: https://windows.php.net/download/
- **Installation**:
  - Download the ZIP package
  - Extract to `C:\php`
  - Add `C:\php` to your system PATH
  - Restart your terminal/command prompt
  - Verify: `php --version`

### 2. Composer (Required for Laravel)
- **Download**: https://getcomposer.org/download/
- **Installation**:
  - Download Composer-Setup.exe
  - Run the installer
  - It will automatically detect PHP and configure itself
  - Verify: `composer --version`

### 3. MySQL (Required for Database)
- **Download**: https://dev.mysql.com/downloads/mysql/
- **Installation**:
  - Download MySQL Installer for Windows
  - Run the installer
  - Choose "Developer Default" setup
  - Set root password (remember it!)
  - Verify: `mysql --version`

### 4. Node.js & npm (Required for Angular)
- **Download**: https://nodejs.org/
- **Installation**:
  - Download LTS version
  - Run the installer
  - Verify: `node --version` and `npm --version`

### 5. Angular CLI (Required for Frontend)
- After installing Node.js, run:
  ```bash
  npm install -g @angular/cli
  ```
- Verify: `ng version`

## Installation Steps

### After installing all prerequisites:

1. **Navigate to project directory**:
   ```bash
   cd C:\Users\Gleysis Leiva\CascadeProjects\pos-inventory-system
   ```

2. **Install Laravel Backend**:
   ```bash
   composer create-project laravel/laravel backend
   ```

3. **Install Angular Frontend**:
   ```bash
   ng new frontend
   ```

4. **Configure MySQL Database**:
   - Create database: `tienda_db`
   - Update backend `.env` file with your MySQL credentials

## Current Status

✅ **Installation Complete**

- ✅ PHP 8.1+ installed and configured
- ✅ Composer installed
- ✅ MySQL installed and configured
- ✅ Node.js & npm installed
- ✅ Angular CLI installed
- ✅ Laravel backend installed
- ✅ Angular frontend installed
- ✅ MySQL database 'tienda_db' created
- ✅ Laravel migrations executed

## Next Steps

The project is now ready for development. The following structure has been created:

- `backend/` - Laravel API backend
- `frontend/` - Angular frontend application

To start development:

1. **Start Laravel backend**:
   ```bash
   cd backend
   php artisan serve
   ```

2. **Start Angular frontend** (in a new terminal):
   ```bash
   cd frontend
   ng serve
   ```

The backend will run on http://localhost:8000 and the frontend on http://localhost:4200
