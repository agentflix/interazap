# Memory: Landing Chat Launcher Integration

## Metadados

| Campo        | Valor                                                       |
| ------------ | ----------------------------------------------------------- |
| **Tipo**     | 🧠 Decisão                                                  |
| **Data**     | 2026-04-20                                                  |
| **Autor**    | ORCHESTRATOR / DEV                                          |
| **Contexto** | Integração do WebChat (FEAT-040) na Landing Page (FEAT-041) |
| **Tags**     | [frontend, integration, chat, landing]                      |

---

## Situação

> O que estava acontecendo? Qual o contexto?

A landing page precisava disponibilizar um ponto de entrada para o chat interno do InteraZap (implementado na FEAT-040), permitindo que os clientes abrissem o webchat. Era necessário definir como essa integração seria feita de forma resiliente, sem criar dependências com o backend.

---

## Decisão / Aprendizado

> O que foi decidido ou aprendido?

Foi decidido criar um launcher HTML/CSS puramente estático com um contrato rigoroso de atributos `data-*` e eventos JS (`CustomEvent`). O launcher possui o ID fixo `interazap-chat-launcher` e injeta propriedades como tenant ID e entrypoint. O frontend escuta o clique e despacha eventos de telemetria antes de redirecionar para a rota externa já existente (`/chat/external/{tenantId}`). Nada no backend foi modificado ou criado.

---

## Alternativas Consideradas

> O que foi descartado e por quê?

| Alternativa                                 | Por que descartada                                                                                                                                |
| ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Incluir o script Angular inteiro na landing | Aumentaria o tempo de carregamento da landing que é puramente estática e leve (Tailwind CDN).                                                     |
| Abrir um Iframe oculto carregando o chat    | Traz desafios de iframe e geraria overhead de rede apenas para exibir o botão. Um redirecionamento ou "popup" é mais limpo nesse cenário inicial. |

---

## Consequências

> O que muda por causa disso?

### Positivas

- A landing page continua extremamente rápida (zero dependência de bundles JS pesados).
- O contrato desacoplado via `data-*` permite evolução, como depois criar um script que substitui o botão pelo widget iframe oficial sem mudar o HTML/ID original.
- Total reaproveitamento da FEAT-040 (zero novos tickets pro backend).

### Negativas / Trade-offs

- A experiência atual é de "redirecionamento" ou abertura de aba (open modal/tab) em vez de um pop-up de chat intra-página (iframe embutido), que pode ser evoluído no futuro se assim for priorizado.

---

## Referências

- `landing/index.html`
- FEAT-041, TASK-041 (T.A.C.E)
