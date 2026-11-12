<?php

namespace App\Http\Requests;

use App\Support\LimitesArchivo;
use Illuminate\Foundation\Http\FormRequest;

/** Validación de la carga de una nueva versión del PDF (RF-04). */
class NuevaVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agregarVersion', $this->route('documento'));
    }

    public function rules(): array
    {
        return [
            'archivo' => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.LimitesArchivo::pdfMaxKb()],
            'motivo_cambio' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'archivo' => 'archivo PDF',
            'motivo_cambio' => 'motivo del cambio',
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.max' => 'El archivo PDF no debe superar los '.config('gaceta.documentos.pdf_max_mb').' MB.',
            'archivo.uploaded' => 'No se pudo subir el archivo. Verifique que no supere '.LimitesArchivo::pdfMaxEfectivoMb().' MB.',
        ];
    }
}
