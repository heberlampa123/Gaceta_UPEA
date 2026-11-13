<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tareas programadas
|--------------------------------------------------------------------------
| Requieren que el servidor ejecute cada minuto: php artisan schedule:run
|   Linux (cron):   * * * * * cd /ruta/del/proyecto && php artisan schedule:run >> /dev/null 2>&1
|   Windows:        Programador de tareas → "php artisan schedule:run" cada minuto
| Ver docs/manuales/GUIA_INSTALACION_DESPLIEGUE.md
*/

// Respaldo diario de la base de datos y de los PDF (02:00, hora de Bolivia).
Schedule::command('gaceta:respaldo')->dailyAt('02:00')->withoutOverlapping()->onOneServer();
