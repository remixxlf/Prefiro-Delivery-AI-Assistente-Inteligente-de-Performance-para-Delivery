<template>
    <div class="flex items-start space-x-3 my-4 animate-fade-in">
        <!-- Avatar IA -->
        <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white shadow-sm ring-2 ring-purple-100">
            <svg class="w-4 h-4 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>

        <!-- Balão de Loading -->
        <div class="bg-white border border-gray-200 rounded-2xl rounded-tl-none px-4 py-3 shadow-sm max-w-sm">
            <div class="flex items-center space-x-2">
                <span class="text-xs font-medium text-gray-500">Consultando métricas no banco e analisando com IA</span>
                <div class="flex space-x-1">
                    <span class="w-1.5 h-1.5 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                    <span class="w-1.5 h-1.5 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                    <span class="w-1.5 h-1.5 bg-purple-600 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                </div>
            </div>
            <div class="mt-2 h-1.5 w-full bg-purple-50 rounded-full overflow-hidden">
                <div class="h-full bg-purple-500 rounded-full w-1/3 animate-pulse"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
// Componente puramente visual
</script>