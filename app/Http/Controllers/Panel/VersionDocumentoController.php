<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\NuevaVersionRequest;
use App\Models\Documento;
use App\Models\VersionDocumento;
use App\Services\GestionDocumental;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Versiones del PDF: carga, visualización, descarga y verificación de integridad (RF-04, Ref. 13–14). */
class VersionDocumentoController extends Controller
{
    public function __construct(private readonly GestionDocumental $gestion) {}

    public function store(NuevaVersionRequest $request, Documento $documento): RedirectResponse
    {
        $version = $this->gestion->agregarVersion(
            $documento,
            $request->file('archivo'),
            $request->validated('motivo_cambio'),
            $request->user(),
        );

        return redirect()->route('panel.documentos.show', $documento)
            ->with('exito', "Se cargó la versión {$version->numero_version}. Ahora es la versión vigente.");
    }

    /** Muestra el PDF en el navegador o lo descarga (?descargar=1). Los archivos nunca son públicos. */
    public function archivo(Request $request, Documento $documento, VersionDocumento $version): StreamedResponse
    {
        abort_unless($version->id_documento === $documento->id_documento, 404);

        $disco = $this->gestion->disco();
        abort_unless($disco->exists($version->ruta_archivo), 404, 'El archivo no se encuentra en el servidor.');

        // Ej.: res-hcu-025-2026_v2.pdf
        $nombre = Str::slug($documento->tipoDocumental->codigo.' '.str_replace('/', '-', $documento->numero))
            .'_v'.$version->numero_version.'.pdf';

        return $disco->response(
            $version->ruta_archivo,
            $nombre,
            ['Content-Type' => 'application/pdf', 'X-Content-Type-Options' => 'nosniff'],
            $request->boolean('descargar') ? 'attachment' : 'inline',
        );
    }

    public function verificar(Documento $documento, VersionDocumento $version): RedirectResponse
    {
        abort_unless($version->id_documento === $documento->id_documento, 404);

        return match ($this->gestion->verificarIntegridad($version)) {
            true => back()->with('exito', "Versión {$version->numero_version}: el archivo está íntegro (la huella SHA-256 coincide con la registrada)."),
            false => back()->with('error', "Versión {$version->numero_version}: ¡el archivo fue alterado! Su huella SHA-256 no coincide con la registrada."),
            null => back()->with('error', "Versión {$version->numero_version}: el archivo no se encuentra en el servidor."),
        };
    }
}
