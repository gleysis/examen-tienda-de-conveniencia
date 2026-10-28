<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ReportExportController extends Controller
{
    public function sales(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = Sale::with('user')->latest();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return $this->exportResponse($request, 'ventas', [
            'Factura', 'Fecha', 'Vendedor', 'Subtotal', 'Impuesto', 'Descuento',
            'Total', 'Método de pago', 'Estado',
        ], $query->cursor()->map(fn (Sale $sale) => [
            $sale->invoice_number,
            $sale->created_at?->format('Y-m-d H:i'),
            $sale->user?->name,
            $sale->subtotal,
            $sale->tax,
            $sale->discount,
            $sale->total,
            $sale->payment_method,
            $sale->status,
        ]));
    }

    public function inventoryMovements(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = InventoryMovement::with(['product', 'user'])->latest();

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $this->exportResponse($request, 'movimientos-inventario', [
            'Fecha', 'Producto', 'SKU', 'Tipo', 'Cantidad',
            'Stock anterior', 'Stock nuevo', 'Referencia', 'Usuario', 'Notas',
        ], $query->cursor()->map(fn (InventoryMovement $movement) => [
            $movement->created_at?->format('Y-m-d H:i'),
            $movement->product?->name,
            $movement->product?->sku,
            $movement->type === 'entry' ? 'Entrada' : 'Salida',
            $movement->quantity,
            $movement->previous_stock,
            $movement->new_stock,
            $movement->reference,
            $movement->user?->name,
            $movement->notes,
        ]));
    }

    public function inventoryStock(Request $request): StreamedResponse|BinaryFileResponse
    {
        $query = Product::with('category')->where('is_active', true)->orderBy('name');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return $this->exportResponse($request, 'inventario-stock', [
            'SKU', 'Producto', 'Categoría', 'Stock', 'Stock mínimo',
            'Costo', 'Precio', 'Valor en stock',
        ], $query->cursor()->map(fn (Product $product) => [
            $product->sku,
            $product->name,
            $product->category?->name,
            $product->stock,
            $product->min_stock,
            $product->cost,
            $product->price,
            $product->stock * $product->cost,
        ]));
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function exportResponse(Request $request, string $baseName, array $headers, iterable $rows): StreamedResponse|BinaryFileResponse
    {
        if ($request->input('format') === 'xlsx') {
            return $this->xlsxResponse($baseName.'.xlsx', $headers, $rows);
        }

        return $this->csvResponse($baseName.'.csv', $headers, $rows);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function csvResponse(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens accented characters correctly.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Minimal dependency-free XLSX writer (ZipArchive + inline strings).
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function xlsxResponse(string $filename, array $headers, iterable $rows): BinaryFileResponse
    {
        $rows = $rows instanceof \Traversable ? iterator_to_array($rows) : $rows;

        $sheetRows = [$this->xlsxRow($headers, 1)];
        foreach (array_values($rows) as $index => $row) {
            $sheetRows[] = $this->xlsxRow($row, $index + 2);
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>';

        $tmpPath = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmpPath, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        return response()->download($tmpPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function xlsxRow(array $row, int $rowNumber): string
    {
        $cells = [];
        foreach (array_values($row) as $index => $value) {
            $ref = $this->xlsxColumn($index).$rowNumber;
            if (is_numeric($value)) {
                $cells[] = '<c r="'.$ref.'"><v>'.$value.'</v></c>';
            } else {
                $cells[] = '<c r="'.$ref.'" t="inlineStr"><is><t>'
                    .htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    .'</t></is></c>';
            }
        }

        return '<row r="'.$rowNumber.'">'.implode('', $cells).'</row>';
    }

    private function xlsxColumn(int $index): string
    {
        $letters = '';
        $index++;
        while ($index > 0) {
            $letters = chr(65 + ($index - 1) % 26).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
