# 🚀 Prefiro Delivery AI — Assistente Inteligente de Performance para Delivery

> **Teste Técnico — Desenvolvedor Full Stack + IA | Prefiro Delivery**  
> Solução completa, segura e escalável para análise de performance de restaurantes delivery via Inteligência Artificial, construída com **Laravel 11**, **Vue.js 3**, **MySQL 8** e **Redis**.

---

## 📑 Sumário Executivo

- [1. Visão Geral](#1-visão-geral)
- [2. Arquitetura da Solução](#2-arquitetura-da-solução)
- [3. Modelo de IA e Justificativa](#3-modelo-de-ia-e-justificativa)
- [4. Estratégia de Contexto Seguro (A Regra Fundamental)](#4-estratégia-de-contexto-seguro-a-regra-fundamental)
- [5. Guia de Instalação e Execução](#5-guia-de-instalação-e-execução)
  - [Opção A: Com Docker Compose (Recomendado)](#opção-a-com-docker-compose-recomendado)
  - [Opção B: Localmente sem Docker](#opção-b-localmente-sem-docker)
  - [Executando os Testes Automatizados](#executando-os-testes-automatizados)
- [6. Guia de Deploy em Produção](#6-guia-de-deploy-em-produção)
- [7. Respostas às 10 Perguntas Obrigatórias](#7-respostas-às-10-perguntas-obrigatórias)
- [8. Diferenciais Implementados](#8-diferenciais-implementados)

---

## 1. Visão Geral

O **Prefiro Delivery AI** é uma plataforma que atua como um consultor executivo para gestores de restaurantes. Em vez de exigir que o gestor filtre planilhas complexas, o sistema permite perguntas em linguagem natural como:
- *"Por que minhas vendas caíram este mês?"*
- *"Qual foi meu melhor dia da semana?"*
- *"Qual produto está perdendo vendas?"*
- *"Me dê três ações para alavancar minhas vendas esta semana."*
- *"Crie uma campanha para clientes que não compram há mais de 30 dias."*

A aplicação analisa os dados reais do banco, compila métricas pré-agregadas sob medida e as envia para o modelo de IA interpretar e formular diagnósticos estratégicos e planos de ação.

---

## 2. Arquitetura da Solução

O sistema adota os princípios de **Clean Architecture** e **Separation of Concerns**, garantindo que regras de negócio, persistência e IA não se misturem:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        FRONTEND (Vue 3 + Pinia)                        │
│   ChatWindow ── MessageBubble (Markdown) ── InputBar ── CampaignPanel  │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │ HTTP / Server-Sent Events (SSE)
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│                       BACKEND (Laravel 11 API)                         │
│  Controllers (Chat, Campaign, Dashboard) ── Form Requests (Validação)  │
│  Rate Limiting (throttle:ai_chat) ── Exception Handler Seguro          │
└───────────────┬───────────────────────────────┬────────────────────────┘
                │                               │
                ▼                               ▼
┌───────────────────────────────┐ ┌──────────────────────────────────────┐
│       CAMADA ANALÍTICA        │ │            CAMADA DE IA              │
│  AnalyticsService             │ │  IntentResolver (Sanitização & Regex)│
│  OrderRepository              │ │  ContextBuilder (Métricas Restritas) │
│  CustomerRepository           │ │  PromptBuilder (Regras Anti-Alucinação│
│  ProductRepository            │ │  AIService (OpenAI / Gemini / Fallback│
└───────────────┬───────────────┘ └──────────────────┬───────────────────┘
                │                                    │
                ▼                                    ▼
┌───────────────────────────────┐ ┌──────────────────────────────────────┐
│        BANCO DE DADOS         │ │          CACHE & AUDITORIA           │
│  MySQL 8 (InnoDB + Índices)   │ │  Redis (Cache de Perguntas & Métricas)│
│  530+ Pedidos com Sazonalidade│ │  ai_conversations (Auditoria Imutável│
│  Customers, Products, Orders  │ │  storage/logs/ai.log (Log Estruturado│
└───────────────────────────────┘ └──────────────────────────────────────┘
```

### Principais Componentes:
- **Frontend SPA (Vue 3 + Vite + Tailwind CSS)**:
  - Comunicação reativa via **Pinia Store** (`chatStore.js`).
  - Suporte nativo a **Streaming em Tempo Real** via `ReadableStream` e decodificação UTF-8.
  - Renderização segura de Markdown via biblioteca `marked`.
  - Drawer transparente *"Ver dados reais do banco"* em cada balão para auditoria visual.
- **Backend (Laravel 11 RESTful)**:
  - Form Requests isolados (`ChatAskRequest`, `GenerateCampaignRequest`).
  - `StreamedResponse` do Symfony/Laravel para eventos SSE (`event: chunk`, `event: done`).
  - Camada de Repositórios portável (MySQL e SQLite em memória para testes).
- **Camada de Inteligência Artificial (`app/Services/AI/`)**:
  - `IntentResolver`: Mapeador semântico com detecção ativa de Prompt/SQL Injection.
  - `ContextBuilder`: Extrator estrito de métricas mínimas.
  - `PromptBuilder`: Construtor de prompts blindados com diretrizes anti-alucinação.
  - `AIService`: Driver multimodelo, orquestrador de streaming, cálculo de tokens/custos e motor analítico grounded de fallback.

---

## 3. Modelo de IA e Justificativa

### Modelo Principal: OpenAI `gpt-4o-mini`
- **Custo-Benefício Excepcional**: \$0.00015 / 1K tokens de entrada e \$0.00060 / 1K tokens de saída (~30x mais econômico que o GPT-4 tradicional).
- **Baixíssima Latência**: Tempo de resposta médio inferior a 1.5s, ideal para streaming contínuo no chat.
- **Alta Aderência a Restrições**: Segue rigorosamente a instrução de basear-se apenas nos dados fornecidos sem alucinar valores.

### Suporte Multimodelo Nativo
O sistema foi arquitetado para permitir troca imediata de provedor via arquivo `.env` sem alterar nenhuma linha de código:
- **Google Gemini**: `gemini-1.5-flash` (`AI_PROVIDER=gemini`)
- **Anthropic**: `claude-3-haiku-20240307` (`AI_PROVIDER=anthropic`)

### Motor Analítico Grounded de Fallback
Se nenhuma chave de API estiver configurada no `.env` (ou se os provedores externos estiverem indisponíveis), o `AIService` aciona um **motor determinístico baseado em regras de negócio**. Ele utiliza exatamente os mesmos dados agregados do MySQL e produz diagnósticos completos, permitindo que qualquer avaliador teste a aplicação imediatamente.

---

## 4. Estratégia de Contexto Seguro (A Regra Fundamental)

> *"Não queremos uma solução onde todos os registros do banco sejam enviados indiscriminadamente para a Inteligência Artificial."* — Diretriz Oficial do Teste

### Como a pergunta se transforma em consulta restrita:

1. **Recepção e Sanitização**: A pergunta é limpa contra bytes nulos, tags HTML e comandos suspeitos.
2. **Identificação da Intenção**: O `IntentResolver` categoriza a pergunta em uma intenção de negócio controlada (ex: `revenue_drop`).
3. **Consulta Restrita e Parametrizada**: O `ContextBuilder` invoca exclusivamente os métodos do `AnalyticsService` necessários para aquela intenção.
4. **Métricas Pré-Computadas**: Apenas números consolidados e percentuais são montados no JSON (faturamento atual vs anterior, variação percentual de pedidos, ticket médio, clientes inativos, categoria com maior queda).
5. **Prompt Blindado**: O `PromptBuilder` anexa o JSON e impõe a regra inegociável: *"Você NÃO PODE inventar números. Toda métrica deve vir exclusivamente do bloco de contexto."*

#### Exemplo Prático: *"Por que minhas vendas caíram?"*
A IA **NÃO** recebe milhares de linhas de pedidos. Ela recebe apenas este payload compacto:
```json
{
  "financial_comparison": {
    "current": { "total_revenue": 14250.00, "delivered_orders": 285, "average_ticket": 50.00 },
    "previous": { "total_revenue": 18900.00, "delivered_orders": 378, "average_ticket": 50.00 },
    "difference": { "revenue_growth_percent": -24.6, "orders_growth_percent": -24.6, "ticket_diff": 0.00 }
  },
  "customer_churn": { "churned_customers_count": 42 },
  "category_performance": { "biggest_drop": { "category": "Marmitas Tradicionais", "revenue_growth_percent": -31.2 } },
  "inactive_customers_30d": 83
}
```
Com base nisso, a IA responde com precisão milimétrica:
> *"Seu faturamento caiu **24,6%** em relação ao mês anterior. O principal fator foi a redução de **24,6%** no volume de pedidos (de 378 para 285 pedidos), enquanto o ticket médio se manteve estável em R$ 50,00. Além disso, 42 clientes que compraram no mês passado ainda não pediram neste mês, e a categoria 'Marmitas Tradicionais' foi a mais afetada (-31,2%)."*

---

## 5. Guia de Instalação e Execução

### Pré-requisitos
- Git
- Docker e Docker Compose (para Opção A) **OU** PHP 8.2+, Composer e Node.js 18+ (para Opção B)

---

### Opção A: Com Docker Compose (Recomendado)

1. **Clone o repositório**:
   ```bash
   git clone https://github.com/Remixxlf/prefiro-delivery.git
   cd prefiro-delivery
   ```

2. **Copie o arquivo de ambiente**:
   ```bash
   cp .env.example .env
   ```
   *(Opcional: insira sua `OPENAI_API_KEY` no `.env`. Caso não insira, o sistema operará perfeitamente via Motor Grounded de Fallback).*

3. **Inicie os containers**:
   ```bash
   docker compose up -d
   ```
   *Isto iniciará: PHP 8.3-FPM (`prefiro_app`), Nginx (`prefiro_nginx`), MySQL 8 (`prefiro_mysql`), Redis 7.2 (`prefiro_redis`) e o Queue Worker (`prefiro_queue`).*

4. **Instale as dependências e popule o banco de dados**:
   ```bash
   docker compose exec app composer install
   docker compose exec app php artisan key:generate
   docker compose exec app php artisan migrate --seed
   docker compose exec app npm install
   docker compose exec app npm run build
   ```

5. **Acesse a aplicação**:
   Abra no seu navegador: **`http://localhost:8000`**

---

### Opção B: Localmente sem Docker

1. **Instale as dependências PHP e Node**:
   ```bash
   composer install
   npm install
   ```

2. **Configure o ambiente**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Configure o Banco de Dados no `.env`** (MySQL ou SQLite):
   Para SQLite rápido:
   ```env
   DB_CONNECTION=sqlite
   DB_DATABASE=database/database.sqlite
   ```
   *(Crie o arquivo vazio `database/database.sqlite` se necessário).*

4. **Execute as migrations com os seeders realistas**:
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Inicie os servidores**:
   Terminal 1 (Backend):
   ```bash
   php artisan serve --port=8000
   ```
   Terminal 2 (Frontend):
   ```bash
   npm run build # ou npm run dev
   ```

---

### Executando os Testes Automatizados

O projeto conta com uma suíte abrangente de testes cobrindo Repositórios, Serviços, IA, Validações de API, Segurança e Cache:

```bash
# Com Docker:
docker compose exec app php artisan test

# Local:
php artisan test
```

Suíte de testes incluída:
- `MigrationSchemaTest`: Verifica tabelas, foreign keys e índices.
- `SeederDataTest`: Valida a sazonalidade e proporções dos dados gerados.
- `OrderRepositoryTest`, `CustomerRepositoryTest`, `ProductRepositoryTest`: Testam queries agregadas.
- `AnalyticsServiceTest`: Valida detecção de churn, quedas e cálculo de tickets.
- `IntentResolverTest`: Valida detecção de intenções e bloqueio de Prompt Injection.
- `ContextBuilderTest`: Garante integridade do payload de métricas.
- `PromptBuilderTest`: Garante salvaguardas anti-alucinação.
- `AIServiceTest`: Testa orquestração, streaming e auditoria.
- `ChatApiTest`: Testa endpoints `/api/v1/chat`, `/chat/stream` e `/chat/history`.
- `CampaignApiTest`: Testa segmentação de público e geração de campanhas.
- `SecurityLogsAndCacheTest`: Testa cache Redis, endpoint de observabilidade e supressão de stack traces.

---

## 6. Guia de Deploy em Produção

A aplicação foi preparada para deploy contínuo em plataformas modernas de nuvem:

### Deploy no Railway / Render / Fly.io:
1. Conecte o repositório GitHub à plataforma.
2. Defina as variáveis de ambiente essenciais:
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `APP_KEY=base64:...` (gerada via `php artisan key:generate --show`)
   - `DB_CONNECTION=mysql` (conecte o add-on de MySQL da plataforma)
   - `CACHE_STORE=redis` (conecte o add-on de Redis)
   - `OPENAI_API_KEY=sk-...`
3. Comando de Build:
   ```bash
   composer install --no-dev --optimize-autoloader && npm install && npm run build
   ```
4. Comando de Inicialização (Start Command):
   ```bash
   php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=$PORT
   ```

---

## 7. Respostas às 10 Perguntas Obrigatórias

### 1. Como você garante que a IA não invente números?
**Resposta:**  
Adotamos uma abordagem de **Grounding Arquitetural Estrito**:
1. **Nenhum cálculo é delegado à IA**: Todas as grandezas quantitativas (faturamento, pedidos, ticket médio, percentual de churn, contagem de clientes inativos e rankings) são computadas no banco de dados via Laravel Eloquent/Query Builder antes da chamada à IA.
2. **System Prompt com Salvaguarda Inegociável**: O modelo recebe instruções categóricas que definem que todo dado quantitativo deve vir exclusivamente do bloco de contexto JSON fornecido.
3. **Transparência e Auditabilidade**: Cada balão de resposta inclui os metadados brutos enviados (`context_data`), permitindo que o gestor expanda e confira se cada número citado pela IA bate exatamente com o banco de dados.

---

### 2. Como impedir que uma pergunta maliciosa execute comandos perigosos no banco de dados?
**Resposta:**  
A proteção opera em três camadas independentes:
1. **Zero SQL Dinâmico Gerado por IA**: A IA nunca recebe privilégios para gerar strings SQL ou consultar o banco diretamente.
2. **Consultas 100% Parametrizadas**: Toda consulta aos Repositories do Laravel utiliza PDO Parameter Binding, tornando tentativas de SQL Injection (`' OR 1=1; --`, `UNION SELECT`) completamente inertes.
3. **Detecção Preventiva de Prompt Injection**: O `IntentResolver` inspeciona a pergunta via expressões regulares procurando padrões de injeção (*"ignore previous instructions"*, *"system prompt"*, *"drop table"*). Ao detectar qualquer termo malicioso, a execução é abortada preventivamente e uma resposta de segurança é retornada sem acionar a IA ou o banco.

---

### 3. Você permitiria que a IA escrevesse SQL diretamente? Por quê?
**Resposta:**  
**Não.** Em ambientes corporativos e de produção, permitir que modelos LLM gerem SQL livre (Text-to-SQL desgovernado) é considerado uma vulnerabilidade crítica pelos seguintes motivos:
- **Risco de Hallucination em Filtros**: A IA pode omitir cláusulas `WHERE status = 'delivered'` ou filtrar datas incorretamente, induzindo o gestor ao erro com números falsos.
- **Risco de Negação de Serviço (DoS)**: A IA pode gerar queries sem índices (Full Table Scans) ou joins cartesianos que travam o banco de dados em horários de pico.
- **Vazamento de Dados Multitenant**: Em um SaaS com múltiplos restaurantes, uma falha de geração de SQL poderia expor dados de faturamento de concorrentes.
- **A Abordagem Correta**: O software mapeia a **intenção** do usuário e executa métodos controlados, indexados e testados pela engenharia de software (`AnalyticsService`).

---

### 4. Se a base tivesse 100 milhões de pedidos, como sua solução se comportaria? O que precisaria mudar?
**Resposta:**  
Com 100 milhões de registros, consultas em tempo real com `SUM()` e `AVG()` gerariam gargalos inaceitáveis de I/O. A arquitetura evoluiria para:
1. **Tabelas de Agregação Rollup (OLAP)**: Criação de tabelas agregadas pré-computadas (ex: `daily_establishment_metrics`, `monthly_product_metrics`) atualizadas por workers assíncronos ou triggers.
2. **Particionamento de Tabelas**: Particionar a tabela `orders` por intervalo de data (`PARTITION BY RANGE (YEAR(ordered_at) * 100 + MONTH(ordered_at))`).
3. **Banco de Dados Analítico Dedicado**: Transferir a carga analítica para um banco colunar como **ClickHouse** ou **Amazon Redshift**, mantendo o MySQL estritamente para transações operacionais (OLTP).
4. **Réplicas de Leitura (Read Replicas)**: Configurar o Laravel (`database.php`) com conexões separadas de `read` e `write`, garantindo que queries analíticas não afetem o checkout dos pedidos.

---

### 5. Como reduziria o custo de chamadas à IA se o sistema tivesse 2.000 estabelecimentos usando diariamente?
**Resposta:**  
Com 2.000 estabelecimentos, o custo de tokens cresce linearmente se não houver governança:
1. **Cache Semântico e Exato com Redis**: Perguntas frequentes idênticas ou muito similares em uma janela de tempo (ex: *"Qual meu melhor dia?"*, *"Meu faturamento aumentou?"*) são respondidas diretamente do Redis sem consumir tokens da OpenAI (como implementado no `AIService`).
2. **Prompt Engineering Ultracompacto**: Minificar o payload JSON removendo espaços em branco, chaves desnecessárias e nomes longos antes do envio.
3. **Utilização de Modelos Menores e Destilados**: Uso padrão de modelos de baixo custo como `gpt-4o-mini` ou `gemini-1.5-flash`, reservando modelos maiores apenas para tarefas complexas.
4. **Previsão Local Determinística**: Respostas padronizadas de rotina podem ser atendidas por código Laravel sem custo de LLM, utilizando a IA apenas quando necessária síntese em linguagem natural refinada.

---

### 6. Em quais partes da aplicação você utilizaria cache e qual estratégia adotaria?
**Resposta:**  
Utilizamos uma estratégia de **Cache em Camadas (Two-Tier Caching)** via Redis:
1. **Camada 1 — Cache de Respostas de IA (`ai:response:{hash}`)**: TTL de 1 hora. Se o gestor ou colaboradores consultam a mesma pergunta, a resposta é entregue em 1 milissegundo com custo zero de tokens.
2. **Camada 2 — Cache de Métricas Analíticas (`analytics:dashboard_summary`, `analytics:best_day`)**: TTL de 15 a 30 minutos em `AnalyticsService`. Evita recalcular faturamento e rankings a cada refresh da página.
3. **Invalidação Inteligente**: Invalidação orientada a eventos (`clearCache()`) disparada quando novos pedidos são confirmados ou no fechamento diário do caixa.

---

### 7. Como monitoraria o consumo de tokens e o custo gerado por usuário/estabelecimento?
**Resposta:**  
1. **Persistência Granular de Auditoria**: Cada interação registra na tabela `ai_conversations` os campos: `tokens_input`, `tokens_output`, `tokens_total`, `cost_usd`, `model`, `provider` e `response_time_ms`.
2. **Cálculo de Custo em Tempo Real**: `config/ai.php` mapeia o custo exato por 1.000 tokens de cada modelo. A cada requisição, o valor em USD é calculado e atribuído.
3. **Endpoint de Observabilidade**: Implementamos `GET /api/v1/dashboard/ai-observability` que expõe a volumetria total, taxa de cache hit, custo acumulado e tempo de resposta.
4. **Alertas e Quotas**: Configuração de thresholds no Redis para travar ou notificar gestores que atinjam o teto diário de tokens do seu plano.

---

### 8. Como registraria as perguntas e respostas dos usuários para auditoria e melhoria contínua?
**Resposta:**  
1. **Modelo de Auditoria Imutável**: O Model `AiConversation` funciona como um log de eventos (append-only), sem atualização (`UPDATED_AT = null`), garantindo que o histórico nunca seja adulterado.
2. **Canais Dedicados de Log no Filesystem**: Criação do canal diário `ai` em `config/logging.php` gravando em `storage/logs/ai.log` estruturado em JSON com rotação de 30 dias.
3. **Conformidade com LGPD**: Perguntas do gestor não trafegam dados sensíveis de clientes finais (como endereços ou números de cartão). Dados pessoais exibidos em relatórios de churn são restritos ao primeiro nome e telefone comercial.
4. **Loop de Melhoria (Fine-tuning)**: Registros com avaliação negativa do gestor são catalogados para alimentar testes de regressão de prompts e calibrar o `IntentResolver`.

---

### 9. O que fazer se a API da OpenAI ficar lenta ou fora do ar?
**Resposta:**  
A arquitetura implementa o padrão **Graceful Degradation & Multi-Provider Fallback**:
1. **Timeouts Curtos e Controlados**: A chamada HTTP para a API da OpenAI possui timeout configurado de 30 segundos.
2. **Multi-Provider Fallback**: Se o provedor principal (OpenAI) falhar com status HTTP 5xx ou timeout, o sistema pode comutar automaticamente para a API do Google Gemini (`gemini-1.5-flash`).
3. **Motor Grounded de Fallback Local**: Caso todos os provedores externos estejam inoperantes ou a internet caia, o `AIService` não quebra a tela do gestor. Ele ativa o motor determinístico local, que formata uma resposta analítica completa baseada nos números reais do banco de dados, sinalizando com status `fallback`.

---

### 10. Se tivesse mais tempo, o que faria de diferente ou adicionaria?
**Resposta:**  
Com mais tempo de desenvolvimento, implementaria as seguintes melhorias estratégicas:
1. **Agente com Function Calling / Tool Calling Nativo**: Utilizar o recurso de *Tools* da OpenAI para que o modelo decida dinamicamente quais funções da API chamar (ex: `getOrderStats()`, `getChurnList()`).
2. **Geração Dinâmica de Gráficos pela IA**: Permitir que a IA responda não apenas com texto, mas também com configurações declarativas para o **Chart.js** (ex: gráficos de barra da evolução semanal renderizados na hora dentro do balão).
3. **Integração Nativa com WhatsApp (Disparo de Campanhas)**: Conectar a ação prática de campanhas diretamente à API oficial da Meta (WhatsApp Business Cloud API) para disparar as mensagens aos clientes inativos com um clique.
4. **Assistente de Voz**: Adicionar transcrição de áudio via OpenAI Whisper no botão do microfone para permitir que o dono do delivery pergunte por comando de voz direto da cozinha.
5. **Arquitetura Multitenant Completa**: Adicionar `establishment_id` em todas as tabelas com Global Scopes do Eloquent para isolamento estrito de dados entre centenas de franquias e restaurantes.

---

## 8. Diferenciais Implementados

| Diferencial | Descrição |
|---|---|
| **Streaming em Tempo Real (SSE)** | Respostas da IA transmitidas palavra por palavra via Server-Sent Events com cursor de digitação (`POST /api/v1/chat/stream`) |
| **Ação Prática com IA** | Painel completo (`CampaignPanel.vue`) que mapeia clientes inativos (>30 dias), calcula potencial de receita e redige mensagens de reativação |
| **Motor Grounded de Fallback** | O sistema funciona 100% mesmo sem chaves de API cadastradas, calculando diagnósticos reais via código Laravel |
| **Transparência de Contexto** | Cada resposta da IA contém um botão expansível *"Ver dados reais do banco"*, exibindo o payload exato extraído do MySQL |
| **Cache em Duas Camadas** | Cache de perguntas idênticas no Redis (TTL 1h) + Cache de métricas do MySQL (TTL 15 min) |
| **Observabilidade e Auditoria** | Endpoint `/api/v1/dashboard/ai-observability`, gravação na tabela `ai_conversations` e canais de log dedicados |
| **Proteção Ativa contra Injeções** | Detecção de Prompt Injection e parametrização estrita de queries contra SQL Injection |
| **Docker Multi-Container** | Stack completa orquestrada via `docker-compose.yml` (PHP-FPM, Nginx com buffer SSE desligado, MySQL 8, Redis e Queue Worker) |

---

## 👨‍💻 Autor

- **Candidato:** Luis Filipe S. Lima
- **Repositório:** [https://github.com/Remixxlf/prefiro-delivery](https://github.com/Remixxlf/prefiro-delivery)
- **Tecnologias:** Laravel 11 • Vue.js 3 • MySQL 8 • Redis • OpenAI GPT-4o-mini • Docker