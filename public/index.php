<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determina se a aplicação está em manutenção
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
try {
    (require_once __DIR__.'/../bootstrap/app.php')
        ->handleRequest(Illuminate\Http\Request::capture());
} catch (\Throwable $e) {
    http_response_code(500);
    echo "<h1>CRITICAL ERROR TRACE</h1>";
    echo "<b>Message:</b> " . $e->getMessage() . "<br><br>";
    echo "<b>File:</b> " . $e->getFile() . ":" . $e->getLine() . "<br><br>";
    echo "<b>Trace:</b><br><pre>" . $e->getTraceAsString() . "</pre>";
    exit(1);
}