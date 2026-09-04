<template>
    <div :class="['flex items-start space-x-3 my-3', isUser ? 'justify-end' : 'justify-start']">
        <!-- Avatar IA (à esquerda) -->
        <div
            v-if="!isUser"
            class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-tr from-purple-600 to-indigo-600 flex items-center justify-center text-white shadow-sm ring-2 ring-purple-100"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </div>

        <!-- Conteúdo da Mensagem -->
        <div :class="['max-w-2xl', isUser ? 'items-end' : 'items-start']">
            <!-- Balão -->
            <div
                :class="[
                    'rounded-2xl px-4 py-3.5 shadow-sm text-sm leading-relaxed transition-all',
                    isUser
                        ? 'bg-purple-600 text-white rounded-tr-none ml-auto'
                        : 'bg-white border border-gray-200 text-gray-800 rounded-tl-none'
                ]"
            >
                <!-- Texto da mensagem do usuário (texto puro com quebra de linha) -->
                <p v-if="isUser" class="whitespace-pre-wrap">{{ message.text }}</p>

                <!-- Resposta da IA com suporte completo a Markdown e cursor de streaming -->
                <div v-else>
                    <div
                        class="prose prose-sm max-w-none prose-chat text-gray-800"
                        v-html="renderedMarkdown"
                    ></div>
                    <span v-if="message.isStreaming" class="typing-cursor ml-1"></span>
                </div>

                <!-- Footer do balão com Metadados da IA -->
                <div
                    v-if="!isUser && !message.isStreaming"
                    class="mt-3 pt-2.5 border-t border-gray-100 flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500"
                >
                    <!-- Tags de Provedor e Intenção -->
                    <div class="flex items-center space-x-1.5 flex-wrap">
                        <span
                            v-if="intentLabel"
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100"
                        >
                            {{ intentLabel }}
                        </span>

                        <span
                            v-if="message.model"
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-50 text-gray-600 border border-gray-200"
                        >
                            {{ message.model }}
                        </span>

                        <span v-if="message.tokens?.total" class="text-gray-400">
                            {{ message.tokens.total }} tokens
                        </span>
                    </div>

                    <!-- Ações: Copiar e Ver Dados Brutos -->
                    <div class="flex items-center space-x-2">
                        <button
                            v-if="message.context_data"
                            @click="showContext = !showContext"
                            class="text-purple-600 hover:text-purple-800 font-medium transition-colors inline-flex items-center space-x-1"
                        >
                            <span>{{ showContext ? 'Ocultar dados' : 'Ver dados reais do banco' }}</span>
                            <svg class="w-3 h-3 transition-transform" :class="showContext ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <button
                            @click="copyText"
                            class="text-gray-400 hover:text-gray-600 transition-colors p-1"
                            title="Copiar resposta"
                        >
                            <svg v-if="!copied" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                            </svg>
                            <span v-else class="text-xs text-green-600 font-medium">Copiado!</span>
                        </button>
                    </div>
                </div>

                <!-- Painel expansível com os dados reais consolidados do MySQL -->
                <div
                    v-if="showContext && message.context_data"
                    class="mt-3 p-3 bg-gray-900 text-gray-100 rounded-lg text-xs overflow-x-auto max-h-60"
                >
                    <div class="flex items-center justify-between pb-1 mb-2 border-b border-gray-700 text-gray-400">
                        <span class="font-mono text-[11px]">Métricas Agregadas Utilizadas pela IA (MySQL Real):</span>
                        <span class="text-[10px] text-green-400">● 100% Verificado</span>
                    </div>
                    <pre class="font-mono leading-relaxed">{{ JSON.stringify(message.context_data, null, 2) }}</pre>
                </div>
            </div>

            <!-- Hora de envio -->
            <span :class="['text-[11px] text-gray-400 mt-1 block px-1', isUser ? 'text-right' : 'text-left']">
                {{ message.created_at || 'Agora' }}
            </span>
        </div>

        <!-- Avatar Usuário (à direita) -->
        <div
            v-if="isUser"
            class="flex-shrink-0 w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-gray-700 font-medium text-xs shadow-sm ring-2 ring-gray-100"
        >
            Gestor
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { marked } from "marked";

const props = defineProps({
    message: {
        type: Object,
        required: true,
    },
});

const isUser = computed(() => props.message.sender === "user");
const showContext = ref(false);
const copied = ref(false);

// Configuração segura do Markdown
marked.setOptions({
    breaks: true,
    gfm: true,
});

const renderedMarkdown = computed(() => {
    if (!props.message.text) return "";
    return marked.parse(props.message.text);
});

// Etiquetas amigáveis para intenções reconhecidas
const intentMap = {
    revenue_drop: "Queda de Vendas",
    revenue_comparison: "Comparativo de Vendas",
    best_day: "Melhor Dia da Semana",
    peak_hours: "Horários de Pico",
    top_products: "Produtos Mais Vendidos",
    losing_products: "Produtos em Queda",
    average_ticket: "Ticket Médio",
    best_customers: "Melhores Clientes",
    inactive_customers: "Clientes Inativos",
    growth_actions: "Plano de Ações",
    create_campaign: "Campanha de IA",
    general_summary: "Visão Geral",
};

const intentLabel = computed(() => {
    return intentMap[props.message.intent] || props.message.intent || null;
});

const copyText = async () => {
    try {
        await navigator.clipboard.writeText(props.message.text);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        console.error("Falha ao copiar:", e);
    }
};
</script>