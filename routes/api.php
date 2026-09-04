<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes — Prefiro Delivery AI
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Chat com IA ──────────────────────────────────────────────────────
    Route::prefix('chat')->group(function () {
        // Pergunta → IA → Resposta (JSON)
        Route::post('/', [ChatController::class, 'ask'])
            ->middleware('throttle:ai_chat')
            ->name('chat.ask');

        // Pergunta → IA → Resposta em streaming (SSE)
        Route::post('/stream', [ChatController::class, 'stream'])
            ->middleware('throttle:ai_chat')
            ->name('chat.stream');

        // Histórico de conversas
        Route::get('/history', [ChatController::class, 'history'])
            ->name('chat.history');

        // Deletar histórico da sessão
        Route::delete('/history', [ChatController::class, 'clearHistory'])
            ->name('chat.history.clear');
    });

    // ── Campanhas com IA ─────────────────────────────────────────────────
    Route::prefix('campaigns')->group(function () {
        Route::post('/', [CampaignController::class, 'generate'])
            ->middleware('throttle:ai_chat')
            ->name('campaigns.generate');
    });

    // ── Dashboard e Observabilidade ──────────────────────────────────────
    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary'])
            ->name('dashboard.summary');

        Route::get('/ai-observability', [DashboardController::class, 'observability'])
            ->name('dashboard.observability');
    });
});