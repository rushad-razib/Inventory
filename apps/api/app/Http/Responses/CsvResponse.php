<?php

namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvResponse
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|null>>  $rows
     */
    public static function make(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, $headers);

            foreach ($rows as $row) {
                fputcsv($out, $row);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
