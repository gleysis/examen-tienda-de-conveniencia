import { AppDatePipe } from './app-date';
import { MoneyPipe } from './money';
import { TranslatePipe } from './translate';

/** Pipes that depend on the global preferences (language, date format, currency). */
export const APP_PIPES = [TranslatePipe, AppDatePipe, MoneyPipe] as const;
