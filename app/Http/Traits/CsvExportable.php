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
            // Written as text, not as something a spreadsheet will run: a cell
            // starting with =, +, - or @ is a formula to Excel and it runs when
            // the operator opens the file. These columns are filled in by
            // customers.
            fputcsv($handle, array_map('csv_cell', $headers));
            foreach ($rows as $row) {
                fputcsv($handle, array_map('csv_cell', array_values((array) $row)));
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
