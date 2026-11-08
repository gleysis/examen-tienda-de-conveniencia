import { Directive, ElementRef, OnDestroy, effect, forwardRef, inject } from '@angular/core';
import { formatDate } from '@angular/common';
import { ControlValueAccessor, NG_VALUE_ACCESSOR } from '@angular/forms';
import { PreferencesService } from '../services/preferences';
import { I18nService } from '../services/i18n';

const CALENDAR_ICON =
  "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a855f7' stroke-width='2' stroke-linecap='round'%3E%3Crect x='3' y='4' width='18' height='18' rx='2'/%3E%3Cpath d='M16 2v4M8 2v4M3 10h18'/%3E%3C/svg%3E\")";

/**
 * Date field that displays its value using the user's date format preference
 * (e.g. 31/12/2026) while keeping the model as an ISO `yyyy-MM-dd` string.
 * Clicking it opens the browser's native calendar.
 * Usage: <input appDateInput [(ngModel)]="filters.date_from" />
 */
@Directive({
  selector: 'input[appDateInput]',
  providers: [{ provide: NG_VALUE_ACCESSOR, useExisting: forwardRef(() => DateInput), multi: true }],
  host: {
    type: 'text',
    readonly: '',
    autocomplete: 'off',
    '(click)': 'open()',
    '(keydown)': 'onKeydown($event)',
    '(blur)': 'onTouched()',
  },
})
export class DateInput implements ControlValueAccessor, OnDestroy {
  private readonly host: HTMLInputElement = inject(ElementRef).nativeElement;
  private readonly preferences = inject(PreferencesService);
  private readonly i18n = inject(I18nService);
  private readonly picker = document.createElement('input');
  private value = '';

  onChange: (value: string) => void = () => {};
  onTouched: () => void = () => {};

  constructor() {
    Object.assign(this.host.style, {
      cursor: 'pointer',
      caretColor: 'transparent',
      backgroundImage: CALENDAR_ICON,
      backgroundRepeat: 'no-repeat',
      backgroundPosition: 'right 0.75rem center',
      backgroundSize: '18px',
      paddingRight: '2.5rem',
    });

    this.picker.type = 'date';
    this.picker.tabIndex = -1;
    this.picker.setAttribute('aria-hidden', 'true');
    Object.assign(this.picker.style, {
      position: 'absolute',
      opacity: '0',
      pointerEvents: 'none',
      border: '0',
      padding: '0',
      margin: '0',
    });
    this.picker.addEventListener('change', () => this.select(this.picker.value));
    this.host.insertAdjacentElement('afterend', this.picker);

    effect(() => {
      this.preferences.preferences();
      this.i18n.language();
      this.render();
    });
  }

  ngOnDestroy(): void {
    this.picker.remove();
  }

  writeValue(value: string | null): void {
    this.value = value ? String(value).substring(0, 10) : '';
    this.picker.value = this.value;
    this.render();
  }

  registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.host.disabled = isDisabled;
  }

  open(): void {
    if (this.host.disabled) {
      return;
    }
    Object.assign(this.picker.style, {
      left: `${this.host.offsetLeft}px`,
      top: `${this.host.offsetTop}px`,
      width: `${this.host.offsetWidth}px`,
      height: `${this.host.offsetHeight}px`,
    });
    try {
      this.picker.showPicker();
    } catch {
      this.picker.click();
    }
  }

  onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Backspace' || event.key === 'Delete') {
      event.preventDefault();
      this.select('');
    } else if (event.key === 'Enter' || event.key === ' ' || event.key === 'ArrowDown') {
      event.preventDefault();
      this.open();
    }
  }

  private select(value: string): void {
    if (value === this.value) {
      return;
    }
    this.writeValue(value);
    this.onChange(this.value);
    this.host.dispatchEvent(new Event('change', { bubbles: true }));
  }

  private render(): void {
    const format = this.preferences.dateFormat;
    this.host.placeholder = format.toLowerCase().replace('yyyy', this.i18n.language() === 'es' ? 'aaaa' : 'yyyy');
    this.host.value = this.value ? formatDate(this.value, format, this.i18n.locale) : '';
  }
}
