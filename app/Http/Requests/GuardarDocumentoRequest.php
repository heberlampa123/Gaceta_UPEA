<?php

namespace App\Http\Requests;

use App\Models\Documento;
use App\Models\TipoDocumental;
use App\Support\LimitesArchivo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validación del registro y la edición de documentos (Ref. 8: validación de datos del documento). */
class GuardarDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Documento|null $documento */
        $documento = $this->route('documento');

        return $documento
            ? $this->user()->can('update', $documento)
            : $this->user()->can('documento.registrar');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero' => trim((string) $this->input('numero')),
            'titulo' => trim((string) $this->input('titulo')),
        ]);
    }

    public function rules(): array
    {
        /** @var Documento|null $documento */
        $documento = $this->route('documento');

        return [
            ...$this->tipoSeleccionado()?->reglasMetadatos() ?? [],
            'metadatos' => ['nullable', 'array'],
            'id_tipo_documental' => ['required', 'integer', Rule::exists('tipo_documental', 'id_tipo_documental')->where('activo', true)],
            'numero' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/\.]*$/',
                Rule::unique('documento', 'numero')
                    ->where('id_tipo_documental', $this->input('id_tipo_documental'))
                    ->where('gestion', $this->input('gestion'))
                    ->ignore($documento?->id_documento, 'id_documento'),
            ],
            'gestion' => ['required', 'integer', 'between:2000,'.(now()->year + 1)],
            'fecha_emision' => ['required', 'date', 'before_or_equal:today'],
            'titulo' => ['required', 'string', 'max:300'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'instancia_emisora' => ['required', 'string', 'max:150'],
            // El PDF se adjunta al registrar; después se cambia cargando una nueva versión.
            'archivo' => $documento
                ? ['prohibited']
                : ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:'.LimitesArchivo::pdfMaxKb()],
        ];
    }

    public function attributes(): array
    {
        return [
            ...$this->tipoSeleccionado()?->atributosMetadatos() ?? [],
            'id_tipo_documental' => 'tipo documental',
            'numero' => 'número',
            'gestion' => 'gestión',
            'fecha_emision' => 'fecha de emisión',
            'titulo' => 'título',
            'descripcion' => 'descripción',
            'instancia_emisora' => 'instancia emisora',
            'archivo' => 'archivo PDF',
        ];
    }

    public function messages(): array
    {
        return [
            'numero.unique' => 'Ya existe un documento de este tipo con el número :input en la gestión indicada.',
            'numero.regex' => 'El número solo puede contener letras, números, barra (/), guion (-) y punto. Ej.: 025/2026',
            'archivo.max' => 'El archivo PDF no debe superar los '.config('gaceta.documentos.pdf_max_mb').' MB.',
            'archivo.uploaded' => 'No se pudo subir el archivo. Verifique que no supere '.LimitesArchivo::pdfMaxEfectivoMb().' MB.',
        ];
    }

    /**
     * Datos validados, con los metadatos filtrados y convertidos según los campos
     * adicionales del tipo documental elegido (Fase 4).
     */
    public function datosDocumento(): array
    {
        $datos = $this->validated();
        $datos['metadatos'] = $this->tipoSeleccionado()?->normalizarMetadatos($datos['metadatos'] ?? []);

        return $datos;
    }

    private function tipoSeleccionado(): ?TipoDocumental
    {
        return once(fn () => TipoDocumental::find((int) $this->input('id_tipo_documental')));
    }
}
