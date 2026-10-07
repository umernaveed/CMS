<?php

namespace App\Support;

use Illuminate\Http\Response;

class SimplePdf
{
    public static function download(string $filename, array $lines, array $options = []): Response
    {
        $pdf = self::make($lines, $options);
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename);
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => strlen($pdf),
        ]);
    }

    public static function inline(string $filename, array $lines, array $options = []): Response
    {
        $pdf = self::make($lines, $options);
        $filename = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $filename);
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => strlen($pdf),
        ]);
    }

    public static function make(array $lines, array $options = []): string
    {
        $width = (float) ($options['width'] ?? 612);
        $height = (float) ($options['height'] ?? 792);
        $margin = (float) ($options['margin'] ?? 36);
        $fontSize = (float) ($options['font_size'] ?? 10);
        $leading = (float) ($options['leading'] ?? ($fontSize + 4));
        $font = $options['font'] ?? 'Courier';
        $charsPerLine = max(24, (int) floor(($width - ($margin * 2)) / ($fontSize * 0.56)));
        $maxLines = max(1, (int) floor(($height - ($margin * 2)) / $leading));

        $wrapped = [];
        foreach ($lines as $line) {
            $text = rtrim((string) $line);
            if ($text === '') {
                $wrapped[] = '';
                continue;
            }

            foreach (explode("\n", wordwrap($text, $charsPerLine, "\n", true)) as $part) {
                $wrapped[] = $part;
            }
        }

        $pages = array_chunk($wrapped, $maxLines);
        if ($pages === []) {
            $pages = [[]];
        }

        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[] = '<< /Type /Pages /Kids [] /Count 0 >>';
        $kids = [];
        $contentObjectNumber = 3 + count($pages);

        foreach ($pages as $index => $pageLines) {
            $pageObject = 3 + $index;
            $contentObject = $contentObjectNumber + $index;
            $kids[] = $pageObject.' 0 R';
            $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$width} {$height}] /Resources << /Font << /F1 ".($contentObjectNumber + count($pages))." 0 R >> >> /Contents {$contentObject} 0 R >>";
        }

        foreach ($pages as $pageLines) {
            $objects[] = self::contentObject($pageLines, $height, $margin, $fontSize, $leading);
        }

        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /{$font} >>";
        $objects[1] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($pages).' >>';

        return self::assemble($objects);
    }

    private static function contentObject(array $lines, float $height, float $margin, float $fontSize, float $leading): string
    {
        $content = "BT\n/F1 {$fontSize} Tf\n{$margin} ".($height - $margin)." Td\n";
        foreach ($lines as $line) {
            $content .= '('.self::escape($line).") Tj\n0 -{$leading} Td\n";
        }
        $content .= "ET\n";

        return '<< /Length '.strlen($content)." >>\nstream\n{$content}endstream";
    }

    private static function assemble(array $objects): string
    {
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $number = $index + 1;
            $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }
}
