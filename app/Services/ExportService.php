<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Stream an Excel XML (.xls / SpreadsheetML) spreadsheet for download.
     *
     * @param  array  $columns  Array of column definitions ['Header' => fn($item) => $item->value]
     */
    public function exportExcel(Builder $query, string $filename, array $columns, int $chunkSize = 100): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query, $columns, $chunkSize) {
            $output = fopen('php://output', 'w');

            // XML Spreadsheet 2003 / SpreadsheetML header
            fwrite($output, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n");
            fwrite($output, "<?mso-application progid=\"Excel.Sheet\"?>\n");
            fwrite($output, "<Workbook xmlns=\"urn:schemas-microsoft-com:office:spreadsheet\"\n");
            fwrite($output, " xmlns:o=\"urn:schemas-microsoft-com:office:office\"\n");
            fwrite($output, " xmlns:x=\"urn:schemas-microsoft-com:office:excel\"\n");
            fwrite($output, " xmlns:ss=\"urn:schemas-microsoft-com:office:spreadsheet\"\n");
            fwrite($output, " xmlns:html=\"http://www.w3.org/TR/REC-html40\">\n");

            // Styles
            fwrite($output, " <Styles>\n");
            fwrite($output, "  <Style ss:ID=\"Default\" ss:Name=\"Normal\">\n");
            fwrite($output, "   <Alignment ss:Vertical=\"Center\"/>\n");
            fwrite($output, "  </Style>\n");
            fwrite($output, "  <Style ss:ID=\"HeaderStyle\">\n");
            fwrite($output, "   <Font ss:Bold=\"1\" ss:Color=\"#FFFFFF\"/>\n");
            fwrite($output, "   <Interior ss:Color=\"#1E293B\" ss:Pattern=\"Solid\"/>\n");
            fwrite($output, "   <Alignment ss:Horizontal=\"Center\" ss:Vertical=\"Center\"/>\n");
            fwrite($output, "  </Style>\n");
            // Dynamic styles could be added here if needed, but for generic we keep it simple.
            fwrite($output, " </Styles>\n");

            fwrite($output, " <Worksheet ss:Name=\"Export\">\n");
            fwrite($output, "  <Table>\n");

            // Generate columns width (generic width of 120)
            foreach ($columns as $header => $callback) {
                fwrite($output, "   <Column ss:Width=\"120\"/>\n");
            }

            // Header row
            fwrite($output, "   <Row ss:StyleID=\"HeaderStyle\" ss:Height=\"25\">\n");
            foreach (array_keys($columns) as $header) {
                fwrite($output, '    <Cell><Data ss:Type="String">'.htmlspecialchars($header)."</Data></Cell>\n");
            }
            fwrite($output, "   </Row>\n");

            // Data rows
            $query->chunk($chunkSize, function ($items) use ($output, $columns) {
                foreach ($items as $item) {
                    fwrite($output, "   <Row>\n");
                    foreach ($columns as $header => $callback) {
                        $value = $callback($item);

                        $type = is_numeric($value) ? 'Number' : 'String';
                        $value = htmlspecialchars((string) $value);

                        fwrite($output, "    <Cell><Data ss:Type=\"{$type}\">{$value}</Data></Cell>\n");
                    }
                    fwrite($output, "   </Row>\n");
                }
            });

            fwrite($output, "  </Table>\n");
            fwrite($output, " </Worksheet>\n");
            fwrite($output, "</Workbook>\n");

            fclose($output);
        }, 200, $headers);
    }
}
