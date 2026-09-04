<template>
    <div class="border-t border-gray-200 bg-white p-3 md:p-4 rounded-b-2xl shadow-sm">
        <!-- Barra de ferramentas superior ao input -->
        <div class="flex items-center justify-between mb-2 px-1 text-xs text-gray-500">
            <!-- Toggle de Streaming (Diferencial) -->
            <button
                @click="chatStore.toggleStreaming()"
                type="button"
                class="inline-flex items-center space-x-1.5 hover:text-gray-800 transition-colors"
                title="Habilitar ou desabilitar resposta em tempo real (streaming)"
            >
                <span
                    :class="[
                        'w-2 h-2 rounded-full',
                        chatStore.useStreamingMode ? 'bg-green-500 animate-pulse' : 'bg-gray-300'
                    ]"
                ></span>
                <span class="font-medium">
                    Streaming: {{ chatStore.useStreamingMode ? 'Ativo (tempo real)' : 'Desativado' }}
                </span>
            </button>

            <!-- Contador de caracteres -->
            <span :class="['font-mono', question.length > 450 ? 'text-red-500 font-bold' : 'text-gray-400']">
                {{ question.length }}/500
            </span>
        </div>

        <!-- Formulário de Envio -->
        <form @submit.prevent="handleSubmit" class="relative flex items-end space-x-2">
            <div class="relative flex-1">
                <textarea
                    ref="textareaRef"
                    v-model="question"
                    rows="1"
                    maxlength="500"
                    placeholder="Faça uma pergunta sobre suas vendas (ex: 'Por que minhas vendas caíram este mês?')..."
                    class="w-full resize-none rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-800 placeholder-gray-400 focus:border-purple-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-purple-200 transition-all leading-normal"
                    :disabled="isDisabled"
                    @keydown="handleKeyDown"
                    @input="autoResize"
                ></textarea>
            </div>

            <!-- Botão de Enviar -->
            <button
                type="submit"
                :disabled="isDisabled || !canSubmit"
                :class="[
                    'flex-shrink-0 inline-flex items-center justify-center w-11 h-11 rounded-xl text-white shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-offset-2',
                    canSubmit && !isDisabled
                        ? 'bg-purple-600 hover:bg-purple-700 hover:shadow-md cursor-pointer'
                        : 'bg-gray-300 cursor-not-allowed opacity-60'
                ]"
                title="Enviar pergunta (Enter)"
            >
                <!-- Spinner se estiver carregando -->
                <svg
                    v-if="isDisabled"
                    class="w-5 h-5 animate-spin"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>

                <!-- Ícone de envio -->
                <svg
                    v-else
                    class="w-5 h-5 transform rotate-90 translate-x-0.5"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                </svg>
            </button>
        </form>

        <!-- Dica de atalho de teclado -->
        <div class="hidden md:flex justify-between items-center mt-1.5 px-1 text-[11px] text-gray-400">
            <span>Pressione <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-300 rounded text-[10px]">Enter</kbd> para enviar, <kbd class="px-1 py-0.5 bg-gray-100 border border-gray-300 rounded text-[10px]">Shift + Enter</kbd> para quebrar linha</span>
            <span>Prefiro Delivery • IA Segura</span>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick } from "vue";
import { useChatStore } from "@/stores/chatStore";

const chatStore = useChatStore();
const question = ref("");
const textareaRef = ref(null);

const isDisabled = computed(() => chatStore.isLoading || chatStore.isStreaming);
const canSubmit = computed(() => question.value.trim().length >= 2);

const autoResize = () => {
    const el = textareaRef.value;
    if (!el) return;
    el.style.height = "auto";
    el.style.height = Math.min(el.scrollHeight, 120) + "px";
};

const handleKeyDown = (e) => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        handleSubmit();
    }
};

const handleSubmit = async () => {
    if (!canSubmit.value || isDisabled.value) return;

    const text = question.value.trim();
    question.value = "";

    // Reseta altura do textarea
    if (textareaRef.value) {
        textareaRef.value.style.height = "auto";
    }

    await chatStore.sendMessage(text);
};

// Permite preencher pergunta externamente (chips de sugestão)
const setQuestion = (text) => {
    question.value = text;
    nextTick(() => {
        autoResize();
        textareaRef.value?.focus();
    });
};

defineExpose({ setQuestion });
</script>