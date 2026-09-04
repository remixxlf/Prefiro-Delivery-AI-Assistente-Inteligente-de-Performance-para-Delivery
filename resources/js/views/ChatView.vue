<template>
    <div class="min-h-screen bg-gray-100 flex flex-col font-sans">
        <!-- Top Navigation Bar -->
        <nav class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Logo / Marca -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-600 flex items-center justify-center text-white font-black text-lg shadow-md">
                            PD
                        </div>
                        <div>
                            <span class="text-base font-bold text-gray-900 tracking-tight">Prefiro Delivery</span>
                            <span class="ml-2 text-xs font-semibold px-2 py-0.5 bg-purple-100 text-purple-700 rounded-full">Assistente IA</span>
                        </div>
                    </div>

                    <!-- Status e Ações Rápidas -->
                    <div class="flex items-center space-x-3">
                        <!-- Indicador de status -->
                        <div class="hidden sm:flex items-center space-x-1.5 text-xs text-gray-500 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200">
                            <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                            <span>Base de dados sincronizada</span>
                        </div>

                        <!-- Botão Ação Prática: Gerar Campanha (Parte 8) -->
                        <button
                            @click="openCampaignModal"
                            class="inline-flex items-center space-x-1.5 px-3 py-2 text-xs font-semibold rounded-lg bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white shadow-sm transition-all"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                            </svg>
                            <span>Nova Campanha com IA</span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- KPI Metrics Bar (Dados do Dashboard) -->
        <div v-if="metrics" class="bg-white border-b border-gray-200 py-3 shadow-xs">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div class="px-3 py-2 bg-gray-50 rounded-xl border border-gray-100">
                        <span class="text-[11px] font-medium text-gray-500 block uppercase">Faturamento Mês</span>
                        <span class="text-sm md:text-base font-bold text-gray-900">
                            R$ {{ formatMoney(metrics.current_month?.revenue) }}
                        </span>
                    </div>

                    <div class="px-3 py-2 bg-gray-50 rounded-xl border border-gray-100">
                        <span class="text-[11px] font-medium text-gray-500 block uppercase">Pedidos Entregues</span>
                        <span class="text-sm md:text-base font-bold text-gray-900">
                            {{ metrics.current_month?.orders_count ?? 0 }} pedidos
                        </span>
                    </div>

                    <div class="px-3 py-2 bg-gray-50 rounded-xl border border-gray-100">
                        <span class="text-[11px] font-medium text-gray-500 block uppercase">Ticket Médio</span>
                        <span class="text-sm md:text-base font-bold text-gray-900">
                            R$ {{ formatMoney(metrics.current_month?.average_ticket) }}
                        </span>
                    </div>

                    <div class="px-3 py-2 bg-gray-50 rounded-xl border border-gray-100">
                        <span class="text-[11px] font-medium text-gray-500 block uppercase">Clientes na Base</span>
                        <span class="text-sm md:text-base font-bold text-purple-700">
                            {{ metrics.customer_health?.total_customers ?? 0 }} clientes
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 max-w-5xl w-full mx-auto p-3 sm:p-5 flex flex-col" style="height: calc(100vh - 8rem)">
            <ChatWindow ref="chatWindowRef" />
        </div>

        <!-- Modal de Ação Rápida de Campanha (Parte 8) -->
        <div
            v-if="showCampaignModal"
            class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/50 backdrop-blur-xs flex items-center justify-center p-4 animate-fade-in"
        >
            <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-gray-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-base font-bold text-gray-900 flex items-center space-x-2">
                        <span class="w-8 h-8 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center">
                            📢
                        </span>
                        <span>Gerar Campanha com IA</span>
                    </h3>
                    <button @click="showCampaignModal = false" class="text-gray-400 hover:text-gray-600">
                        ✕
                    </button>
                </div>

                <p class="text-xs text-gray-600 mb-4 leading-relaxed">
                    O sistema consulta o banco de dados para identificar exatamente os clientes inativos e utiliza a IA para produzir o texto persuasivo de reativação.
                </p>

                <div class="space-y-3 mb-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Clientes sem comprar há mais de:
                        </label>
                        <select
                            v-model="campaignDays"
                            class="w-full text-sm border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-purple-200 focus:outline-none"
                        >
                            <option :value="15">15 dias (Em risco)</option>
                            <option :value="30">30 dias (Recomendado - Inativos)</option>
                            <option :value="60">60 dias</option>
                            <option :value="90">90 dias (Perdidos)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Objetivo da mensagem (opcional):
                        </label>
                        <input
                            v-model="campaignGoal"
                            type="text"
                            placeholder="Ex: Oferecer frete grátis para matar a saudade"
                            class="w-full text-sm border border-gray-300 rounded-lg p-2.5 focus:ring-2 focus:ring-purple-200 focus:outline-none"
                        />
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button
                        @click="showCampaignModal = false"
                        class="px-4 py-2 text-xs font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="confirmGenerateCampaign"
                        :disabled="chatStore.isLoading"
                        class="px-4 py-2 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 rounded-lg shadow-sm"
                    >
                        Gerar Campanha Agora
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useChatStore } from "@/stores/chatStore";
import ChatWindow from "@/components/ChatWindow.vue";

const chatStore = useChatStore();
const chatWindowRef = ref(null);

const showCampaignModal = ref(false);
const campaignDays = ref(30);
const campaignGoal = ref("");

const metrics = computed(() => chatStore.dashboardMetrics);

const formatMoney = (val) => {
    if (!val) return "0,00";
    return Number(val).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const openCampaignModal = () => {
    showCampaignModal.value = true;
};

const confirmGenerateCampaign = async () => {
    showCampaignModal.value = false;
    await chatStore.generateCampaign(campaignDays.value, campaignGoal.value || null);
};
</script>