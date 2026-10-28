<?php

/**
 * Snapshot of the test client's signup state (profiles, preferences, Gmail identity).
 * Read by UserSeeder. Secrets (OAuth tokens) are never stored here.
 */
return [
    'profiles' => [
        'pt' => [
            'is_active' => true,
            'cv_original_name' => 'Marcus_Vinicius_Bassalobre_Software_Engineer_PT.pdf',
            'email_subject' => 'Aplicação: {{ job_title }} – {{ client_name }}',
            'email_body' => 'Olá, equipe {{ company }},

{{ cover_letter }}

Vaga: {{ job_url }}

Meu currículo está em anexo.

{{ links }}

Podemos conversar 15 minutos esta semana sobre esta vaga?

Atenciosamente,
{{ client_name }}',
            'cover_letter' => 'Meu nome é Marcus Vinicius, sou engenheiro full stack com mais de 13 anos de experiência construindo produtos de pagamentos, fintech e investimentos de ponta a ponta, do React e Next.js no front-end ao Node.js, NestJS e Laravel no back-end.

O que entreguei:
• Tech lead do Carteira no Investidor10, produto de carteira de investimentos usado por mais de 24.000 usuários por dia, com atualizações em tempo real (WebSockets, Redis), integrações de IA/MCP e migração de um monolito Laravel para React 19, Next.js e um app React Native offline-first.
• Plataforma de pagamentos da Payt, ajudando a escalar do MVP a cerca de R$ 6,5 milhões (~US$ 1,3 milhão) em volume mensal de pico, incluindo fluxos antifraude que reduziram fraudes e chargebacks em mais de 60%.
• Camada de gateways de pagamento com mais de 50 provedores na Vega Checkout e mais de 10 projetos corporativos para clientes como Unimed, Gerdau, Saint-Gobain, Adidas e Intelbras na Diwe.

Stack: TypeScript, JavaScript, PHP, Python, React, Next.js, React Native, Node.js, NestJS, Laravel, PostgreSQL, MySQL, MongoDB, Redis, AWS, Docker, APIs da OpenAI e da Anthropic.

Moro em Dublin, Irlanda (inglês C1 Advanced, elegível ao Critical Skills Employment Permit). Aberto a posições remotas de engenharia de software.',
            'links' => [
                0 => [
                    'label' => 'Linkedin',
                    'url' => 'https://www.linkedin.com/in/mvbassalobre/',
                ],
                1 => [
                    'label' => 'Portfolio',
                    'url' => 'https://mvbassalobre.com/',
                ],
                2 => [
                    'label' => 'GitHub',
                    'url' => 'https://github.com/marcusvbda',
                ],
            ],
        ],
        'en' => [
            'is_active' => true,
            'cv_original_name' => 'Marcus_Vinicius_Bassalobre_Software_Engineer_EN.pdf',
            'email_subject' => 'Application: {{ job_title }} – {{ client_name }}',
            'email_body' => 'Hi {{ company }} team,

{{ cover_letter }}

Job posting: {{ job_url }}

My CV is attached.

{{ links }}

Worth a 15-minute chat this week about this role?

Best regards,
{{ client_name }}',
            'cover_letter' => 'My name is Marcus, I\'m full stack engineer with 13+ years building payment, fintech and investment products end to end, and today AI agents that run in production.

What I\'ve delivered:
• A WhatsApp AI agent for Divercity Park that answers customers and quotes parties with live prices from the park\'s API, hands conversations to staff with a summary when it can\'t answer, and turns unanswered questions into a FAQ the team completes.
• Lead developer of Carteira at Investidor10 (24,000+ daily users): real-time updates (WebSockets, Redis), AI/MCP integrations, and the migration from a Laravel monolith to React 19, Next.js and an offline-first React Native app.
• Payt\'s core payment platform, scaled from MVP to ~R$6.5M (~US$1.3M) peak monthly volume, with anti-fraud workflows that cut fraud and chargebacks by 60%+.
• A 50+ provider payment gateway layer at Vega Checkout, and 10+ enterprise projects at Diwe (Unimed, Gerdau, Saint-Gobain, Adidas, Intelbras).

Stack: TypeScript, PHP, Python, React, Next.js, React Native, Node.js, NestJS, Laravel, PostgreSQL, MySQL, Redis, AWS, Docker, n8n, OpenAI and Anthropic APIs, MCP.',
            'links' => [
                0 => [
                    'label' => 'LinkedIn',
                    'url' => 'https://www.linkedin.com/in/mvbassalobre',
                ],
                1 => [
                    'label' => 'Portfolio',
                    'url' => 'https://mvbassalobre.com',
                ],
                2 => [
                    'label' => 'GitHub',
                    'url' => 'https://github.com/marcusvbda',
                ],
            ],
        ],
    ],
    'preference' => [
        'titles' => [
        ],
        'seniorities' => [
        ],
        'stack' => [
        ],
        'locations' => [
        ],
        'remote_mode' => 'any',
        'exclude_words' => [
        ],
    ],
    'gmail' => [
        'external_account_id' => '112625470526166618747',
        'account_email' => 'bassalobre.vinicius@gmail.com',
        'account_name' => 'Vinicius Bassalobre',
        'granted_scopes' => [
            0 => 'https://www.googleapis.com/auth/userinfo.profile',
            1 => 'openid',
            2 => 'https://www.googleapis.com/auth/gmail.send',
            3 => 'https://www.googleapis.com/auth/userinfo.email',
        ],
        'metadata' => [
        ],
    ],
];
