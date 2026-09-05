<template>
    <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4 sm:p-6 animate-fade-in"
    >
        <div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl border border-gray-100 flex flex-col max-h-[90vh]">
            
            <!-- Header -->
            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-2xl">
                <h3 class="text-lg font-bold text-gray-900 flex items-center space-x-2">
                    <span class="w-8 h-8 rounded-lg bg-gradient-to-tr from-purple-600 to-indigo-600 text-white flex items-center justify-center shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                        </svg>
                    </span>
                    <span>Gerador de Campanhas com IA</span>
                </h3>
                <button @click="closePanel" class="text-gray-400 hover:text-gray-700 bg-white p-1.5 rounded-lg border border-transparent hover:border-gray-200 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Corpo -->
            <div class="p-6 overflow-y-auto flex-1">
                
                <!-- ESTADO 1: Formulário (Antes de Gerar) -->
                <div v-if="!resultData && !isGenerating" class="space-y-5 animate-fade-in">
                    <p class="text-sm text-gray-600 leading-relaxed">
                        O sistema consulta o banco de dados para identificar exatamente os clientes inativos e utiliza a IA para criar textos persuasivos de reativação (WhatsApp/SMS e Push).
                    </p>

                    <div class="space-y-4 bg-gray-50 p-5 rounded-xl border border-gray-100">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Clientes sem pedidos há mais de:
                            </label>
                            <select
                                v-model="days"
                                class="w-full text-sm border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 focus:outline-none transition-all shadow-sm"
                            >
                                <option :value="15">15 dias (Risco moderado)</option>
                                <option :value="30">30 dias (Inativos - Recomendado)</option>
                                <option :value="60">60 dias (Altamente Inativos)</option>
                                <option :value="90">90 dias (Quase Perdidos)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">
                                Objetivo ou oferta (Opcional):
                            </label>
                            <input
                                v-model="goal"
                                type="text"
                                placeholder="Ex: Oferecer frete grátis ou 10% de desconto"
                                class="w-full text-sm border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-purple-500 focus:border-purple-500 focus:outline-none transition-all shadow-sm"
                            />
                        </div>
                    </div>
                </div>

                <!-- ESTADO 2: Carregando -->
                <div v-else-if="isGenerating" class="py-12 flex flex-col items-center justify-center space-y-4 animate-fade-in">
                    <div class="relative w-16 h-16">
                        <div class="absolute inset-0 rounded-full border-4 border-gray-100"></div>
                        <div class="absolute inset-0 rounded-full border-4 border-purple-600 border-t-transparent animate-spin"></div>
                    </div>
                    <p class="text-gray-500 text-sm font-medium animate-pulse">Pesquisando clientes no banco e redigindo campanha...</p>
                </div>

                <!-- ESTADO 3: Resultado Gerado -->
                <div v-else-if="resultData" class="space-y-5 animate-fade-in">
                    <!-- Cards de Audiência -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div class="bg-indigo-50 border border-indigo-100 p-3 rounded-xl">
                            <span class="block text-[10px] uppercase font-bold text-indigo-500 mb-1">Segmento Mapeado</span>
                            <span class="text-xl font-black text-indigo-900">{{ resultData.audience.total_customers }}</span>
                            <span class="text-xs text-indigo-700 ml-1">clientes</span>
                        </div>
                        <div class="bg-green-50 border border-green-100 p-3 rounded-xl">
                            <span class="block text-[10px] uppercase font-bold text-green-600 mb-1">Ticket Médio</span>
                            <span class="text-xl font-black text-green-900">R$ {{ formatMoney(resultData.audience.average_historical_ticket) }}</span>
                        </div>
                        <div class="bg-orange-50 border border-orange-100 p-3 rounded-xl col-span-2">
                            <span class="block text-[10px] uppercase font-bold text-orange-600 mb-1">Potencial Recuperável (Mês)</span>
                            <span class="text-xl font-black text-orange-900">R$ {{ formatMoney(resultData.audience.potential_recoverable_value) }}</span>
                        </div>
                    </div>

                    <!-- Mensagem Gerada -->
                    <div class="bg-gray-50 border border-gray-200 rounded-xl overflow-hidden shadow-sm">
                        <div class="bg-gray-100 px-4 py-2 border-b border-gray-200 flex justify-between items-center">
                            <span class="text-xs font-bold text-gray-600 uppercase tracking-wider flex items-center space-x-1.5">
                                <svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <span>Rascunho da Campanha</span>
                            </span>
                            <span class="text-[10px] text-gray-400 bg-white px-2 py-0.5 rounded border border-gray-200">{{ resultData.campaign.model }}</span>
                        </div>
                        <div class="p-4 prose prose-sm max-w-none prose-chat text-gray-800" v-html="renderedMarkdown"></div>
                    </div>
                </div>
            </div>

            <!-- Footer (Ações) -->
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 rounded-b-2xl flex justify-end space-x-3">
                <button
                    v-if="!isGenerating"
                    @click="closePanel"
                    class="px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-200 rounded-xl transition-colors"
                >
                    {{ resultData ? 'Fechar' : 'Cancelar' }}
                </button>
                
                <button
                    v-if="!resultData && !isGenerating"
                    @click="handleGenerate"
                    class="px-5 py-2.5 text-sm font-bold text-white bg-purple-600 hover:bg-purple-700 rounded-xl shadow-md hover:shadow-lg transition-all flex items-center space-x-2"
                >
                    <span>Gerar com IA</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </button>

                <button
                    v-if="resultData"
                    @click="copyCampaign"
                    class="px-5 py-2.5 text-sm font-bold text-white bg-green-600 hover:bg-green-700 rounded-xl shadow-md transition-all flex items-center space-x-2"
                >
                    <svg v-if="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                    </svg>
                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ copied ? 'Copiado!' : 'Copiar Texto' }}</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useChatStore } from "@/stores/chatStore";
import { marked } from "marked";

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    }
});

const emit = defineEmits(["close"]);

const chatStore = useChatStore();
const days = ref(30);
const goal = ref("");
const isGenerating = ref(false);
const resultData = ref(null);
const copied = ref(false);

const closePanel = () => {
    // Reset state before closing
    setTimeout(() => {
        days.value = 30;
        goal.value = "";
        resultData.value = null;
        isGenerating.value = false;
        copied.value = false;
    }, 200);
    emit("close");
};

const handleGenerate = async () => {
    isGenerating.value = true;
    try {
        const result = await chatStore.generateCampaign(days.value, goal.value || null);
        if (result) {
            resultData.value = result;
        }
    } finally {
        isGenerating.value = false;
    }
};

const formatMoney = (val) => {
    if (!val) return "0,00";
    return Number(val).toLocaleString("pt-BR", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const renderedMarkdown = computed(() => {
    if (!resultData.value?.campaign?.generated_text) return "";
    return marked.parse(resultData.value.campaign.generated_text);
});

const copyCampaign = async () => {
    if (!resultData.value?.campaign?.generated_text) return;
    try {
        await navigator.clipboard.writeText(resultData.value.campaign.generated_text);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch (e) {
        console.error(e);
    }
};
</script>