{{--
    Relaciones con otras normas en la ficha pública (13/11/2026). Solo se muestran las que ya surtieron
    efecto. Las normas abrogadas no son visibles en el portal, por eso se nombran sin enlace.
--}}
@php($emitidas = $documento->relacionesEmitidas)
@php($recibidas = $documento->relacionesRecibidas)

@if ($emitidas->isNotEmpty() || $recibidas->isNotEmpty())
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-body"><h2 class="h6 mb-0"><i class="bi bi-diagram-3 me-1"></i> Relación con otras normas</h2></div>
        <ul class="list-group list-group-flush small">
            @foreach ($recibidas as $relacion)
                <li class="list-group-item">
                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">{{ $relacion->tipo->nombre_inverso }}</span>
                    <a href="{{ route('portal.documentos.show', $relacion->origen) }}">{{ $relacion->origen->nombreCorto() }}</a>
                    @if ($relacion->detalle) <div class="text-body-secondary">{{ $relacion->detalle }}</div> @endif
                </li>
            @endforeach
            @foreach ($emitidas as $relacion)
                <li class="list-group-item">
                    <span class="badge badge-suave">{{ $relacion->tipo->nombre }}</span>
                    @if ($relacion->destino->esVigente())
                        <a href="{{ route('portal.documentos.show', $relacion->destino) }}">{{ $relacion->destino->nombreCorto() }}</a>
                    @else
                        {{ $relacion->destino->nombreCorto() }} <span class="text-body-secondary">(sin vigencia)</span>
                    @endif
                    @if ($relacion->detalle) <div class="text-body-secondary">{{ $relacion->detalle }}</div> @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
