<?php

namespace App\Concerns;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

trait WritesXlsx
{
    /**
     * Minimal dependency-free XLSX writer (ZipArchive + inline strings).
     *
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     * @param  array<int, array{0: string, 1: mixed}>  $meta
     */
    protected function xlsxResponse(string $filename, array $headers, iterable $rows, array $meta = []): BinaryFileResponse
    {
        $rows = $rows instanceof \Traversable ? iterator_to_array($rows) : $rows;

        $sheetRows = [];
        $rowNumber = 1;

        foreach ($meta as [$label, $value]) {
            $sheetRows[] = $this->xlsxRow([$label, $value], $rowNumber++);
        }
        if ($meta !== []) {
            $sheetRows[] = $this->xlsxRow([], $rowNumber++);
        }
        if ($headers !== []) {
            $sheetRows[] = $this->xlsxRow($headers, $rowNumber++);
        }
        foreach (array_values($rows) as $row) {
            $sheetRows[] = $this->xlsxRow($row, $rowNumber++);
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
