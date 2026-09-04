<template>
    <div v-if="message" class="my-3 p-3.5 bg-red-50 border border-red-200 rounded-xl flex items-start justify-between space-x-3 text-red-800 shadow-sm animate-fade-in">
        <div class="flex items-start space-x-2.5">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="text-xs md:text-sm">
                <p class="font-semibold text-red-900">Atenção</p>
                <p class="text-red-700 mt-0.5 leading-relaxed">{{ message }}</p>
            </div>
        </div>

        <button
            @click="$emit('dismiss')"
            class="text-red-400 hover:text-red-600 p-1 rounded-lg hover:bg-red-100 transition-colors"
            title="Fechar aviso"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
</template>

<script setup>
defineProps({
    message: {
        type: String,
        default: null,
    },
});

defineEmits(["dismiss"]);
</script>