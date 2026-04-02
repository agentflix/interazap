# SPEC-026-chat-transfer-internal-note — Motivo interno na transferência do chat

## Status

Spec visual entregue por `@DESIGNER`, pronta para consumo por `@FRONTEND`, `@QA` e `@REVIEWER`.

## Design Spec — Transferência com motivo obrigatório e nota interna

### 1. Objetivo

Garantir que qualquer transferência entre atendentes ainda exposta na tela de chat só possa ser concluída com um motivo explícito, escrito em `textarea`, e que esse motivo reapareça na timeline do chat como uma `internal_note` claramente interna, sem aparência de mensagem enviada ao cliente.

### 2. Layout do modal / diálogo de transferência

**Estrutura**

- `modal` ou diálogo principal de transferência com largura média, dividido em header, body e footer.
- Header: título + texto de apoio curto.
- Body: `select-input` para destino, `textarea-input` obrigatório para motivo e helper text logo abaixo do campo.
- Footer: ação secundária `Cancelar` à esquerda e único CTA primário `Transferir chamado` à direita.

**Ordem visual**

1. Título do modal
2. Texto de apoio
3. `select-input` de atendente
4. `textarea-input` de motivo
5. Helper/erro inline do motivo
6. Ações do rodapé

### 3. Comportamento do CTA

- O CTA primário começa desabilitado.
- O CTA só habilita quando houver atendente selecionado e motivo com conteúdo real após `trim()`.
- Ao clicar em `Transferir chamado`, o modal entra em loading: inputs bloqueados, CTA com label de progresso e sem duplo envio.
- Em sucesso: fechar modal e refletir a `internal_note` na timeline interna do ticket.
- Em erro: manter modal aberto, preservar seleção e texto digitado, exibir erro visível e devolver o CTA ao estado habilitado.
- Em lista vazia de atendentes: manter o `select-input` indisponível para escolha e exibir estado vazio textual claro com orientação para tentar novamente mais tarde.

### 4. Copy final dos rótulos e textos de apoio

| Elemento | Copy final |
| ------- | ---------- |
| Título do modal | `Transferir chamado` |
| Texto de apoio | `Informe para quem o chamado será transferido e registre o motivo para a equipe interna.` |
| Label do select | `Transferir para` |
| Placeholder do select | `Selecione um atendente` |
| Label do textarea | `Motivo da transferência` |
| Placeholder do textarea | `Descreva o contexto que o próximo atendente precisa saber.` |
| Helper padrão | `Essa nota aparecerá apenas na timeline interna e não será exibida ao cliente.` |
| Erro de validação do motivo | `Informe o motivo da transferência.` |
| CTA primário | `Transferir chamado` |
| CTA em loading | `Transferindo...` |
| CTA secundário | `Cancelar` |
| Erro de submissão | `Não foi possível transferir o chamado. Tente novamente.` |
| Rótulo da timeline | `Nota interna · oculta para o cliente` |

### 5. Apresentação da `internal_note` na timeline

- A nota deve aparecer no fluxo normal da timeline, mas com estilo próprio de mensagem interna.
- Não usar aparência de mensagem `incoming` nem `outgoing`; a leitura deve ser de sistema/operação interna.
- Estrutura visual da nota:
	`rótulo interno` → `texto do motivo` → `metadados secundários`.
- O texto principal da nota é o motivo digitado no modal, sem prefixos técnicos.
- Os metadados secundários podem incluir contexto curto de transferência e timestamp, em tom visual discreto.
- A nota não deve exibir status de envio ao cliente, avatar de contato nem qualquer affordance de canal externo.

### 6. Estados obrigatórios

| Estado | Comportamento esperado |
| ------ | ---------------------- |
| Default | Select e textarea ativos; helper neutro; CTA primário desabilitado até o formulário ficar válido. |
| Loading | Select e textarea bloqueados; CTA primário com `Transferindo...`; impedir clique duplo e fechamento por reenvio acidental. |
| Error | Mensagem de erro visível; dados preservados; foco retorna ao problema principal; CTA volta ao estado interativo. |
| Empty | Sem atendentes disponíveis: mensagem visível em texto secundário, CTA primário desabilitado e nenhuma falsa affordance de seleção. |
| Disabled | CTA primário desabilitado enquanto faltar atendente ou o motivo estiver vazio/em branco. |

### 7. Tokens/classes recomendados

| Área | Tokens/classes recomendados |
| ---- | --------------------------- |
| Container do modal | `bg-white dark:bg-neutral-900 text-neutral-900 dark:text-neutral-50` |
| Header do modal | `text-2xl font-semibold` no título + `text-sm text-neutral-400` no apoio |
| Espaçamento interno | `p-6 gap-4` no body e `p-4 gap-4` no footer |
| Helper/erro inline | `text-xs`; helper em `text-neutral-400`; erro em `text-red-400` |
| CTA primário | Variante primária do `button`, com estados de hover/focus do design system |
| CTA secundário | Variante neutra/ghost do `button` |
| Bloco da `internal_note` | `bg-neutral-800 dark:bg-neutral-800 border border-neutral-700 rounded-xl p-4` |
| Rótulo da `internal_note` | `text-xs font-medium text-blue-400` |
| Texto do motivo | `text-sm text-neutral-50` |
| Metadados da nota | `text-xs text-neutral-400` |

### 8. O que não fazer

- Não permitir transferência com motivo vazio, só com espaços ou escondendo a exigência em toast.
- Não usar `confirm-modal`; o fluxo exige formulário completo no `modal` principal.
- Não renderizar a `internal_note` com o mesmo visual das mensagens do cliente ou do atendente.
- Não exibir check de envio, avatar de contato, status externo ou qualquer sinal de que a nota saiu para o cliente.
- Não hardcodear cores, bordas ou espaçamentos fora dos tokens do design system.
- Não criar CTA extra de mesmo peso visual que concorra com `Transferir chamado`.