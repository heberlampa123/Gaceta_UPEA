<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuardarTipoDocumentalRequest;
use App\Models\TipoDocumental;
use App\Support\Auditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Administración dinámica de tipos documentales (RF-05, Ref. 21).
 * Permite incorporar nuevos tipos y sus campos propios sin modificar la base de datos.
 * Los tipos no se eliminan: se desactivan (dejan de ofrecerse para nuevos documentos).
 */
class TipoDocumentalController extends Controller
{
    public function index(): View
    {
        return view('panel.tipos.index', [
            'tipos' => TipoDocumental::withCount('documentos')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('panel.tipos.create', ['tipo' => new TipoDocumental(['activo' => true])]);
    }

    public function store(GuardarTipoDocumentalRequest $request): RedirectResponse
    {
        $tipo = DB::transaction(function () use ($request) {
            $tipo = TipoDocumental::create($request->datosTipo());
            Auditor::creacion($tipo);

            return $tipo;
        });

        return redirect()->route('panel.tipos.index')->with('exito', "Se creó el tipo documental «{$tipo->nombre}».");
    }

    public function edit(TipoDocumental $tipo): View
    {
        return view('panel.tipos.edit', [
            'tipo' => $tipo,
            'documentos' => $tipo->documentos()->count(),
        ]);
    }

    public function update(GuardarTipoDocumentalRequest $request, TipoDocumental $tipo): RedirectResponse
    {
        DB::transaction(function () use ($request, $tipo) {
            $tipo->fill($request->datosTipo())->save();
            Auditor::modificacion($tipo);
        });

        return redirect()->route('panel.tipos.index')->with('exito', "Se actualizó el tipo documental «{$tipo->nombre}».");
    }
}
