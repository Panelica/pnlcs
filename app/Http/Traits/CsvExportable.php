<?php

namespace App\Http\Traits;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait CsvExportable
{
    /**
     * Stream a Collection of rows as a downloadable CSV response.
     */
    protected function streamCsvDownload(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, array_values((array) $row));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
