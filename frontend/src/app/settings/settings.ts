import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import {
  SystemSettingService,
  SettingGroup,
  SettingField,
  SettingChange,
} from '../services/system-setting';
import { AuthService } from '../services/auth';
import { NotificationService } from '../services/notification';
import { PreferencesService } from '../services/preferences';
import { I18nService } from '../services/i18n';
import { PaginationMeta } from '../services/pagination';
import { Layout } from '../layout/layout';
import { APP_PIPES } from '../pipes';
import { catchError, forkJoin, of } from 'rxjs';

const PERSONAL_GROUP = 'personal';
const SECURITY_GROUP = 'security';

@Component({
  imports: [CommonModule, FormsModule, Layout, ...APP_PIPES],
  selector: 'app-settings',
  styleUrl: './settings.css',
  templateUrl: './settings.html',
})
export class Settings implements OnInit {
  groups: SettingGroup[] = [];
  activeGroup = '';
  isLoading = false;
  isSaving = false;
  isUploadingLogo = false;
  errorMessage = '';
  readonly canViewHistory: boolean;
  /** Whether the user can open any system-wide section besides their own preferences. */
  readonly hasSystemAccess: boolean;

  /** Values as last loaded/saved, used to detect unsaved changes per group. */
  private savedSnapshot: Record<string, string> = {};
  private personalGroup: SettingGroup | null = null;
  private systemGroups: SettingGroup[] = [];

  showHistory = false;
  historyLoading = false;
  history: SettingChange[] = [];
  historyMeta: PaginationMeta | null = null;

  user: any;

  readonly securityGroup = SECURITY_GROUP;
  currentPassword = '';
  newPassword = '';
  passwordConfirmation = '';
  isSavingPassword = false;

  constructor(
    private systemSettingService: SystemSettingService,
    private authService: AuthService,
    private notificationService: NotificationService,
    private preferencesService: PreferencesService,
    private i18n: I18nService,
    private router: Router,
    private cdr: ChangeDetectorRef
  ) {
    this.user = this.authService.getCurrentUser();
    this.canViewHistory = this.authService.hasPermission('settings.view');
    this.hasSystemAccess = this.authService.hasAnyPermission([
      'settings.view',
      'settings.edit',
      'settings.pos.edit',
      'settings.inventory.edit',
    ]);
  }

  ngOnInit(): void {
    this.loadSchema();
  }

  get canEdit(): boolean {
    return !!this.currentGroup?.can_edit;
  }

  loadSchema(): void {
    this.isLoading = true;
    const schema$ = this.hasSystemAccess
      ? this.systemSettingService.getSchema().pipe(catchError(() => of({ groups: [] as SettingGroup[] })))
      : of({ groups: [] as SettingGroup[] });

    forkJoin([this.systemSettingService.getMyPreferences(), schema$]).subscribe({
      next: ([mine, schema]) => {
        this.personalGroup = mine.group;
        this.setGroups(schema.groups);
        if (!this.activeGroup && this.groups.length) {
          this.activeGroup = this.groups[0].key;
        }
        this.isLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading settings schema:', error);
        this.errorMessage = this.i18n.t('Error al cargar la configuración');
        this.isLoading = false;
        this.cdr.detectChanges();
      },
    });
  }

  get currentGroup(): SettingGroup | undefined {
    return this.groups.find((g) => g.key === this.activeGroup);
  }

  get isDirty(): boolean {
    const group = this.currentGroup;
    return !!group && this.serialize(group) !== this.savedSnapshot[group.key];
  }

  get isPasswordFormDirty(): boolean {
    return !!(this.currentPassword || this.newPassword || this.passwordConfirmation);
  }

  setGroup(group: string): void {
    if (group === this.activeGroup) {
      return;
    }
    const dirty = this.activeGroup === SECURITY_GROUP ? this.isPasswordFormDirty : this.isDirty;
    if (dirty && !confirm(this.i18n.t('Tienes cambios sin guardar en esta sección. ¿Deseas descartarlos?'))) {
      return;
    }
    if (this.activeGroup === SECURITY_GROUP) {
      this.clearPasswordForm();
    } else {
      this.discardChanges();
    }
    this.activeGroup = group;
    this.errorMessage = '';
  }

  saveGroup(): void {
    const group = this.currentGroup;
    if (!group || !this.canEdit) return;

    const values: Record<string, any> = {};
    for (const field of group.fields) {
      if (field.type !== 'image') {
        values[field.key] = this.normalizeValue(field);
      }
    }

    this.isSaving = true;
    this.errorMessage = '';
    const successMessage = this.i18n.t('Cambios guardados en {section}', { section: this.i18n.t(group.label) });

    if (group.key === PERSONAL_GROUP) {
      this.savePersonal(values, successMessage);
      return;
    }

    this.systemSettingService.bulkUpdate(values).subscribe({
      next: (response) => {
        this.setGroups(response.groups);
        this.isSaving = false;
        this.applySystemChange(group.key, values);
        this.notificationService.success(successMessage);
        this.cdr.detectChanges();
      },
      error: (error) => this.handleError(error, 'Error al guardar la configuración'),
    });
  }

  savePassword(): void {
    if (this.isSavingPassword) {
      return;
    }
    if (!this.currentPassword || !this.newPassword || !this.passwordConfirmation) {
      this.errorMessage = this.i18n.t('Completa todos los campos de contraseña');
      return;
    }
    if (this.newPassword !== this.passwordConfirmation) {
      this.errorMessage = this.i18n.t('La confirmación de la contraseña no coincide.');
      return;
    }

    this.isSavingPassword = true;
    this.errorMessage = '';
    this.authService.changePassword(this.currentPassword, this.newPassword, this.passwordConfirmation).subscribe({
      next: (response) => {
        this.isSavingPassword = false;
        this.clearPasswordForm();
        this.notificationService.success(this.i18n.t(response.message));
        this.cdr.detectChanges();
      },
      error: (error) => {
        this.isSavingPassword = false;
        this.handleError(error, 'Error al cambiar la contraseña');
      },
    });
  }

  resetGroup(): void {
    const group = this.currentGroup;
    if (!group || !this.canEdit) return;

    const message = this.i18n.t('¿Restablecer los valores predeterminados de "{section}"? Esta acción no se puede deshacer.', {
      section: this.i18n.t(group.label),
    });
    if (!confirm(message)) {
      return;
    }

    this.isSaving = true;
    if (group.key === PERSONAL_GROUP) {
      const defaults = Object.fromEntries(group.fields.map((field) => [field.key, null]));
      this.savePersonal(defaults, this.i18n.t('Valores predeterminados restablecidos'));
      return;
    }

    this.systemSettingService.resetGroup(group.key).subscribe({
      next: (response) => {
        this.setGroups(response.groups);
        this.isSaving = false;
        this.applySystemChange(group.key, this.valuesOf(group.key));
        this.notificationService.success(this.i18n.t('Valores predeterminados restablecidos'));
        this.cdr.detectChanges();
      },
      error: (error) => this.handleError(error, 'Error al restablecer la configuración'),
    });
  }

  discardChanges(): void {
    const group = this.currentGroup;
    if (!group || this.savedSnapshot[group.key] === undefined) return;

    const saved = JSON.parse(this.savedSnapshot[group.key]) as Record<string, any>;
    group.fields.forEach((field) => (field.value = saved[field.key]));
  }

  onLogoSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;

    if (file.size > 2 * 1024 * 1024) {
      this.notificationService.error(this.i18n.t('El logo no puede superar los 2 MB'));
      return;
    }

    this.isUploadingLogo = true;
    this.systemSettingService.uploadLogo(file).subscribe({
      next: (response) => this.afterLogoChange(response.groups, response.message),
      error: (error) => {
        this.isUploadingLogo = false;
        this.handleError(error, 'Error al subir el logo');
      },
    });
  }

  removeLogo(): void {
    if (!confirm(this.i18n.t('¿Quitar el logo de la tienda?'))) return;

    this.isUploadingLogo = true;
    this.systemSettingService.deleteLogo().subscribe({
      next: (response) => this.afterLogoChange(response.groups, response.message),
      error: (error) => {
        this.isUploadingLogo = false;
        this.handleError(error, 'Error al quitar el logo');
      },
    });
  }

  optionEntries(field: SettingField): { value: string; label: string }[] {
    return Object.entries(field.options ?? {}).map(([value, label]) => ({ value, label }));
  }

  inputStep(field: SettingField): string {
    return field.type === 'float' ? '0.01' : '1';
  }

  // ---- History ----

  openHistory(): void {
    this.showHistory = true;
    this.loadHistory(1);
  }

  closeHistory(): void {
    this.showHistory = false;
  }

  loadHistory(page: number): void {
    this.historyLoading = true;
    this.systemSettingService.getHistory(page).subscribe({
      next: (response) => {
        this.history = response.data;
        this.historyMeta = response.meta;
        this.historyLoading = false;
        this.cdr.detectChanges();
      },
      error: (error) => {
        console.error('Error loading settings history:', error);
        this.historyLoading = false;
        this.notificationService.error(this.i18n.t('Error al cargar el historial de cambios'));
        this.cdr.detectChanges();
      },
    });
  }

  formatHistoryValue(change: SettingChange, value: string | null): string {
    if (value === null || value === '') {
      return change.type === 'image' ? this.i18n.t('Sin logo') : '—';
    }
    switch (change.type) {
      case 'boolean':
        return this.i18n.t(value === '1' || value === 'true' ? 'Activado' : 'Desactivado');
      case 'image':
        return this.i18n.t('Logo cargado');
      case 'select': {
        const label = this.findField(change.key)?.options?.[value];
        return label ? this.i18n.t(label) : value;
      }
      default:
        return value;
    }
  }

  logout(): void {
    this.authService.logout().subscribe({
      next: () => this.router.navigate(['/login']),
      error: () => {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        this.router.navigate(['/login']);
      },
    });
  }

  private afterLogoChange(groups: SettingGroup[], message: string): void {
    // Keep unsaved edits of the other store fields while refreshing the logo.
    const pending = this.currentGroup ? this.valuesOf(this.currentGroup.key) : {};
    this.setGroups(groups);
    this.currentGroup?.fields.forEach((field) => {
      if (field.type !== 'image' && field.key in pending) {
        field.value = pending[field.key];
      }
    });
    this.isUploadingLogo = false;
    this.notificationService.success(this.i18n.t(message));
    this.cdr.detectChanges();
  }

  private savePersonal(preferences: Record<string, any>, successMessage: string): void {
    this.systemSettingService.updateMyPreferences(preferences).subscribe({
      next: (response) => {
        this.personalGroup = response.group;
        this.setGroups(this.systemGroups);
        this.isSaving = false;
        this.preferencesService.apply(response.preferences);
        this.notificationService.success(successMessage);
        this.cdr.detectChanges();
      },
      error: (error) => this.handleError(error, 'Error al guardar tus preferencias'),
    });
  }

  /**
   * System preferences are only defaults, so reload them together with the
   * user's own choices instead of applying them directly.
   */
  private applySystemChange(groupKey: string, values: Record<string, any>): void {
    if (groupKey === 'system') {
      this.preferencesService.load();
    } else {
      this.preferencesService.apply(values);
    }
  }

  /** Replace the system groups, keeping the personal group as the first tab. */
  private setGroups(systemGroups: SettingGroup[]): void {
    this.systemGroups = systemGroups;
    this.groups = this.personalGroup ? [this.personalGroup, ...systemGroups] : systemGroups;
    this.savedSnapshot = Object.fromEntries(this.groups.map((group) => [group.key, this.serialize(group)]));
  }

  private serialize(group: SettingGroup): string {
    // null and '' are equivalent for the user, so they must not count as a change.
    return JSON.stringify(Object.fromEntries(group.fields.map((field) => [field.key, field.value ?? ''])));
  }

  private valuesOf(groupKey: string): Record<string, any> {
    const group = this.groups.find((g) => g.key === groupKey);
    return Object.fromEntries((group?.fields ?? []).map((field) => [field.key, field.value]));
  }

  private findField(key: string): SettingField | undefined {
    return this.groups.flatMap((group) => group.fields).find((field) => field.key === key);
  }

  private normalizeValue(field: SettingField): any {
    switch (field.type) {
      case 'boolean':
        return !!field.value;
      case 'integer':
        return Number.parseInt(field.value, 10) || 0;
      case 'float':
        return Number.parseFloat(field.value) || 0;
      default:
        return field.value ?? '';
    }
  }

  private clearPasswordForm(): void {
    this.currentPassword = '';
    this.newPassword = '';
    this.passwordConfirmation = '';
  }

  private handleError(error: any, fallback: string): void {
    console.error(fallback, error);
    this.isSaving = false;
    const errors = error.error?.errors;
    this.errorMessage = errors
      ? (Object.values(errors).flat() as string[]).join(' ')
      : error.error?.message || this.i18n.t(fallback);
    this.notificationService.error(this.errorMessage);
    this.cdr.detectChanges();
  }
}
