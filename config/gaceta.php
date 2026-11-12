<?php

/*
| Configuración propia del Sistema de la Gaceta Universitaria (UPEA).
*/

return [

    // Cuenta del administrador inicial creada por AdministradorSeeder.
    // La contraseña es temporal: el sistema obliga a cambiarla en el primer inicio de sesión.
    'admin' => [
        'nombres' => env('ADMIN_NOMBRES', 'Administrador'),
        'apellidos' => env('ADMIN_APELLIDOS', 'del Sistema'),
        'correo' => env('ADMIN_CORREO', 'admin@upea.edu.bo'),
        'nombre_usuario' => env('ADMIN_USUARIO', 'admin'),
        'contrasena' => env('ADMIN_CONTRASENA'),
    ],

];
