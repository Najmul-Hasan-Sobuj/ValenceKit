<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    /**
     * Stream a CSV file for download.
     *
     * @param  array  $columns  Array of column definitions ['Header' => fn($item) => $item->value]
     */
    public function exportCsv(Builder $query, string $filename, array $columns, int $chunkSize = 100): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query, $columns, $chunkSize) {
            $output = fopen('php://output', 'w');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_keys($columns), ',', '"', '', "\r\n");

            $query->chunk($chunkSize, function ($items) use ($output, $columns) {
                foreach ($items as $item) {
                    $row = [];

                    foreach ($columns as $callback) {
                        $row[] = $this->escapeFormula($callback($item));
                    }

                    fputcsv($output, $row, ',', '"', '', "\r\n");
                }
            });

            fclose($output);
        }, 200, $headers);
    }

    /**
     * Sanitize values to prevent CSV formula injection.
     */
    public function escapeFormula(mixed $value): mixed
    {
        if (! is_string($value) || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^([\x00-\x20]*[=+\-@|%]|\t|\r)/', $value) === 1
            ? "'{$value}"
            : $value;
    }
}
