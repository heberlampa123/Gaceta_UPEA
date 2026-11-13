{{--
    Insignia del estado del documento, con color suave según su código (estilos .estado-* en resources/scss/app.scss).
    Uso: @include('panel.documentos._estado', ['estado' => $documento->estadoActual])
--}}
<span class="badge estado estado-{{ strtolower($estado?->codigo ?? 'ninguno') }} @unless ($estado) badge-suave @endunless">{{ $estado?->nombre ?? '—' }}</span>
