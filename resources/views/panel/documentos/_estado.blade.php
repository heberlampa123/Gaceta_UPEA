{{-- Insignia de color según el código del estado. Uso: @include('panel.documentos._estado', ['estado' => $documento->estadoActual]) --}}
@php
    $colores = [
        'BORRADOR' => 'text-bg-secondary',
        'PENDIENTE_REVISION' => 'text-bg-warning',
        'OBSERVADO' => 'text-bg-danger',
        'APROBADO' => 'text-bg-info',
        'PUBLICADO' => 'text-bg-success',
    ];
@endphp
<span class="badge {{ $colores[$estado?->codigo] ?? 'text-bg-light border' }}">{{ $estado?->nombre ?? '—' }}</span>
