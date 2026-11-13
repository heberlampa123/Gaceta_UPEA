<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarDocumentoRequest;
use App\Models\Documento;
use App\Models\EstadoDocumento;
use App\Models\TipoDocumental;
use App\Models\TipoRelacionDocumental;
use App\Services\GestionDocumental;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Gestión documental: registro, consulta y modificación de documentos (RF-02, Ref. 7–12). */
class DocumentoController extends Controller
{
    public function __construct(private readonly GestionDocumental $gestion) {}

    public function index(Request $request): View
    {
        $filtros = $request->only(['buscar', 'estado', 'gestion', 'tipo']);

        $documentos = Documento::query()
            ->activos()
            ->with(['tipoDocumental', 'estadoActual', 'usuarioRegistro'])
            // Marca «No vigente» en el listado (normas abrogadas por otra ya publicada).
            ->when(Documento::relacionesHabilitadas(), fn (Builder $q) => $q->withExists([
                'relacionesRecibidas as abrogado' => fn (Builder $r) => $r->efectivas()->queQuitanVigencia(),
            ]))
            ->when($filtros['buscar'] ?? null, fn (Builder $q, string $texto) => $q->where(fn (Builder $w) => $w
                ->buscar($texto)
                ->orWhere('numero', 'ilike', '%'.$texto.'%')))
            ->when($filtros['estado'] ?? null, fn (Builder $q, string $estado) => $q->enEstado($estado))
            ->when($filtros['gestion'] ?? null, fn (Builder $q, $gestion) => $q->where('gestion', (int) $gestion))
            ->when($filtros['tipo'] ?? null, fn (Builder $q, $tipo) => $q->where('id_tipo_documental', (int) $tipo))
            ->orderByDesc('fecha_registro')
            ->orderByDesc('id_documento')
            ->paginate(15)
            ->withQueryString();

        return view('panel.documentos.index', [
            'documentos' => $documentos,
            'filtros' => $filtros,
            'estados' => EstadoDocumento::orderBy('orden')->get(),
            'tipos' => TipoDocumental::orderBy('nombre')->get(),
            'gestiones' => Documento::activos()->distinct()->orderByDesc('gestion')->pluck('gestion'),
        ]);
    }

    public function create(): View
    {
        $tipos = TipoDocumental::activos()->orderBy('nombre')->get();

        return view('panel.documentos.create', [
            'documento' => new Documento([
                'id_tipo_documental' => $tipos->firstWhere('codigo', TipoDocumental::RES_HCU)?->id_tipo_documental,
                'gestion' => now()->year,
                'instancia_emisora' => config('gaceta.documentos.instancia_emisora_predeterminada'),
            ]),
            'tipos' => $tipos,
        ]);
    }

    public function store(GuardarDocumentoRequest $request): RedirectResponse
    {
        $documento = $this->gestion->registrar($request->datosDocumento(), $request->file('archivo'), $request->user());

        return redirect()->route('panel.documentos.show', $documento)
            ->with('exito', "Se registró {$documento->nombreCorto()} en estado Borrador.");
    }

    public function show(Documento $documento): View
    {
        $documento->load([
            'tipoDocumental',
            'estadoActual',
            'usuarioRegistro',
            'versionVigente.usuarioCarga',
            'versiones' => fn ($q) => $q->with('usuarioCarga')->reorder()->orderByDesc('numero_version'),
            'seguimientos.usuario',
            'seguimientos.estadoNuevo',
            'seguimientos.version',
            'observaciones.revisor',
            'observaciones.usuarioAtiende',
            'observaciones.version',
        ]);

        if (Documento::relacionesHabilitadas()) {
            $documento->load([
                'relacionesEmitidas' => fn ($q) => $q->with(['tipo', 'destino.tipoDocumental', 'origen.estadoActual']),
                // Las normas anuladas (borradores registrados por error) no afectan a nadie.
                'relacionesRecibidas' => fn ($q) => $q->whereHas('origen', fn ($o) => $o->activos())
                    ->with(['tipo', 'origen.tipoDocumental', 'origen.estadoActual']),
            ]);
        }

        return view('panel.documentos.show', [
            'documento' => $documento,
            'tiposRelacion' => TipoRelacionDocumental::activos()->get(),
        ]);
    }

    public function edit(Documento $documento): View
    {
        $this->authorize('update', $documento);

        return view('panel.documentos.edit', [
            'documento' => $documento,
            'tipos' => TipoDocumental::activos()->orWhere('id_tipo_documental', $documento->id_tipo_documental)->orderBy('nombre')->get(),
        ]);
    }

    /** Anula un borrador registrado por error (corrección 13/11/2026). */
    public function anular(Request $request, Documento $documento): RedirectResponse
    {
        $this->autorizarEnFlujo('anular', $documento);
        $datos = $request->validate(
            ['motivo' => ['required', 'string', 'min:5', 'max:300']],
            ['motivo.required' => 'Indique el motivo de la anulación.'],
            ['motivo' => 'motivo'],
        );

        $this->gestion->anular($documento, $datos['motivo'], $request->user());

        return redirect()->route('panel.documentos.index')
            ->with('exito', "Se anuló el borrador {$documento->numero}. Su número quedó disponible.");
    }

    public function update(GuardarDocumentoRequest $request, Documento $documento): RedirectResponse
    {
        $this->gestion->actualizar($documento, $request->datosDocumento());

        return redirect()->route('panel.documentos.show', $documento)->with('exito', 'Se actualizaron los datos del documento.');
    }
}
