<?php

use Illuminate\Support\Facades\Schedule;

// Limpar histórico de conversas antigas (mais de 30 dias)
Schedule::command('chat:prune-history --days=30')
    ->daily()
    ->at('02:00')
    ->runInBackground();