import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { SystemSettingService } from '../services/system-setting';

interface SystemSetting {
  id: number;
  key: string;
  value: string | null;
  type: string;
  group: string;
  description: string | null;
  is_public: boolean;
}

@Component({
  imports: [CommonModule, FormsModule],
  selector: 'app-settings',
  styleUrl: './settings.css',
  templateUrl: './settings.html',
})
export class Settings implements OnInit {
  settings: SystemSetting[] = [];
  isLoading = false;
  errorMessage = '';
  showForm = false;
  editingSetting: SystemSetting | null = null;

  settingForm = {
    key: '',
    value: '',
    type: 'string',
    group: 'general',
    description: '',
    is_public: false,
  };

  activeGroup = 'all';
  groups: string[] = ['general', 'sales', 'inventory', 'pos', 'taxes', 'receipts'];

  constructor(private systemSettingService: SystemSettingService) {}

  ngOnInit(): void {
    this.loadSettings();
  }

  loadSettings(): void {
    this.isLoading = true;
    const params: any = {};
    if (this.activeGroup !== 'all') params.group = this.activeGroup;

    this.systemSettingService.getSettings(params).subscribe({
      next: (response) => {
        this.settings = response.settings;
        this.isLoading = false;
      },
      error: (error) => {
        console.error('Error loading settings:', error);
        this.errorMessage = 'Error al cargar configuraciones';
        this.isLoading = false;
      },
    });
  }

  setGroup(group: string): void {
    this.activeGroup = group;
    this.loadSettings();
  }

  showAddForm(): void {
    this.editingSetting = null;
    this.settingForm = {
      key: '',
      value: '',
      type: 'string',
      group: this.activeGroup === 'all' ? 'general' : this.activeGroup,
      description: '',
      is_public: false,
    };
    this.showForm = true;
  }

  editSetting(setting: SystemSetting): void {
    this.editingSetting = setting;
    this.settingForm = {
      key: setting.key,
      value: setting.value || '',
      type: setting.type,
      group: setting.group,
      description: setting.description || '',
      is_public: setting.is_public,
    };
    this.showForm = true;
  }

  saveSetting(): void {
    if (this.editingSetting) {
      this.systemSettingService.updateSetting(this.editingSetting.id, this.settingForm).subscribe({
        next: (response) => {
          alert('Configuración actualizada exitosamente');
          this.showForm = false;
          this.loadSettings();
        },
        error: (error) => {
          console.error('Error updating setting:', error);
          this.errorMessage = error.error?.message || 'Error al actualizar configuración';
        },
      });
    } else {
      this.systemSettingService.createSetting(this.settingForm).subscribe({
        next: (response) => {
          alert('Configuración creada exitosamente');
          this.showForm = false;
          this.loadSettings();
        },
        error: (error) => {
          console.error('Error creating setting:', error);
          this.errorMessage = error.error?.message || 'Error al crear configuración';
        },
      });
    }
  }

  deleteSetting(setting: SystemSetting): void {
    if (confirm(`¿Está seguro de eliminar la configuración ${setting.key}?`)) {
      this.systemSettingService.deleteSetting(setting.id).subscribe({
        next: () => {
          alert('Configuración eliminada exitosamente');
          this.loadSettings();
        },
        error: (error) => {
          console.error('Error deleting setting:', error);
          this.errorMessage = error.error?.message || 'Error al eliminar configuración';
        },
      });
    }
  }

  cancelForm(): void {
    this.showForm = false;
    this.editingSetting = null;
  }

  getDisplayValue(setting: SystemSetting): string {
    if (setting.value === null) return '-';
    if (setting.type === 'boolean') {
      return setting.value === '1' || setting.value === 'true' ? 'Sí' : 'No';
    }
    if (setting.type === 'number' || setting.type === 'integer' || setting.type === 'float') {
      return setting.value;
    }
    return setting.value;
  }

  getTypeLabel(type: string): string {
    const labels: any = {
      string: 'Texto',
      integer: 'Entero',
      float: 'Decimal',
      boolean: 'Booleano',
      array: 'Arreglo',
      json: 'JSON',
    };
    return labels[type] || type;
  }
}
