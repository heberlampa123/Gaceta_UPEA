{{--
    Una notificación de la campana. Se usa desde el servidor (con $n = NotificacionResource)
    y como <template> vacío que resources/js/notificaciones.js llena con la respuesta de Axios.
--}}
<a href="{{ $n['url'] ?? '#' }}" data-enlace="url" data-item
   @class(['list-group-item list-group-item-action d-flex gap-2 py-2', 'bg-primary-subtle' => isset($n['leida']) && ! $n['leida']])>
    <i class="bi bi-{{ $n['icono'] ?? 'bell' }} text-gaceta mt-1" data-icono></i>
    <span class="flex-grow-1 small">
        <strong class="d-block" data-campo="titulo">{{ $n['titulo'] ?? '' }}</strong>
        <span data-campo="mensaje">{{ $n['mensaje'] ?? '' }}</span>
        <span class="d-block text-body-secondary" data-campo="fecha_texto">{{ $n['fecha_texto'] ?? '' }}</span>
    </span>
</a>
