<?php

namespace App\Support;

/**
 * Tamaño máximo real de los PDF: el menor entre lo configurado en el sistema
 * (GACETA_PDF_MAX_MB) y lo que permite PHP (upload_max_filesize y post_max_size).
 */
class LimitesArchivo
{
    /** Máximo configurado en el sistema, en KB (lo usa la validación). */
    public static function pdfMaxKb(): int
    {
        return config('gaceta.documentos.pdf_max_mb') * 1024;
    }

    /** Máximo efectivo, en KB, considerando también la configuración de PHP. */
    public static function pdfMaxEfectivoKb(): int
    {
        return min(
            self::pdfMaxKb(),
            self::iniAKb((string) ini_get('upload_max_filesize')),
            self::iniAKb((string) ini_get('post_max_size')),
        );
    }

    public static function pdfMaxEfectivoMb(): string
    {
        return rtrim(rtrim(number_format(self::pdfMaxEfectivoKb() / 1024, 1, '.', ''), '0'), '.');
    }

    /** Convierte valores de php.ini ("2M", "512K", "1G") a KB. 0 = sin límite. */
    public static function iniAKb(string $valor): int
    {
        $valor = trim($valor);

        if ($valor === '' || $valor === '0' || $valor === '-1') {
            return PHP_INT_MAX;
        }

        $numero = (float) $valor;

        return (int) match (strtoupper(substr($valor, -1))) {
            'G' => $numero * 1024 * 1024,
            'M' => $numero * 1024,
            'K' => $numero,
            default => $numero / 1024,
        };
    }

    /** Tamaño legible: 1.5 MB, 320 KB. */
    public static function legible(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1, ',', '.').' MB'
            : number_format(max(1, $bytes / 1024), 0, ',', '.').' KB';
    }
}
