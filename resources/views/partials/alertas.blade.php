@foreach (['exito' => 'success', 'error' => 'danger', 'aviso' => 'warning', 'estado' => 'info'] as $clave => $color)
    @if (session($clave))
        <div class="alert alert-{{ $color }} alert-dismissible fade show" role="alert">
            {{ session($clave) }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    @endif
@endforeach

@if ($temporal = session('contrasena_temporal'))
    <div class="alert alert-warning" role="alert">
        <h2 class="h6 alert-heading"><i class="bi bi-key me-1"></i> Contraseña temporal</h2>
        <p class="mb-2">
            Entregue estos datos al usuario. <strong>Se muestran solo esta vez</strong>;
            el sistema le pedirá cambiar la contraseña al ingresar.
        </p>
        <div class="d-flex flex-wrap gap-3 font-monospace">
            <span>Usuario: <strong>{{ $temporal['usuario'] }}</strong></span>
            <span>Contraseña: <strong>{{ $temporal['contrasena'] }}</strong></span>
        </div>
    </div>
@endif
