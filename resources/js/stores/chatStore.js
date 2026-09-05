import { defineStore } from "pinia";
import axios from "axios";

export const useChatStore = defineStore("chat", {
    state: () => ({
        messages: [],
        sessionId: null,
        isLoading: false,
        isStreaming: false,
        error: null,
        suggestedPrompts: [],
        dashboardMetrics: null,
        useStreamingMode: true, // Diferencial habilitado por padrão
    }),

    getters: {
        hasMessages: (state) => state.messages.length > 0,
        lastMessage: (state) => state.messages[state.messages.length - 1] ?? null,
    },

    actions: {
        /**
         * Inicializa a sessão a partir do localStorage ou cria um UUID novo.
         */
        initSession() {
            let sid = localStorage.getItem("prefiro_session_id");
            if (!sid) {
                sid = "sess_" + Math.random().toString(36).substring(2, 15) + "_" + Date.now().toString(36);
                localStorage.setItem("prefiro_session_id", sid);
            }
            this.sessionId = sid;
        },

        /**
         * Carrega histórico prévio da sessão a partir da API.
         */
        async fetchHistory() {
            if (!this.sessionId) this.initSession();

            try {
                const response = await axios.get("/chat/history", {
                    params: { session_id: this.sessionId },
                });

                if (response.data?.status === "success" && Array.isArray(response.data.data)) {
                    this.messages = response.data.data;
                }
            } catch (err) {
                console.error("Falha ao carregar histórico do chat:", err);
            }
        },

        /**
         * Carrega métricas do dashboard e sugestões de perguntas.
         */
        async fetchDashboard() {
            try {
                const response = await axios.get("/dashboard/summary");
                if (response.data?.status === "success") {
                    this.dashboardMetrics = response.data.data.metrics ?? null;
                    this.suggestedPrompts = response.data.data.suggested_prompts ?? [];
                }
            } catch (err) {
                console.error("Falha ao carregar dados do dashboard:", err);
            }
        },

        /**
         * Envia pergunta para o assistente de IA.
         */
        async sendMessage(questionText) {
            const trimmed = questionText?.trim();
            if (!trimmed || this.isLoading || this.isStreaming) return;

            this.error = null;
            if (!this.sessionId) this.initSession();

            // Adiciona mensagem do usuário na timeline
            const userMsgId = "user_" + Date.now();
            this.messages.push({
                id: userMsgId,
                sender: "user",
                text: trimmed,
                created_at: new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }),
            });

            if (this.useStreamingMode) {
                await this.sendStreamingMessage(trimmed);
            } else {
                await this.sendStandardMessage(trimmed);
            }
        },

        /**
         * Envio padrão síncrono (JSON).
         */
        async sendStandardMessage(questionText) {
            this.isLoading = true;

            try {
                const response = await axios.post("/chat", {
                    question: questionText,
                    session_id: this.sessionId,
                });

                if (response.data?.status === "success") {
                    const d = response.data.data;
                    this.messages.push({
                        id: "ai_" + Date.now(),
                        sender: "assistant",
                        text: d.response,
                        intent: d.intent,
                        context_data: d.context_data,
                        provider: d.provider,
                        model: d.model,
                        tokens: d.tokens,
                        cost_usd: d.cost_usd,
                        created_at: new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }),
                    });
                } else {
                    this.error = response.data?.message || "Erro desconhecido ao processar sua pergunta.";
                }
            } catch (err) {
                this.handleApiError(err);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Envio via Streaming SSE (Diferencial).
         */
        async sendStreamingMessage(questionText) {
            this.isStreaming = true;

            // Cria mensagem placeholder na timeline para receber os pedaços de texto
            const aiMsgIndex = this.messages.length;
            this.messages.push({
                id: "ai_" + Date.now(),
                sender: "assistant",
                text: "",
                isStreaming: true,
                intent: null,
                context_data: null,
                provider: null,
                model: null,
                tokens: null,
                cost_usd: 0,
                created_at: new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }),
            });

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                const apiBase = import.meta.env.VITE_API_URL ?? "/api/v1";

                const response = await fetch(`${apiBase}/chat/stream`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "text/event-stream",
                        "X-Requested-With": "XMLHttpRequest",
                        ...(csrfToken ? { "X-CSRF-TOKEN": csrfToken } : {}),
                    },
                    body: JSON.stringify({
                        question: questionText,
                        session_id: this.sessionId,
                    }),
                });

                if (!response.ok) {
                    throw new Error(`HTTP Error ${response.status}`);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder("utf-8");
                let buffer = "";

                while (true) {
                    const { done, value } = await reader.read();
                    if (done) break;

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split("\n\n");
                    buffer = lines.pop() || "";

                    for (const block of lines) {
                        const trimmedBlock = block.trim();
                        if (!trimmedBlock) continue;

                        const eventMatch = trimmedBlock.match(/^event:\s*(\w+)/m);
                        const dataMatch = trimmedBlock.match(/^data:\s*(.+)$/m);

                        const eventType = eventMatch ? eventMatch[1] : "chunk";
                        const rawData = dataMatch ? dataMatch[1] : null;

                        if (eventType === "chunk" && rawData) {
                            try {
                                const parsed = JSON.parse(rawData);
                                if (parsed.content) {
                                    this.messages[aiMsgIndex].text += parsed.content;
                                }
                            } catch (e) {
                                // Ignore non-json chunk
                            }
                        } else if (eventType === "done") {
                            this.messages[aiMsgIndex].isStreaming = false;
                        }
                    }
                }
            } catch (err) {
                console.warn("Falha no streaming SSE, acionando fallback síncrono:", err);
                // Se falhou antes de receber texto, executa fallback síncrono
                if (!this.messages[aiMsgIndex].text) {
                    this.messages.splice(aiMsgIndex, 1);
                    await this.sendStandardMessage(questionText);
                }
            } finally {
                if (this.messages[aiMsgIndex]) {
                    this.messages[aiMsgIndex].isStreaming = false;
                }
                this.isStreaming = false;
            }
        },

        /**
         * Dispara a ação prática de geração de campanha.
         */
        async generateCampaign(days = 30, goal = null) {
            this.isLoading = true;
            this.error = null;

            try {
                const response = await axios.post("/campaigns", {
                    days,
                    goal,
                    session_id: this.sessionId,
                });

                if (response.data?.status === "success") {
                    const d = response.data.data;
                    this.messages.push({
                        id: "ai_camp_" + Date.now(),
                        sender: "assistant",
                        text: d.campaign.generated_text,
                        intent: "create_campaign",
                        context_data: d.audience,
                        provider: d.campaign.provider,
                        model: d.campaign.model,
                        tokens: d.campaign.tokens,
                        cost_usd: 0,
                        created_at: new Date().toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" }),
                    });
                    return d;
                }
            } catch (err) {
                this.handleApiError(err);
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Limpa todo o histórico de conversas da sessão.
         */
        async clearHistory() {
            if (!this.sessionId) return;

            try {
                await axios.delete("/chat/history", {
                    params: { session_id: this.sessionId },
                });
                this.messages = [];
            } catch (err) {
                console.error("Falha ao limpar histórico:", err);
            }
        },

        /**
         * Tratamento seguro e amigável de erros de API.
         */
        handleApiError(err) {
            if (err.response?.status === 429) {
                this.error = "Limite de requisições por minuto atingido. Por favor, aguarde alguns segundos antes de perguntar novamente.";
            } else if (err.response?.status === 422) {
                const errors = err.response.data?.errors;
                const firstMsg = errors ? Object.values(errors)[0]?.[0] : null;
                this.error = firstMsg || "Dados da requisição inválidos.";
            } else {
                this.error = err.response?.data?.message || "Não foi possível conectar ao servidor de IA. Verifique sua conexão e tente novamente.";
            }
        },

        dismissError() {
            this.error = null;
        },

        toggleStreaming() {
            this.useStreamingMode = !this.useStreamingMode;
        },
    },
});