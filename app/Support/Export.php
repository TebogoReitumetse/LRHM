<?php

namespace App\Support;

use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared export helpers: CSV (opens in Excel), PDF and printable HTML.
 */
class Export
{
    /** @param array<int,string> $headings  @param iterable<array> $rows */
    public static function csv(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        AuditLog::record('export.csv', null, 'Exported '.$filename);

        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
            fputcsv($out, $headings, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => self::safeCell($v), $row), escape: '');
            }
            fclose($out);
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function pdf(string $view, array $data, string $filename, string $orientation = 'landscape')
    {
        AuditLog::record('export.pdf', null, 'Exported '.$filename);

        return Pdf::loadView($view, $data + ['pdf' => true])->setPaper('a4', $orientation)->download($filename.'.pdf');
    }

    public static function print(string $view, array $data)
    {
        return view($view, $data + ['pdf' => false]);
    }

    /** Stop spreadsheet formula injection. */
    private static function safeCell($v): string
    {
        $v = (string) $v;

        return preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v;
    }
}
