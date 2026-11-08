import { Injectable } from '@angular/core';
import { Observable, map } from 'rxjs';
import { SystemSettingService } from './system-setting';
import { tr } from './i18n';

export type PaperWidth = '58' | '80';

export interface ReceiptSettings {
  storeName: string;
  address: string;
  phone: string;
  email: string;
  taxId: string;
  logo: string;
  showLogo: boolean;
  showStoreInfo: boolean;
  footer: string;
  currencySymbol: string;
  paperWidth: PaperWidth;
}

export interface TicketItem {
  name: string;
  quantity: number;
  unit_price: number;
  subtotal: number;
}

export interface TicketData {
  invoice_number: string;
  date: string;
  cashier: string;
  items: TicketItem[];
  subtotal: number;
  discount: number;
  tax: number;
  total: number;
  payment_method: string;
  amount_received?: number | null;
  change?: number | null;
}

/** Printable width in mm for each roll; thermal heads leave ~5 mm unprinted on each side. */
const PRINTABLE_WIDTH_MM: Record<PaperWidth, number> = { '58': 48, '80': 72 };

const PAYMENT_LABELS: Record<string, string> = {
  cash: 'Efectivo',
  card: 'Tarjeta',
  transfer: 'Transferencia',
};

/**
 * Prints sale tickets sized for thermal roll printers (58 mm portable or 80 mm counter).
 */
@Injectable({ providedIn: 'root' })
export class TicketPrinterService {
  constructor(private systemSettingService: SystemSettingService) {}

  loadSettings(): Observable<ReceiptSettings> {
    return this.systemSettingService.getPublicSettings().pipe(
      map((response) => TicketPrinterService.toReceiptSettings(response.settings || {}))
    );
  }

  static toReceiptSettings(settings: Record<string, any>): ReceiptSettings {
    const isOn = (value: unknown) =>
      value === undefined || value === true || value === 1 || value === '1' || value === 'true';

    return {
      storeName: settings['store_name'] || 'Tienda Leiva',
      address: settings['store_address'] || '',
      phone: settings['store_phone'] || '',
      email: settings['store_email'] || '',
      taxId: settings['store_tax_id'] || '',
      logo: settings['store_logo'] || '',
      showLogo: isOn(settings['receipt_show_logo']),
      showStoreInfo: isOn(settings['receipt_show_store_info']),
      footer: settings['receipt_footer'] || '¡Gracias por su compra!',
      currencySymbol: settings['currency_symbol'] || 'C$',
      paperWidth: String(settings['receipt_paper_width']) === '80' ? '80' : '58',
    };
  }

  print(ticket: TicketData, settings: ReceiptSettings): void {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.width = `${settings.paperWidth}mm`;
    iframe.style.height = '0';
    iframe.style.border = '0';
    document.body.appendChild(iframe);

    const win = iframe.contentWindow;
    const doc = win?.document;
    if (!win || !doc) {
      iframe.remove();
      return;
    }

    doc.open();
    doc.write(this.buildHtml(ticket, settings));
    doc.close();

    const images = Array.from(doc.images).filter((img) => !img.complete);
    let pending = images.length;
    const ready = () => {
      // Size the page to the ticket so the roll is not cut into fixed-height pages.
      const heightMm = Math.ceil((doc.body.scrollHeight * 25.4) / 96) + 4;
      const pageStyle = doc.createElement('style');
      pageStyle.textContent = `@page { size: ${settings.paperWidth}mm ${heightMm}mm; margin: 0; }`;
      doc.head.appendChild(pageStyle);

      win.focus();
      win.print();
      setTimeout(() => iframe.remove(), 1000);
    };

    if (pending === 0) {
      setTimeout(ready, 50);
      return;
    }
    images.forEach((img) => {
      const done = () => --pending === 0 && ready();
      img.addEventListener('load', done);
      img.addEventListener('error', done);
    });
  }

  private buildHtml(ticket: TicketData, settings: ReceiptSettings): string {
    const e = escapeHtml;
    const money = (amount: number) =>
      `${e(settings.currencySymbol)} ${Number(amount || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })}`;
    const row = (label: string, value: string, cls = '') =>
      `<div class="row ${cls}"><span>${label}</span><span>${value}</span></div>`;

    const storeInfo = settings.showStoreInfo
      ? [
          settings.taxId && `${e(tr('RUC:'))} ${e(settings.taxId)}`,
          settings.address && e(settings.address),
          settings.phone && `${e(tr('Tel:'))} ${e(settings.phone)}`,
          settings.email && e(settings.email),
        ]
          .filter(Boolean)
          .map((line) => `<div>${line}</div>`)
          .join('')
      : '';

    const items = ticket.items
      .map(
        (item) => `
        <div class="item">
          <div class="item-name">${e(item.name)}</div>
          ${row(`${item.quantity} x ${money(item.unit_price)}`, money(item.subtotal))}
        </div>`
      )
      .join('');

    const isCash = ticket.payment_method === 'cash';
    const width = PRINTABLE_WIDTH_MM[settings.paperWidth];
    const fontSize = settings.paperWidth === '58' ? 11 : 12;

    return `<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>${e(tr('Recibo'))} ${e(ticket.invoice_number)}</title>
  <style>
    * { box-sizing: border-box; }
    html, body { margin: 0; padding: 0; background: #fff; }
    body {
      width: ${settings.paperWidth}mm;
      padding: 2mm ${(Number(settings.paperWidth) - width) / 2}mm 4mm;
      font-family: 'Courier New', Courier, monospace;
      font-size: ${fontSize}px;
      line-height: 1.3;
      color: #000;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    .center { text-align: center; }
    .logo { display: block; max-width: 70%; max-height: 18mm; margin: 0 auto 1.5mm; object-fit: contain; filter: grayscale(100%) contrast(1.2); }
    .store-name { font-size: ${fontSize + 3}px; font-weight: bold; word-wrap: break-word; }
    .store-info { font-size: ${fontSize - 1}px; word-wrap: break-word; }
    .sep { border-top: 1px dashed #000; margin: 1.5mm 0; }
    .row { display: flex; justify-content: space-between; gap: 2mm; }
    .row span:last-child { white-space: nowrap; text-align: right; }
    .item { margin-bottom: 1mm; }
    .item-name { font-weight: bold; word-wrap: break-word; }
    .total { font-size: ${fontSize + 3}px; font-weight: bold; margin: 1mm 0; }
    .footer { margin-top: 2mm; word-wrap: break-word; }
  </style>
</head>
<body>
  <div class="center">
    ${settings.showLogo && settings.logo ? `<img class="logo" src="${e(settings.logo)}" alt="">` : ''}
    <div class="store-name">${e(settings.storeName)}</div>
    <div class="store-info">${storeInfo}</div>
  </div>
  <div class="sep"></div>
  ${row(e(tr('Factura:')), e(ticket.invoice_number))}
  ${row(e(tr('Fecha:')), e(ticket.date))}
  ${ticket.cashier ? row(e(tr('Cajero:')), e(ticket.cashier)) : ''}
  <div class="sep"></div>
  ${items}
  <div class="sep"></div>
  ${row(e(tr('Subtotal:')), money(ticket.subtotal))}
  ${ticket.discount > 0 ? row(e(tr('Descuento:')), `-${money(ticket.discount)}`) : ''}
  ${ticket.tax > 0 ? row(e(tr('IVA:')), money(ticket.tax)) : ''}
  ${row(e(tr('TOTAL')), money(ticket.total), 'total')}
  <div class="sep"></div>
  ${row(e(tr('Pago:')), e(tr(PAYMENT_LABELS[ticket.payment_method] || ticket.payment_method)))}
  ${isCash && ticket.amount_received != null ? row(e(tr('Recibido:')), money(ticket.amount_received)) : ''}
  ${isCash && ticket.change != null ? row(e(tr('Cambio:')), money(ticket.change)) : ''}
  <div class="sep"></div>
  <div class="center footer">${e(settings.footer)}</div>
</body>
</html>`;
  }
}

function escapeHtml(value: string): string {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}
