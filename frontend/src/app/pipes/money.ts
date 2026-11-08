import { Pipe, PipeTransform, inject } from '@angular/core';
import { PreferencesService } from '../services/preferences';

/**
 * Formats an amount with the configured currency symbol: {{ total | money }} → "C$ 1,250.00".
 */
@Pipe({ name: 'money', pure: false })
export class MoneyPipe implements PipeTransform {
  private readonly preferences = inject(PreferencesService);

  transform(value: number | string | null | undefined): string {
    const amount = Number(value);
    const safeAmount = Number.isFinite(amount) ? amount : 0;
    const formatted = safeAmount.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${this.preferences.currencySymbol()} ${formatted}`;
  }
}
