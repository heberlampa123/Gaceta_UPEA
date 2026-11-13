@php
    // Los motivos propios del sistema (DocumentoPolicy) se muestran; el mensaje genérico de Laravel se traduce.
    $motivo = $exception->getMessage();
    $mensaje = $motivo && $motivo !== 'This action is unauthorized.' && $motivo !== 'Forbidden'
        ? $motivo
        : 'No tiene permiso para realizar esta acción.';
@endphp
@include('errors.plantilla', [
    'codigo' => 403,
    'icono' => 'shield-lock',
    'titulo' => 'Acceso no permitido',
    'mensaje' => $mensaje,
])
