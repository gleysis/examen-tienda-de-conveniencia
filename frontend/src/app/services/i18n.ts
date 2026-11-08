import { Injectable, signal } from '@angular/core';
import { EN } from '../i18n/en';

export type Language = 'es' | 'en';

type TranslationParams = Record<string, string | number | null | undefined>;

let activeService: I18nService | null = null;

/**
 * Translate a Spanish source text from TypeScript code (messages, confirms, alerts).
 * Templates should use the `t` pipe instead.
 */
export function tr(text: string, params?: TranslationParams): string {
  return activeService ? activeService.t(text, params) : text;
}

/**
 * Lightweight runtime translation. Spanish source text is used as the key,
 * so Spanish needs no dictionary and missing English entries fall back to Spanish.
 */
@Injectable({ providedIn: 'root' })
export class I18nService {
  readonly language = signal<Language>('es');

  constructor() {
    activeService = this;
  }

  t(text: string, params?: TranslationParams): string {
    const translated = this.language() === 'en' ? EN[text] ?? text : text;
    if (!params) {
      return translated;
    }
    return translated.replace(/\{(\w+)\}/g, (match, key) =>
      params[key] === undefined || params[key] === null ? match : String(params[key])
    );
  }

  get locale(): string {
    return this.language() === 'en' ? 'en-US' : 'es';
  }
}
