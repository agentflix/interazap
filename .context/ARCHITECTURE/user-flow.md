# Fluxos do Usuário — InteraZap

> Três fluxos que atravessam todas as camadas: mensagem recebida, resposta da IA e atendimento humano.

## Fluxo 1 — Mensagem recebida (uazapi / Telegram)

```mermaid
sequenceDiagram
    participant CH as uazapi / Telegram
    participant API as api (Laravel)
    participant DB as PostgreSQL
    participant R as Redis Streams
    participant GW as gateway (NestJS)
    participant LLM as Provedor LLM
    participant APP as app (Angular)

    CH->>API: POST /webhooks/{canal}/instances/{token}
    API->>API: ChatWebhookIngestor — idempotência + lookup da instância
    API->>DB: persiste mensagem e ticket
    API-->>APP: broadcast via gateway (ticket atualizado)
    API->>API: VerifyContactWindowAction — janela de 24h
    alt Autopilot ativo e humano não assumiu
        API->>R: XADD ai.run (correlationId)
        R->>GW: consumer de IA
        GW->>LLM: inferência (Gemini · OpenAI · MiniMax)
        LLM-->>GW: resposta
        GW->>API: HTTP /internal — grava resposta
        API->>DB: persiste mensagem da IA
        API->>R: XADD chat.outbound_message
        R->>GW: consumer outbound
        GW->>CH: envia ao contato
    else Humano assumiu o ticket
        API-->>APP: notifica operador — IA bloqueada
    end
```

## Fluxo 2 — Operador no painel

```mermaid
sequenceDiagram
    participant APP as app (Angular)
    participant GW as gateway (Socket.io)
    participant API as api (Laravel)

    APP->>API: POST /api/auth/login (Sanctum)
    API-->>APP: token
    APP->>GW: WebSocket /ws com token
    GW->>API: valida sessão via /internal
    GW-->>APP: canal do tenant aberto
    APP->>API: assume ticket
    API-->>GW: evento de atualização
    GW-->>APP: broadcast em tempo real para todos os operadores
    APP->>API: envia mensagem
    API->>GW: XADD chat.outbound_message
    GW->>APP: confirmação de entrega
```

## Fluxo 3 — Decisão de resposta

```mermaid
flowchart TD
    START([Mensagem do contato]) --> IDEMP{Evento já processado?}
    IDEMP -->|Sim| DROP[Ignora — idempotência]
    IDEMP -->|Não| TENANT{Instância pertence a tenant ativo?}
    TENANT -->|Não| DROP
    TENANT -->|Sim| TICKET{Ticket aberto?}
    TICKET -->|Não| NEW[Cria ticket]
    TICKET -->|Sim| HUMAN{Humano assumiu?}
    NEW --> HUMAN
    HUMAN -->|Sim| NOTIFY[Notifica operador — IA não responde]
    HUMAN -->|Não| QUOTA{Cota de mensagens de IA disponível?}
    QUOTA -->|Não, modo stop| BLOCK[Bloqueia IA e avisa o tenant]
    QUOTA -->|Sim, ou overage| WINDOW{Janela de 24h aberta?}
    WINDOW -->|Sim| RUN[Run de IA no gateway]
    WINDOW -->|Não| TEMPLATE[Exige template aprovado]
    RUN --> SEND[Envia resposta ao canal]
    TEMPLATE --> SEND
    NOTIFY --> OP[Operador responde pelo painel]
    OP --> SEND
```
