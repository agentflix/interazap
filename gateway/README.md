# InteraZap Gateway

NestJS Gateway for external integrations (UazAPI, Z-API, Asaas, OpenAI) with real-time WebSocket support.

## Architecture

```
gateway/
├── src/
│   ├── core/                   # Core infrastructure
│   │   └── config/             # Typed configuration
│   ├── domains/
│   │   ├── ai/                 # OpenAI integration
│   │   │   ├── consumers/      # Redis Streams consumers
│   │   │   ├── contracts/      # DTOs
│   │   │   └── providers/      # OpenAI adapter
│   │   ├── billing/            # Asaas integration
│   │   │   ├── contracts/      # Payment DTOs
│   │   │   └── providers/      # Asaas normalizer
│   │   ├── chat/               # WhatsApp integrations
│   │   │   ├── contracts/      # Provider interface, DTOs
│   │   │   ├── outbound/       # Message sending
│   │   │   └── providers/      # UazAPI, Z-API adapters
│   │   ├── realtime/           # WebSocket gateway
│   │   │   ├── gateways/       # Socket.io gateway
│   │   │   └── services/       # Event fanout
│   │   └── webhooks/           # Outbound webhooks
│   │       └── outbound/       # HTTP dispatcher
│   └── infrastructure/
│       ├── database/           # PostgreSQL
│       └── redis/              # Redis Streams, PubSub
```

## Quick Start

```bash
# Install dependencies
pnpm install

# Copy environment file
cp .env.example .env

# Start development server
pnpm run start:dev

# Run tests
pnpm test

# Run linter
pnpm lint

# Build for production
pnpm build
```

## Environment Variables

| Variable            | Description           | Default                |
| ------------------- | --------------------- | ---------------------- |
| `PORT`              | Server port           | 3001                   |
| `REDIS_URL`         | Redis connection      | redis://localhost:6379 |
| `DATABASE_URL`      | PostgreSQL connection | -                      |
| `UAZAPI_BASE_URL`   | UazAPI base URL       | -                      |
| `UAZAPI_API_KEY`    | UazAPI API key        | -                      |
| `ZAPI_BASE_URL`     | Z-API base URL        | https://api.z-api.io   |
| `ZAPI_CLIENT_TOKEN` | Z-API client token    | -                      |
| `OPENAI_API_KEY`    | OpenAI API key        | -                      |
| `OPENAI_MODEL`      | Default model         | gpt-4o-mini            |
| `ASAAS_BASE_URL`    | Asaas API URL         | -                      |
| `ASAAS_API_KEY`     | Asaas API key         | -                      |

## Endpoints

### Health

- `GET /health` - Health check

### Chat Webhooks

- `POST /webhooks/:provider/instances/:token` - Receive webhooks (UazAPI, Z-API)

### Billing Webhooks

- `POST /webhooks/billing/:provider/:token` - Receive billing webhooks (Asaas)

### WebSocket

- `ws://localhost:3000/ws` - Socket.io endpoint for real-time events

## Redis Streams

### Inbound (Gateway → Laravel)

| Stream                          | Description                |
| ------------------------------- | -------------------------- |
| `chat.inbound_message_received` | New messages from WhatsApp |
| `billing.payment_received`      | Payment events from Asaas  |

### Outbound (Laravel → Gateway)

| Stream                  | Description                     |
| ----------------------- | ------------------------------- |
| `chat.outbound_message` | Messages to send via WhatsApp   |
| `ai.chat_request`       | OpenAI chat completion requests |
| `ai.embedding_request`  | OpenAI embedding requests       |

### Response (Gateway → Laravel)

| Stream                         | Description                  |
| ------------------------------ | ---------------------------- |
| `chat.outbound_message_status` | Send status (success/failed) |
| `ai.chat_response`             | Chat completion results      |
| `ai.embedding_response`        | Embedding results            |

## Quality Gates

All gates must pass before merging:

```bash
# Run all checks
pnpm lint && pnpm test && pnpm build
```

## Tests

```bash
# Unit tests
pnpm test

# Watch mode
pnpm test:watch

# Coverage report
pnpm test:cov
```

**Current coverage**: 151 tests passing

## Providers

### Chat Providers

- **UazAPI** - WhatsApp Business API (existing)
- **Z-API** - WhatsApp API (new)

### Billing Providers

- **Asaas** - Brazilian payment gateway

### AI Providers

- **OpenAI** - GPT models, embeddings

## Key Features

- **Multi-provider support** - Factory pattern for WhatsApp providers
- **Circuit breaker** - OpenAI adapter with 5-failure threshold
- **Retry with backoff** - 1s → 4s → 16s exponential backoff
- **HMAC signatures** - Webhook authentication
- **Redis Streams** - Reliable message delivery with acknowledgment
- **Real-time WebSocket** - Event fanout to connected clients

## License

Private - InteraZap
