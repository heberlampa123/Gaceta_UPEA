<?php

namespace App\Support;

/**
 * Genera un PDF mínimo de una página con texto (sin librerías externas).
 * Se usa solo para datos de demostración y pruebas automáticas.
 */
class PdfSimple
{
    /** @param  string[]  $lineas  texto adicional bajo el título */
    public static function generar(string $titulo, array $lineas = []): string
    {
        $contenido = '';
        $y = 740;

        foreach (explode("\n", wordwrap($titulo, 60)) as $linea) {
            $contenido .= self::texto($linea, 16, $y);
            $y -= 22;
        }

        $y -= 8;

        foreach ($lineas as $parrafo) {
            foreach (explode("\n", wordwrap($parrafo, 90)) as $linea) {
                $contenido .= self::texto($linea, 11, $y);
                $y -= 16;
            }
        }

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($contenido)." >>\nstream\n{$contenido}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $posiciones = [];

        foreach ($objetos as $i => $objeto) {
            $posiciones[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";

        foreach ($posiciones as $posicion) {
            $pdf .= sprintf("%010d 00000 n \n", $posicion);
        }

        return $pdf."trailer\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    private static function texto(string $texto, int $tamano, int $y): string
    {
        $texto = mb_convert_encoding($texto, 'Windows-1252', 'UTF-8');
        $texto = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $texto);

        return "BT /F1 {$tamano} Tf 72 {$y} Td ({$texto}) Tj ET\n";
    }
}
