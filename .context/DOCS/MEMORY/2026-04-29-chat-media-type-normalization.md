# Memory: Normalização de tipo de mídia no envio de chat

## Metadados

| Campo        | Valor                                                                |
| ------------ | -------------------------------------------------------------------- |
| **Tipo**     | 🧠 Decisão                                                           |
| **Data**     | 2026-04-29                                                           |
| **Autor**    | DEBUG agent                                                          |
| **Contexto** | Bug: imagem enviada pelo atendente aparecendo como arquivo/documento |
| **Tags**     | chat, media, type-normalization, whatsapp                            |

---

## Situação

> O que estava acontecendo? Qual o contexto?

Mensagens enviadas com mídia podiam chegar ao backend com `type` genérico (`document`/`file`) e, sem normalização no fluxo de criação, eram persistidas e roteadas como documento.

---

## Decisão / Aprendizado

> O que foi decidido ou aprendido?

Aplicar normalização de `type` no fluxo real de criação (`SendChatMessageAction::create`) usando prioridade:

1. `mime_type` (image/video/audio),
2. fallback por extensão (`file_name` e `file_url`),
3. fallback para tipo original normalizado.

Isso evita depender exclusivamente do `type` informado pelo cliente para mídia.

---

## Alternativas Consideradas

> O que foi descartado e por quê?

| Alternativa                                   | Por que descartada                                                                                |
| --------------------------------------------- | ------------------------------------------------------------------------------------------------- |
| Corrigir apenas frontend (`detectMediaType`)  | Não cobre chamadas de outros clientes/integrações e não protege API contra payload inconsistente. |
| Corrigir apenas `resolveMediaType` no gateway | Corrige envio externo, mas mantém `type` inconsistente no banco/UI.                               |

---

## Consequências

> O que muda por causa disso?

### Positivas

- Mensagens de imagem não ficam presas como documento por inconsistência de `type` de entrada.
- UI do atendente e envio ao gateway passam a usar tipo coerente (`image`).
- Regressão coberta por teste automatizado de feature.

### Negativas / Trade-offs

- Heurística por extensão pode classificar incorretamente arquivos com extensão enganosa quando `mime_type` estiver ausente.

---

## Referências

- `api/src/Domain/Chat/Actions/SendChatMessageAction.php`
- `api/tests/Feature/ChatMessageControllerTest.php`
- Task: correção pontual de bug em envio de mídia (sem task T.A.C.E formal)

---

## Complemento (WebChat) — Filtro de anexo por tipo

### Situação

No WebChat, o seletor para `Foto` já usava `accept=image/*`, porém isso não impede todos os cenários de seleção manual de arquivos incompatíveis (ex.: PDF), gerando envio inválido no modo foto.
Também havia sintoma de UX: em alguns navegadores o filtro só refletia após um segundo clique, pois o `accept` dependia do ciclo de renderização após `pendingAttachmentType.set(...)`.

### Decisão

- Validar no frontend (`chat-window.component.ts`) o arquivo selecionado contra o tipo escolhido (`image`, `video`, `audio`, `document`) com regra MIME + fallback por extensão.
- Definir `input.accept` diretamente no `onAttachmentSelected` antes do `click()` para garantir filtro correto já na primeira abertura.
- Validar no backend (`WebChatMessageController`) a compatibilidade entre `type` e `mime_type` para bloquear bypass por chamadas diretas à API.

### Resultado

- Modo `Foto` não aceita mais PDF no fluxo do widget.
- API retorna `400` para payload inconsistente (`type=image` com `mime_type=application/pdf`).
- Cobertura de regressão adicionada em frontend e backend.

### Referências

- `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts`
- `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`
- `api/tests/Feature/Chat/WebChatMessageControllerTest.php`
