# T.A.C.E — Framework de Especificação de Tasks

> Use sempre que for criar uma task. Tasks vagas são proibidas.

## As 4 Letras

| Letra | Significado | Pergunta-chave |
|-------|-------------|----------------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? |
| **C** | Comportamento | COMO funciona (antes → depois)? |
| **E** | Evidência | COMO SABER que está pronto? |

---

## T — Tarefa

Frase imperativa, específica, focada em UM resultado.

**Bom:** "Criar Action `ChatOutboundAction` que publica mensagem no Redis Stream `whatsapp:outbound`"

**Ruim:** "Implementar envio de mensagem"

---

## A — Arquivo

Lista de arquivos exatos a criar ou modificar (paths absolutos no monorepo).

**Bom:**
- `api/src/Domain/Chat/Actions/ChatOutboundAction.php` (criar)
- `api/src/Domain/Chat/DTOs/ChatOutboundDTO.php` (criar)
- `api/tests/Feature/Chat/ChatOutboundActionTest.php` (criar)

**Ruim:** "Mexer no módulo Chat"

---

## C — Comportamento

Estado ANTES vs estado DEPOIS. Inclui efeitos colaterais (queue, log, evento).

**Bom:**
```text
ANTES:
- Não há ação para enviar mensagem outbound

DEPOIS:
- ChatOutboundAction recebe ChatOutboundDTO (tenant_id, contact_id, content)
- Valida tenant via BelongsToTenant
- Publica evento no Redis Stream "whatsapp:outbound"
- Retorna ID da mensagem persistida em "messages"
- Log estruturado com correlation_id
```

**Ruim:** "Manda a mensagem"

---

## E — Evidência

Critérios verificáveis. Idealmente com gates da stack.

**Bom:**
- [ ] Teste Pest passa: `tests/Feature/Chat/ChatOutboundActionTest.php`
- [ ] Coverage do método ≥ 80%
- [ ] PHPStan L6 limpo
- [ ] Mensagem aparece em `XRANGE whatsapp:outbound - +` (validado em teste de integração com Redis)
- [ ] Log estruturado contém `correlation_id`, `tenant_id`, `contact_id`

**Ruim:** "Funciona"

---

## Anti-padrões

- Tasks com mais de UM resultado → quebrar em várias
- "Refatorar X" sem `C` claro → especifique o comportamento depois
- "Melhorar performance" → meça antes/depois (`E`)
- Sem testes em `E` → reprovado pelo QA
