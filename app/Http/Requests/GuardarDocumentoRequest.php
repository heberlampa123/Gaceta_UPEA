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
            // Tipo activo, o el tipo que el documento ya tiene aunque se haya desactivado después.
            'id_tipo_documental' => ['required', 'integer', Rule::exists('tipo_documental', 'id_tipo_documental')
                ->where(fn ($q) => $q->where('activo', true)->orWhere('id_tipo_documental', $documento?->id_tipo_documental ?? 0))],
            'numero' => [
                'required', 'string', 'max:30', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-\/\.]*$/',
                Rule::unique('documento', 'numero')
                    ->where('id_tipo_documental', $this->input('id_tipo_documental'))
                    ->where('gestion', $this->input('gestion'))
                    ->where('activo', true) // los documentos anulados liberan su número
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

    /**
     * Coherencia entre gestión, fecha de emisión y número (corrección 13/11/2026):
     *  - el año de la fecha de emisión debe ser el de la gestión;
     *  - si el número incluye el año ("025/2026"), debe coincidir con la gestión.
     */
    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->hasAny(['gestion', 'fecha_emision', 'numero'])) {
                return;
            }

            $gestion = (int) $this->input('gestion');
            $anioFecha = (int) substr((string) $this->input('fecha_emision'), 0, 4);

            if ($anioFecha !== $gestion) {
                $validator->errors()->add('fecha_emision', "La fecha de emisión ({$anioFecha}) no corresponde a la gestión {$gestion}.");
            }

            if (preg_match('/[\/\-](\d{4})$/', (string) $this->input('numero'), $m) && (int) $m[1] !== $gestion) {
                $validator->errors()->add('numero', "El número indica el año {$m[1]}, pero la gestión es {$gestion}.");
            }
        }];
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
