# 📋 Resumo Executivo: O que o Sistema Realiza

> **Prefiro Delivery AI — Assistente Inteligente de Performance para Delivery**  
> Documento síntese das funcionalidades, fluxo de processamento, regras de negócio e diferenciais do projeto.

---

## 💡 1. Propósito da Solução

O **Prefiro Delivery AI** transforma a rotina do gestor de delivery. Em vez de obrigá-lo a extrair relatórios, cruzar planilhas e tentar decifrar gráficos confusos, o sistema disponibiliza um **consultor de negócios em tempo real**.

O gestor conversa em linguagem natural e a aplicação:
1. Identifica exatamente a dúvida operacional ou financeira.
2. Consulta o banco de dados MySQL de forma controlada.
3. Extrai métricas agregadas consolidadas (sem enviar registros brutos de clientes).
4. Aciona a Inteligência Artificial para interpretar os números, diagnosticar gargalos e formular planos de ação práticos.
5. Transmite a análise palavra por palavra na tela via **Streaming (Server-Sent Events)**.

---

## 🔄 2. O Ciclo de Inteligência do Sistema (Fluxo de Dados)

```
[Gestor faz uma pergunta]
          │
          ▼
[1. Sanitização & Segurança] ────▶ Detecta Prompt/SQL Injection e limpa tags HTML
          │
          ▼
[2. IntentResolver] ─────────────▶ Mapeia a intenção (ex: 'revenue_drop', 'peak_hours')
          │
          ▼
[3. Cache Redis Check] ──────────▶ Pergunta já feita na última hora? Se sim, responde em 1ms com $0 de custo
          │ (se não estiver em cache)
          ▼
[4. ContextBuilder] ─────────────▶ Aciona métodos específicos do AnalyticsService
          │
          ▼
[5. Consulta ao MySQL] ──────────▶ Eloquent/Query Builder calcula métricas agregadas reais
          │                        (faturamento, delta percentual, ticket médio, churn)
          ▼
[6. PromptBuilder] ──────────────▶ Monta o prompt com diretriz inegociável:
          │                        "NUNCA invente números. Use apenas o contexto fornecido."
          ▼
[7. Chamada ao Provedor de IA] ──▶ OpenAI (gpt-4o-mini) / Gemini / Anthropic / Motor Local
          │
          ▼
[8. Streaming em Tempo Real] ────▶ SSE transmite pedaços de texto para o Vue.js com efeito de digitação
          │
          ▼
[9. Auditoria & Cache] ──────────▶ Salva em ai_conversations, loga no arquivo ai.log e cacheia no Redis
```

---

## 📊 3. Principais Análises que o Sistema Realiza

### A. Diagnóstico de Queda e Variação de Faturamento
- **O que faz:** Compara o faturamento do mês atual com o mês anterior e aponta a causa raiz da variação.
- **Métricas avaliadas:**
  - Faturamento total do mês atual vs anterior (com variação percentual).
  - Quantidade de pedidos entregues (identifica se a queda foi por falta de pedidos).
  - Ticket médio atual vs anterior (identifica se os clientes gastaram menos por pedido).
  - Churn recente: quantos clientes compraram no mês passado e ainda não pediram neste mês.
  - Categoria com maior impacto negativo nas vendas (ex: *Marmitas Tradicionais caíram 31,2%*).

### B. Identificação do Melhor e Pior Dia da Semana
- **O que faz:** Analisa a série histórica de pedidos agrupada por dia da semana (Domingo a Sábado).
- **Métricas avaliadas:**
  - Volume de pedidos e faturamento consolidado por dia.
  - Participação percentual de cada dia no faturamento geral.
  - Identificação clara do dia de pico (ex: *Sexta-feira*) e do dia mais fraco (ex: *Segunda-feira*).
  - Sugestões de ofertas temáticas para alavancar os dias de baixo movimento.

### C. Horários de Pico da Operação
- **O que faz:** Mapeia a distribuição temporal de pedidos hora a hora (00h às 23h).
- **Métricas avaliadas:**
  - Horário exato de pico máximo de expedição (ex: *12h00 no almoço* ou *20h00 no jantar*).
  - Comparativo de volume entre os turnos de Almoço, Tarde e Jantar.
  - Recomendações operacionais de pré-preparo de insumos antes do pico.

### D. Ranking e Desempenho de Produtos
- **O que faz:** Avalia a curva ABC do cardápio do restaurante.
- **Métricas avaliadas:**
  - Top 5 produtos campeões de vendas (unidades vendidas, faturamento gerado e preço unitário).
  - Produtos em declínio acentuado de vendas em relação ao mês anterior.
  - Sugestões estratégicas de combos ou reposicionamento de itens no cardápio.

### E. Análise de Ticket Médio e Estratégias de Upselling
- **O que faz:** Monitora o valor médio gasto por pedido entregue e sugere formas de expansão da margem.
- **Métricas avaliadas:**
  - Ticket médio atual e comparativo histórico.
  - Produtos de alta margem e tíquete elevado com potencial de cross-selling (ex: combos e sobremesas).

### F. Saúde da Base de Clientes e Segmentação RFM
- **O que faz:** Monitora a recência e frequência de compra de toda a carteira de clientes.
- **Métricas avaliadas:**
  - Segmentação em 4 níveis: **Ativos** (últimos 30 dias), **Em Risco** (31 a 60 dias), **Inativos** (61 a 90 dias) e **Perdidos** (>90 dias).
  - Identificação dos clientes com maior valor histórico acumulado (Top Clientes).
  - Cálculo do potencial financeiro em risco com base no tíquete histórico dos inativos.

### G. Plano de Ação Semanal ("3 Ações para Alavancar Vendas")
- **O que faz:** Sintetiza os gargalos identificados no banco em um plano de ação executivo de 3 passos para o gestor implementar imediatamente na operação.

---

## 📢 4. Ação Prática com IA: Gerador de Campanhas

O sistema vai além das respostas teóricas e entrega uma **ferramenta prática de marketing**:

1. **Seleção do Segmento:** O gestor escolhe a janela de inatividade (15, 30, 60 ou 90 dias sem comprar).
2. **Varredura no Banco:** O sistema executa query no MySQL e apresenta:
   - Quantidade exata de clientes encontrados no segmento.
   - Ticket médio histórico desse grupo.
   - Potencial de receita recuperável se esses clientes retornarem.
3. **Geração com IA:** O modelo de IA formula:
   - **Mensagem para WhatsApp / SMS:** Texto acolhedor, conciso, com gatilhos mentais e chamada para ação clara.
   - **Notificação Push para Aplicativo:** Mensagem curta, instigante, pronta para disparo em apps de delivery.
   - **Incentivo Comercial Sugerido:** Sugestão de condição especial (ex: cupom ou entrega grátis) calculada para não corroer a margem do restaurante.
4. **Cópia em 1 Clique:** Botão interativo para copiar o texto formatado.

---

## 🛡️ 5. Blindagem de Segurança e Confiabilidade

| Pilar de Segurança | Como o Sistema Garante |
|---|---|
| **Anti-Alucinação** | A IA não inventa números; 100% dos dados quantitativos são pré-computados no banco antes da chamada. |
| **Proteção contra SQL Injection** | A IA não escreve SQL. Todas as consultas usam Eloquent Scopes e PDO Parameter Bindings. |
| **Proteção contra Prompt Injection** | O `IntentResolver` inspeciona a pergunta e bloqueia comandos como *"ignore previous instructions"* ou instruções de sobrescrita. |
| **Proteção contra Vazamento de Erros** | O Exception Handler do Laravel suprime stack traces em respostas de API, retornando JSON seguro para HTTP 422, 404, 429 e 500. |
| **Proteção contra Abuso (Rate Limit)** | Limite de 20 requisições por minuto por IP via middleware `throttle:ai_chat`. |
| **Isolamento de Credenciais** | O arquivo `.env` está estritamente ignorado no `.gitignore` e nenhuma chave trafega no frontend. |

---

## ⚡ 6. Desempenho e Observabilidade

- **Cache Redis em 2 Camadas:**
  - *Perguntas Frequentes:* Perguntas idênticas em janela de 1h são servidas do Redis com **latência de ~1ms e custo $0.00**.
  - *Métricas do Banco:* Consultas pesadas de consolidação são cacheadas por 15 minutos em `AnalyticsService`.
- **Auditoria Imutável:**
  - Cada interação é gravada na tabela `ai_conversations` (pergunta, contexto JSON, prompt enviado, resposta, modelo, tokens de entrada/saída, custo em USD e tempo em ms).
- **Logs Estruturados:**
  - Logs diários em `storage/logs/ai.log` e canal de falhas em `storage/logs/ai_errors.log`.
- **Endpoint de Métricas da IA:**
  - `GET /api/v1/dashboard/ai-observability` consolida estatísticas de uso para monitoramento executivo.
- **Motor Grounded de Fallback:**
  - Caso a OpenAI fique offline ou o sistema seja executado sem internet/chaves externas, o motor analítico local responde fundamentado nos dados reais do MySQL sem quebrar a experiência do usuário.

---

## 🖥️ 7. Experiência do Usuário no Frontend (Vue.js 3)

- **Streaming em Tempo Real (SSE):** Resposta digitada dinamicamente na tela com cursor pulsante.
- **Renderização Markdown:** Formatação rica com negritos, listas, tabelas e avisos visuais.
- **Gaveta de Auditoria ("Ver dados reais do banco"):** Cada resposta possui um botão que abre o payload JSON original extraído do MySQL, provando a fidelidade dos dados.
- **Cards de KPI no Topo:** Faturamento do mês, total de pedidos, ticket médio e tamanho da base de clientes sempre visíveis.
- **Chips Interativos de Perguntas:** Sugestões rápidas com 1 clique para iniciar a exploração.
- **Totalmente Responsivo:** Otimizado para desktop e dispositivos móveis.

---

## 📦 8. Como Executar o Sistema

```bash
# 1. Iniciar containers (PHP 8.3, Nginx, MySQL 8, Redis 7.2)
docker compose up -d

# 2. Configurar banco e compilar frontend
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app npm install && npm run build

# 3. Acessar no navegador
http://localhost:8000
```