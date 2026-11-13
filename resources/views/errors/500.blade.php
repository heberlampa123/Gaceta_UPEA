@include('errors.plantilla', [
    'codigo' => 500,
    'icono' => 'exclamation-triangle',
    'titulo' => 'Error del servidor',
    'mensaje' => 'Ocurrió un problema inesperado. Si persiste, comuníquese con el administrador del sistema.',
])
