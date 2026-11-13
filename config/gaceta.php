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

    // Documentos oficiales y sus archivos PDF (Fase 3).
    'documentos' => [
        // Tamaño máximo de cada PDF, en MB (también debe permitirlo PHP: upload_max_filesize y post_max_size).
        'pdf_max_mb' => (int) env('GACETA_PDF_MAX_MB', 20),
        // Disco de Laravel donde se guardan los PDF ("local" = storage/app/private, no accesible desde la web).
        'disco' => env('GACETA_DISCO_DOCUMENTOS', 'local'),
        'carpeta' => 'documentos',
        'instancia_emisora_predeterminada' => 'Honorable Consejo Universitario',
    ],

    // Respaldos de la base de datos y de los PDF (Fase 8): php artisan gaceta:respaldo
    'respaldos' => [
        // Carpeta donde se guardan (una subcarpeta por respaldo, con fecha y hora).
        'carpeta' => env('GACETA_RESPALDO_CARPETA', storage_path('app/respaldos')),
        // Días que se conservan los respaldos; los más antiguos se eliminan.
        'dias_retencion' => (int) env('GACETA_RESPALDO_DIAS', 30),
        // Desarrollo con Docker: nombre del contenedor de PostgreSQL (usa su pg_dump).
        // Producción: dejar vacío para usar pg_dump instalado en el servidor.
        'contenedor_docker' => env('GACETA_RESPALDO_CONTENEDOR'),
        'pg_dump' => env('GACETA_PG_DUMP', 'pg_dump'),
    ],

];
