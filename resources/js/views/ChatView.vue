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

                        <!-- Botão Ação Prática: Gerar Campanha -->
                        <button
                            @click="isCampaignPanelOpen = true"
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

        <!-- Painel de Geração de Campanhas com IA -->
        <CampaignPanel 
            :is-open="isCampaignPanelOpen" 
            @close="isCampaignPanelOpen = false" 
        />
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useChatStore } from "@/stores/chatStore";
import ChatWindow from "@/components/ChatWindow.vue";
import CampaignPanel from "@/components/CampaignPanel.vue";

const chatStore = useChatStore();
const chatWindowRef = ref(null);
const isCampaignPanelOpen = ref(false);

const metrics = computed(() => chatStore.dashboardMetrics);

const formatMoney = (val) => {
    if (!val) return "0,00";
    return Number(val).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
</script>