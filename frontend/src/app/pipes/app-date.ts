import { Pipe, PipeTransform, inject } from '@angular/core';
import { formatDate } from '@angular/common';
import { PreferencesService } from '../services/preferences';
import { I18nService } from '../services/i18n';

export type DateDisplay = 'date' | 'time' | 'datetime' | 'long';

/**
 * Formats dates using the global date/time format preferences.
 * Usage: {{ value | appDate }}, {{ value | appDate:'datetime' }}.
 */
@Pipe({ name: 'appDate', pure: false })
export class AppDatePipe implements PipeTransform {
  private readonly preferences = inject(PreferencesService);
  private readonly i18n = inject(I18nService);

  transform(value: string | number | Date | null | undefined, display: DateDisplay = 'date'): string {
    if (value === null || value === undefined || value === '') {
      return '';
    }

    const format = {
      date: this.preferences.dateFormat,
      time: this.preferences.timeFormat,
      datetime: `${this.preferences.dateFormat} ${this.preferences.timeFormat}`,
      long: 'fullDate',
    }[display];

    try {
      return formatDate(value, format, this.i18n.locale);
    } catch {
      return String(value);
    }
  }
}
