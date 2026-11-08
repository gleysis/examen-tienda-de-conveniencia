import { Pipe, PipeTransform, inject } from '@angular/core';
import { I18nService } from '../services/i18n';

@Pipe({ name: 't', pure: false })
export class TranslatePipe implements PipeTransform {
  private readonly i18n = inject(I18nService);

  transform(text: string | null | undefined, params?: Record<string, string | number | null | undefined>): string {
    return text ? this.i18n.t(text, params) : '';
  }
}
