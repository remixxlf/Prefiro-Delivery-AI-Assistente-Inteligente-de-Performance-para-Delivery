<?php

namespace App\Services\AI;

/**
 * IntentResolver
 *
 * Analisa a pergunta do usuário em linguagem natural, identifica a intenção
 * de negócio subjacente e valida proteções contra Prompt Injection.
 *
 * Não consulta dados brutos e não executa comandos SQL: seu papel é mapear
 * a pergunta para uma consulta de negócio parametrizada e controlada.
 */
class IntentResolver
{
    // Constantes de intenções suportadas
    public const INTENT_REVENUE_DROP       = 'revenue_drop';
    public const INTENT_REVENUE_COMPARISON = 'revenue_comparison';
    public const INTENT_BEST_DAY           = 'best_day';
    public const INTENT_PEAK_HOURS         = 'peak_hours';
    public const INTENT_TOP_PRODUCTS       = 'top_products';
    public const INTENT_LOSING_PRODUCTS    = 'losing_products';
    public const INTENT_AVERAGE_TICKET     = 'average_ticket';
    public const INTENT_BEST_CUSTOMERS     = 'best_customers';
    public const INTENT_INACTIVE_CUSTOMERS = 'inactive_customers';
    public const INTENT_GROWTH_ACTIONS     = 'growth_actions';
    public const INTENT_CREATE_CAMPAIGN    = 'create_campaign';
    public const INTENT_GENERAL_SUMMARY    = 'general_summary';

    /**
     * Padrões de detecção de tentativa de Prompt Injection ou SQL Injection.
     */
    protected array $injectionPatterns = [
        '/\b(ignore|esque(ç|c)a)\s+(all|todas|as|previous|anteriores)\s+(instructions|instru(ç|c)(õ|o)es)/i',
        '/\b(system\s*prompt|prompt\s*do\s*sistema|jailbreak|dan\s*mode)/i',
        '/\b(voc(ê|e)\s+agora\s+(é|e)|you\s+are\s+now|act\s+as)\b/i',
        '/\b(drop\s+table|delete\s+from|union\s+select|insert\s+into|update\s+\w+\s+set)\b/i',
        '/(--|\/\*|\*\/|;\s*--)/',
        '/<script\b[^>]*>(.*?)<\/script>/is',
    ];

    /**
     * Mapeamento de termos e expressões regulares para cada intenção de negócio.
     */
    protected array $intentRules = [
        self::INTENT_CREATE_CAMPAIGN => [
            '/\b(crie|criar|gere|gerar|fa(ç|c)a|produza)\s+(uma\s+)?campanha\b/i',
            '/\bcampanha\s+para\s+(clientes|inativos|quem\s+n(ã|a)o\s+compra)\b/i',
            '/\b(mensagem|texto)\s+(para\s+clientes\s+inativos|de\s+reativa(ç|c)(ã|a)o)\b/i',
        ],
        self::INTENT_GROWTH_ACTIONS => [
            '/\b(aumentar|melhorar|alavancar)\s+(meu\s+)?(faturamento|vendas|receita)\b/i',
            '/\b(tr(ê|e)s|3)\s+a(ç|c)(õ|o)es\b/i',
            '/\bo\s+que\s+posso\s+fazer\s+para\s+(vender|aumentar)\b/i',
            '/\b(dicas|estrat(é|e)gias|recomenda(ç|c)(õ|o)es)\s+para\b/i',
            '/\bcomo\s+(posso\s+)?vender\s+mais\b/i',
        ],
        self::INTENT_REVENUE_DROP => [
            '/\b(por\s*que|pq)\s+(minhas\s+)?vendas\s+(ca(í|i)ram|diminu(í|i)ram|despencaram)\b/i',
            '/\b(por\s*que|pq)\s+(meu\s+)?faturamento\s+(caiu|diminuiu|despencou)\b/i',
            '/\b(queda|redu(ç|c)(ã|a)o)\s+(nas\s+vendas|no\s+faturamento|de\s+vendas)\b/i',
            '/\bpor\s+que\s+vendi\s+menos\b/i',
        ],
        self::INTENT_REVENUE_COMPARISON => [
            '/\bquanto\s+vendi\s+(este|esse|neste|no)\s+m(ê|e)s\b/i',
            '/\b(meu\s+)?faturamento\s+(aumentou|diminuiu|subiu|cresceu)\b/i',
            '/\bquanto\s+(faturei|foi\s+o\s+faturamento|ganhei)\b/i',
            '/\bevolu(ç|c)(ã|a)o\s+das\s+vendas\b/i',
            '/\bcomparativo\s+de\s+(vendas|faturamento)\b/i',
        ],
        self::INTENT_BEST_DAY => [
            '/\b(qual\s+foi\s+meu\s+)?melhor\s+dia\s+(da\s+semana)?\b/i',
            '/\b(qual\s+)?dia\s+(que\s+)?mais\s+vende\b/i',
            '/\bpior\s+dia\s+(da\s+semana)?\b/i',
            '/\bdias\s+da\s+semana\b/i',
        ],
        self::INTENT_PEAK_HOURS => [
            '/\b(qual\s+)?hor(á|a)rio\s+(possui\s+maior\s+volume|mais\s+vende|de\s+pico)\b/i',
            '/\bvolume\s+de\s+pedidos\s+por\s+hor(á|a)rio\b/i',
            '/\bque\s+horas\s+(mais\s+vendo|tenho\s+mais\s+pedidos)\b/i',
            '/\bturno\s+(de\s+maior\s+movimento|almo(ç|c)o|jantar)\b/i',
        ],
        self::INTENT_LOSING_PRODUCTS => [
            '/\b(quais|qual)\s+produtos?\s+(est(ã|a)o\s+)?perdendo\s+vendas\b/i',
            '/\bprodutos?\s+em\s+queda\b/i',
            '/\bprodutos?\s+que\s+mais\s+perderam\b/i',
            '/\bqueda\s+de\s+vendas\s+por\s+produto\b/i',
        ],
        self::INTENT_TOP_PRODUCTS => [
            '/\b(qual|quais)\s+produtos?\s+(mais\s+vendeu|mais\s+vendidos?|mais\s+saem)\b/i',
            '/\bcampe(ã|a)o\s+de\s+vendas\b/i',
            '/\bmais\s+vendido\b/i',
            '/\branking\s+de\s+produtos\b/i',
        ],
        self::INTENT_AVERAGE_TICKET => [
            '/\b(qual|como\s+est(á|a))\s+(o\s+)?(meu\s+)?ticket\s+m(é|e)dio\b/i',
            '/\bticket\s+m(é|e)dio\b/i',
            '/\bvalor\s+m(é|e)dio\s+por\s+pedido\b/i',
        ],
        self::INTENT_INACTIVE_CUSTOMERS => [
            '/\b(quais\s+)?clientes\s+est(ã|a)o\s+deixando\s+de\s+comprar\b/i',
            '/\bclientes\s+(que\s+)?n(ã|a)o\s+compram\s+h(á|a)\s+mais\s+de\s+(\d+)\s+dias\b/i',
            '/\bclientes\s+inativos\b/i',
            '/\bclientes\s+sem\s+pedidos\b/i',
            '/\bclientes\s+sumidos\b/i',
        ],
        self::INTENT_BEST_CUSTOMERS => [
            '/\b(quais\s+s(ã|a)o\s+meus\s+)?melhores\s+clientes\b/i',
            '/\bclientes\s+que\s+mais\s+(compram|gastam|pedem)\b/i',
            '/\bmaiores\s+compradores\b/i',
            '/\btop\s+clientes\b/i',
        ],
    ];

    /**
     * Sanitiza a pergunta do usuário e remove caracteres de controle potencialmente maliciosos.
     */
    public function sanitize(string $question): string
    {
        // Remove bytes nulos
        $clean = str_replace(chr(0), '', $question);

        // Remove tags HTML/XML
        $clean = strip_tags($clean);

        // Normaliza espaços em branco
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Limita comprimento máximo para proteção contra DoS de tokens
        return mb_substr(trim($clean), 0, 500);
    }

    /**
     * Verifica se há indícios claros de Prompt Injection na pergunta.
     */
    public function detectPromptInjection(string $question): bool
    {
        foreach ($this->injectionPatterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Identifica a intenção e extrai parâmetros relevantes da pergunta.
     */
    public function resolve(string $rawQuestion): array
    {
        $sanitized = $this->sanitize($rawQuestion);
        $isInjection = $this->detectPromptInjection($rawQuestion);

        if ($isInjection) {
            return [
                'intent'            => self::INTENT_GENERAL_SUMMARY,
                'confidence'        => 0.1,
                'sanitized_question'=> $sanitized,
                'is_suspicious'     => true,
                'security_warning'  => 'Tentativa de injeção ou instrução não permitida detectada.',
                'parameters'        => [],
            ];
        }

        // Tenta combinar com as regras de intenção
        foreach ($this->intentRules as $intent => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $sanitized)) {
                    $params = $this->extractParameters($intent, $sanitized);
                    return [
                        'intent'            => $intent,
                        'confidence'        => 0.95,
                        'sanitized_question'=> $sanitized,
                        'is_suspicious'     => false,
                        'parameters'        => $params,
                    ];
                }
            }
        }

        // Fallback para resumo geral
        return [
            'intent'            => self::INTENT_GENERAL_SUMMARY,
            'confidence'        => 0.5,
            'sanitized_question'=> $sanitized,
            'is_suspicious'     => false,
            'parameters'        => [],
        ];
    }

    /**
     * Extrai parâmetros numéricos ou de domínio específicos da pergunta.
     */
    protected function extractParameters(string $intent, string $text): array
    {
        $params = [];

        // Extrai quantidade de dias para inatividade (ex: 30, 45, 60)
        if (in_array($intent, [self::INTENT_INACTIVE_CUSTOMERS, self::INTENT_CREATE_CAMPAIGN])) {
            if (preg_match('/\b(\d+)\s+dias\b/i', $text, $matches)) {
                $params['days'] = (int) $matches[1];
            } else {
                $params['days'] = 30; // Padrão
            }
        }

        return $params;
    }
}