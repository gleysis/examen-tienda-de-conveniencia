import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { I18nService, Language } from './i18n';

export type Theme = 'light' | 'dark' | 'auto';
export type FontSize = 'small' | 'normal' | 'large';

export interface Preferences {
  theme: Theme;
  language: Language;
  font_size: FontSize;
  date_format: string;
  time_format: string;
}

const STORAGE_KEY = 'system_preferences';
const CURRENCY_KEY = 'system_currency_symbol';

const DEFAULTS: Preferences = {
  theme: 'light',
  language: 'es',
  font_size: 'normal',
  date_format: 'dd/MM/yyyy',
  time_format: 'HH:mm',
};

const ALLOWED: Record<keyof Preferences, readonly string[]> = {
  theme: ['light', 'dark', 'auto'],
  language: ['es', 'en'],
  font_size: ['small', 'normal', 'large'],
  date_format: ['dd/MM/yyyy', 'MM/dd/yyyy', 'yyyy-MM-dd'],
  time_format: ['HH:mm', 'hh:mm a'],
};

const FONT_SIZES: Record<FontSize, string> = {
  small: '14px',
  normal: '16px',
  large: '18px',
};

/**
 * Applies the user's preferences (theme, language, font size and date/time
 * formats), falling back to the system defaults, to the whole application. Values are cached locally so
 * they are applied instantly on the next load, even before the API responds.
 */
@Injectable({ providedIn: 'root' })
export class PreferencesService {
  private readonly http = inject(HttpClient);
  private readonly i18n = inject(I18nService);
  private readonly apiUrl = 'http://127.0.0.1:8000/api';
  private readonly darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

  readonly preferences = signal<Preferences>(this.readCache());
  readonly currencySymbol = signal<string>(localStorage.getItem(CURRENCY_KEY) || 'C$');

  constructor() {
    this.darkQuery.addEventListener('change', () => this.applyTheme(this.preferences().theme));
    this.apply(this.preferences());
  }

  /**
   * Fetch the system defaults and, for a signed-in user, their own
   * preferences on top of them.
   */
  load(): void {
    this.http.get<{ settings: Record<string, any> }>(`${this.apiUrl}/settings/public`).subscribe({
      next: (response) => {
        this.apply(response.settings || {});
        this.loadPersonal();
      },
      error: () => {
        // Keep cached preferences if the API is unavailable.
        this.loadPersonal();
      },
    });
  }

  private loadPersonal(): void {
    if (!localStorage.getItem('auth_token')) {
      return;
    }
    this.http.get<{ preferences: Record<string, any> }>(`${this.apiUrl}/me/preferences`).subscribe({
      next: (response) => this.apply(response.preferences || {}),
      error: () => {
        // Fall back to the system defaults already applied.
      },
    });
  }

  apply(values: Partial<Record<keyof Preferences | 'currency_symbol', any>>): void {
    if (typeof values.currency_symbol === 'string' && values.currency_symbol.trim() !== '') {
      this.currencySymbol.set(values.currency_symbol);
      localStorage.setItem(CURRENCY_KEY, values.currency_symbol);
    }

    const next: Preferences = { ...this.preferences() };
    (Object.keys(DEFAULTS) as (keyof Preferences)[]).forEach((key) => {
      if (ALLOWED[key].includes(values[key])) {
        (next as any)[key] = values[key];
      }
    });

    this.preferences.set(next);
    localStorage.setItem(STORAGE_KEY, JSON.stringify(next));

    this.applyTheme(next.theme);
    document.documentElement.style.fontSize = FONT_SIZES[next.font_size] ?? FONT_SIZES.normal;
    document.documentElement.lang = next.language;
    this.i18n.language.set(next.language);
  }

  get dateFormat(): string {
    return this.preferences().date_format;
  }

  get timeFormat(): string {
    return this.preferences().time_format;
  }

  private applyTheme(theme: Theme): void {
    const resolved = theme === 'auto' ? (this.darkQuery.matches ? 'dark' : 'light') : theme;
    document.documentElement.dataset['theme'] = resolved;
    document.documentElement.style.colorScheme = resolved;
  }

  private readCache(): Preferences {
    const cached: Preferences = { ...DEFAULTS };
    try {
      const stored = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');
      (Object.keys(DEFAULTS) as (keyof Preferences)[]).forEach((key) => {
        if (ALLOWED[key].includes(stored[key])) {
          (cached as any)[key] = stored[key];
        }
      });
    } catch {
      // Ignore a corrupted cache and fall back to defaults.
    }
    return cached;
  }
}
