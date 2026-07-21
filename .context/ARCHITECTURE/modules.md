# Mapa de Módulos — InteraZap

> Setas = "consome". Definição formal em `modules.yaml`.

```mermaid
graph LR
    APP["app<br/>Angular 20"]
    GW["gateway<br/>NestJS 11"]
    API["api<br/>Laravel 12"]
    LAND["landing · landing-clinicas · landing-new"]
    PG[("PostgreSQL 17")]
    REDIS[("Redis 7")]
    EXT["Externos<br/>uazapi · Meta · Telegram<br/>Gemini · OpenAI · MiniMax · AWS"]

    APP -->|REST /api| API
    APP -->|WebSocket /ws| GW
    API -->|Redis Streams XADD| REDIS
    GW -->|consumers| REDIS
    GW -->|HTTP /internal| API
    API --> PG
    API --> REDIS
    GW --> EXT
    LAND -.->|estáticas, sem backend| APP
```

## Bounded contexts

```mermaid
graph TB
    subgraph API["api/src/Domain — Laravel"]
        A1["Ai"]
        A2["Chat"]
        A3["Billing"]
        A4["CRM"]
        A5["Platform"]
        A6["Auth · Configuration · Dashboard · Reports"]
        A7["Gateway — client Redis Streams"]
        A8["Shared — base comum"]
    end

    subgraph GW["gateway/src/domains — NestJS"]
        G1["ai — providers, consumers, classifier"]
        G2["chat — outbound, webhooks Meta, canais"]
        G3["realtime — Socket.io, webchat"]
        G4["billing"]
        G5["webhooks — outbound para terceiros"]
        G6["internal"]
    end

    A1 -->|stream ai.run| A7
    A2 -->|stream chat.outbound_message| A7
    A7 --> G1
    A7 --> G2
    G3 -->|HTTP interno| A2
    G4 -->|HTTP interno| A3
```

## Regra de leitura

Todo módulo da api é `api/src/Domain/{Contexto}` com o mesmo esqueleto: `Actions/`, `Http/`, `Models/`, `DTOs/`, `Routes/`, `Jobs/`, `Services/`, `Policies/`.
Todo domínio do gateway é `gateway/src/domains/{contexto}` com `controllers/`, `services/`, `dto/`, `models/`, `consumers|processors/`.
