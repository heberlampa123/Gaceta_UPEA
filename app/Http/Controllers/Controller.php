<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // Permite $this->authorize('update', $documento) en los controladores.
    use AuthorizesRequests;
}
