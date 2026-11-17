<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

            $this->writeCsv($query, $columns, $output, $chunkSize);

            fclose($output);
        }, 200, $headers);
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, callable>  $columns
     * @param  resource  $output
     */
    public function writeCsv(Builder $query, array $columns, mixed $output, int $chunkSize = 100): void
    {
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

    }

    /** @return array<string, callable> */
    public function leadColumns(): array
    {
        return [
            'Name' => fn ($lead) => $lead->name, 'Phone' => fn ($lead) => $lead->phone,
            'Email' => fn ($lead) => $lead->email, 'Address' => fn ($lead) => $lead->address,
            'Website' => fn ($lead) => $lead->website, 'Google Maps' => fn ($lead) => $lead->maps_url,
            'Stage' => fn ($lead) => $lead->stage,
        ];
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
