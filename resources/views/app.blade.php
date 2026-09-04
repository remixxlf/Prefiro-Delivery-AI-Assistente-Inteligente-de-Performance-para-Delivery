<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ config('app.name', 'Prefiro Delivery AI') }}</title>
    <meta name="description" content="Assistente Inteligente de Performance para Delivery" />

    {{-- Favicon --}}
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />

    {{-- Vite (CSS + JS) --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-gray-50 text-gray-900">
    <div id="app"></div>
</body>
</html>