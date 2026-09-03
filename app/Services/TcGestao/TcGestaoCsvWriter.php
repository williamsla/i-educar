<?php

namespace App\Services\TcGestao;

class TcGestaoCsvWriter
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    public static function toString(array $headers, array $rows, string $delimiter = ','): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar buffer CSV.');
        }

        fputcsv($handle, $headers, $delimiter);
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), $delimiter);
        }

        rewind($handle);
        $content = stream_get_contents($handle) ?: '';
        fclose($handle);

        // BOM UTF-8 para Excel / TC Gestão
        return "\xEF\xBB\xBF" . $content;
    }
}
