<template>
    <div class="flex flex-col h-full bg-gray-50 border border-gray-200 rounded-2xl shadow-xl overflow-hidden">
        <!-- Cabeçalho do Chat -->
        <header class="flex items-center justify-between px-5 py-3.5 bg-white border-b border-gray-200 shadow-sm flex-shrink-0">
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white font-bold shadow-sm">
                        PD
                    </div>
                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 bg-green-500 border-2 border-white rounded-full"></span>
                </div>
                <div>
                    <h2 class="text-sm font-bold text-gray-900 flex items-center space-x-1.5">
                        <span>Assistente de Performance</span>
                        <span class="text-[10px] uppercase tracking-wider font-semibold px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded">IA em tempo real</span>
                    </h2>
                    <p class="text-xs text-gray-500">Prefiro Delivery • Consultoria com dados reais do MySQL</p>
                </div>
            </div>

            <!-- Ações do Header -->
            <div class="flex items-center space-x-2">
                <button
                    v-if="chatStore.hasMessages"
                    @click="confirmClear"
                    type="button"
                    class="text-xs text-gray-500 hover:text-red-600 px-2.5 py-1.5 rounded-lg border border-gray-200 hover:border-red-200 hover:bg-red-50 transition-colors flex items-center space-x-1"
                    title="Limpar histórico da conversa"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    <span class="hidden sm:inline">Limpar</span>
                </button>
            </div>
        </header>

        <!-- Corpo com Mensagens (Scrollável) -->
        <main
            ref="scrollContainer"
            class="flex-1 overflow-y-auto px-4 md:px-6 py-4 space-y-3"
        >
            <!-- Tela de Boas-Vindas quando não há mensagens -->
            <div v-if="!chatStore.hasMessages" class="max-w-2xl mx-auto my-6 text-center animate-fade-in">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-purple-100 flex items-center justify-center text-purple-600 shadow-inner">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                </div>

                <h3 class="text-lg font-bold text-gray-900 mb-1">
                    Olá, Gestor! O que gostaria de analisar hoje?
                </h3>
                <p class="text-xs md:text-sm text-gray-600 mb-6 max-w-lg mx-auto">
                    Converse com a IA em linguagem natural para entender tendências de vendas, identificar quedas, descobrir horários de pico e receber recomendações estratégicas fundamentadas nos dados reais do seu restaurante.
                </p>

                <!-- Cards com Sugestões de Perguntas -->
                <div class="text-left mb-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-2 px-1">
                        Perguntas frequentes sugeridas:
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <button
                            v-for="(prompt, index) in defaultPrompts"
                            :key="index"
                            @click="selectPrompt(prompt)"
                            class="p-3 text-left bg-white hover:bg-purple-50/70 border border-gray-200 hover:border-purple-300 rounded-xl text-xs md:text-sm text-gray-700 hover:text-purple-900 shadow-xs transition-all flex items-start space-x-2 group"
                        >
                            <span class="text-purple-500 font-bold group-hover:translate-x-0.5 transition-transform">→</span>
                            <span class="flex-1 font-medium">{{ prompt }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Lista de Mensagens do Chat -->
            <div v-else class="space-y-3">
                <MessageBubble
                    v-for="msg in chatStore.messages"
                    :key="msg.id"
                    :message="msg"
                />
            </div>

            <!-- Indicador de Carregamento (Loading) -->
            <LoadingIndicator v-if="chatStore.isLoading || (chatStore.isStreaming && !hasStreamingContent)" />

            <!-- Mensagem de Erro (se houver) -->
            <ErrorMessage
                :message="chatStore.error"
                @dismiss="chatStore.dismissError()"
            />
        </main>

        <!-- Barra de Entrada de Texto -->
        <footer class="flex-shrink-0">
            <InputBar ref="inputBarRef" />
        </footer>
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted } from "vue";
import { useChatStore } from "@/stores/chatStore";
import MessageBubble from "./MessageBubble.vue";
import InputBar from "./InputBar.vue";
import LoadingIndicator from "./LoadingIndicator.vue";
import ErrorMessage from "./ErrorMessage.vue";

const chatStore = useChatStore();
const scrollContainer = ref(null);
const inputBarRef = ref(null);

const defaultPrompts = computed(() => {
    if (chatStore.suggestedPrompts && chatStore.suggestedPrompts.length > 0) {
        return chatStore.suggestedPrompts.slice(0, 6);
    }
    return [
        "Por que minhas vendas caíram este mês?",
        "Quanto vendi este mês? Meu faturamento aumentou ou diminuiu?",
        "Qual foi meu melhor dia da semana?",
        "Qual horário possui maior volume de pedidos?",
        "Qual produto mais vendeu?",
        "Me dê três ações que eu poderia executar esta semana para aumentar minhas vendas.",
        "Crie uma campanha para clientes que não compram há mais de 30 dias.",
    ];
});

const hasStreamingContent = computed(() => {
    const last = chatStore.lastMessage;
    return last && last.isStreaming && last.text && last.text.length > 0;
});

const scrollToBottom = async () => {
    await nextTick();
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
};

// Rola automaticamente sempre que mensagens mudam ou streaming avança
watch(
    () => [chatStore.messages.length, chatStore.lastMessage?.text],
    () => {
        scrollToBottom();
    },
    { deep: true }
);

const selectPrompt = (text) => {
    if (inputBarRef.value) {
        inputBarRef.value.setQuestion(text);
    }
};

const confirmClear = async () => {
    if (confirm("Deseja realmente limpar todo o histórico desta conversa?")) {
        await chatStore.clearHistory();
    }
};

onMounted(async () => {
    chatStore.initSession();
    await Promise.all([
        chatStore.fetchHistory(),
        chatStore.fetchDashboard(),
    ]);
    scrollToBottom();
});
</script>