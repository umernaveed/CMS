<?php

namespace App\Support;

final class Code128Barcode
{
    private const START_CODE_B = 104;
    private const STOP_CODE = 106;
    private const QUIET_ZONE_MODULES = 10;

    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213',
        '122312', '132212', '221213', '221312', '231212', '112232', '122132',
        '122231', '113222', '123122', '123221', '223211', '221132', '221231',
        '213212', '223112', '312131', '311222', '321122', '321221', '312212',
        '322112', '322211', '212123', '212321', '232121', '111323', '131123',
        '131321', '112313', '132113', '132311', '211313', '231113', '231311',
        '112133', '112331', '132131', '113123', '113321', '133121', '313121',
        '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111',
        '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114',
        '413111', '241112', '134111', '111242', '121142', '121241', '114212',
        '124112', '124211', '411212', '421112', '421211', '212141', '214121',
        '412121', '111143', '111341', '131141', '114113', '114311', '411113',
        '411311', '113141', '114131', '311141', '411131', '211412', '211214',
        '211232', '2331112',
    ];

    public static function svg(string $value, int $height = 72): string
    {
        $value = self::normalizeValue($value);
        $codes = self::codesFor($value);
        $bars = [];
        $x = self::QUIET_ZONE_MODULES;

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            $parts = str_split($pattern);

            foreach ($parts as $index => $width) {
                $width = (int) $width;

                if ($index % 2 === 0) {
                    $bars[] = sprintf(
                        '<rect x="%d" y="0" width="%d" height="%d" fill="#000"/>',
                        $x,
                        $width,
                        $height
                    );
                }

                $x += $width;
            }
        }

        $width = $x + self::QUIET_ZONE_MODULES;
        $label = htmlspecialchars('Barcode '.$value, ENT_QUOTES, 'UTF-8');

        return sprintf(
            '<svg class="barcode-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="100%%" height="%d" role="img" aria-label="%s" shape-rendering="crispEdges">%s</svg>',
            $width,
            $height,
            $height,
            $label,
            implode('', $bars)
        );
    }

    private static function codesFor(string $value): array
    {
        $codes = [self::START_CODE_B];
        $checksum = self::START_CODE_B;

        foreach (str_split($value) as $position => $character) {
            $code = ord($character) - 32;
            $codes[] = $code;
            $checksum += $code * ($position + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = self::STOP_CODE;

        return $codes;
    }

    private static function normalizeValue(string $value): string
    {
        $value = preg_replace('/[^\x20-\x7E]/', '', trim($value)) ?? '';

        return $value !== '' ? $value : 'N/A';
    }
}
