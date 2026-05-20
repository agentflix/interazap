# Decisões — ai-tool-parameter-resolution

## TASK-2.1.1 — 2026-05-20
**Decisão:** Resolução de entidades por parâmetros humanos é padrão para tools de AI
**Motivo:** A LLM gera parâmetros como nomes ("Lucas", "Rosa") em vez de UUIDs. Tools devem resolver essas strings para entidades reais do tenant antes de persistir.
**Impacto:** Todas as tools de AI devem usar `AiToolEntityResolver` ou padrão equivalente para aceitar UUID, nome, email ou alias. Erros devem ser recuperáveis (nunca exception).

## TASK-5.1.1 — 2026-05-20
**Decisão:** Tools de alto nível (composite) reduzem alucinação de parâmetros pela LLM
**Motivo:** Quando a LLM precisa compor múltiplas tools (`notify_seller` + `create_task` + `send_message`), ela inventa UUIDs para parâmetros que não recebeu. Uma tool composite que encapsula a operação completa elimina esse problema.
**Impacto:** Preferir tools que encapsulam múltiplas operações em uma única chamada. Tools de alto nível devem resolver entidades internamente e sempre enviar resposta ao cliente.
