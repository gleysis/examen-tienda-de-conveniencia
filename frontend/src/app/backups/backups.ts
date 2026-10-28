import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BackupService, BackupFile } from '../services/backup';
import { NotificationService } from '../services/notification';
import { Layout } from '../layout/layout';
import { AuthService } from '../services/auth';

@Component({
  imports: [CommonModule, Layout],
  selector: 'app-backups',
  styleUrl: './backups.css',
  templateUrl: './backups.html',
})
export class Backups implements OnInit {
  backups: BackupFile[] = [];
  isLoading = false;
  isCreating = false;
  isRestoring = false;
  errorMessage = '';
  user: any;
  selectedFile: File | null = null;

  constructor(
    private backupService: BackupService,
    private notificationService: NotificationService,
    private authService: AuthService
  ) {
    this.user = this.authService.getCurrentUser();
  }

  ngOnInit(): void {
    this.loadBackups();
  }

  loadBackups(): void {
    this.isLoading = true;
    this.backupService.getBackups().subscribe({
      next: (response) => {
        this.backups = response.backups || [];
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading backups:', error);
        this.errorMessage = error.error?.message || 'Error al cargar respaldos';
        this.isLoading = false;
      },
    });
  }

  createBackup(): void {
    this.isCreating = true;
    this.errorMessage = '';
    this.backupService.createBackup().subscribe({
      next: (response) => {
        this.isCreating = false;
        this.notificationService.success(response.message || 'Respaldo creado');
        this.loadBackups();
      },
      error: (error) => {
        this.isCreating = false;
        this.errorMessage = error.error?.message || 'Error al crear el respaldo';
      },
    });
  }

  download(backup: BackupFile): void {
    this.backupService.downloadBackup(backup.filename).subscribe({
      next: (blob) => {
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = backup.filename;
        link.click();
        window.URL.revokeObjectURL(url);
      },
      error: (error) => {
        console.error('Error downloading backup:', error);
        this.errorMessage = 'Error al descargar el respaldo';
      },
    });
  }

  delete(backup: BackupFile): void {
    if (!confirm(`¿Eliminar el respaldo "${backup.filename}"?`)) {
      return;
    }

    this.backupService.deleteBackup(backup.filename).subscribe({
      next: () => this.loadBackups(),
      error: (error) => {
        this.errorMessage = error.error?.message || 'Error al eliminar el respaldo';
      },
    });
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.selectedFile = input.files?.[0] || null;
  }

  restore(): void {
    if (!this.selectedFile) {
      return;
    }

    if (
      !confirm(
        '¿Restaurar la base de datos desde este archivo? Los datos actuales serán reemplazados.'
      )
    ) {
      return;
    }

    this.isRestoring = true;
    this.errorMessage = '';
    this.backupService.restoreBackup(this.selectedFile).subscribe({
      next: (response) => {
        this.isRestoring = false;
        this.selectedFile = null;
        this.notificationService.success(response.message || 'Base de datos restaurada');
      },
      error: (error) => {
        this.isRestoring = false;
        this.errorMessage = error.error?.message || 'Error al restaurar la base de datos';
      },
    });
  }

  formatSize(bytes: number): string {
    if (bytes >= 1048576) {
      return (bytes / 1048576).toFixed(2) + ' MB';
    }
    return (bytes / 1024).toFixed(1) + ' KB';
  }
}
