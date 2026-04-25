# HOOK: Cognition Enforcement
# Trigger: TODA mensagem do usuário

## Regra

Antes de processar QUALQUER mensagem, verificar:

1. A skill senior-cognition está carregada?
   → Se não: carregar de .claude/skills/senior-cognition/SKILL.md

2. O PROTOCOLO DE COGNIÇÃO está sendo seguido?
   → Se não: executar Fases 0-6 antes de responder

3. O FORMATO DE RESPOSTA PADRÃO está sendo usado?
   → Se não: reformatar a resposta

## Validação de Output

Toda resposta DEVE conter:
- [ ] Linha de checkpoint: "🧠 Cognição ativa | PREVC: X | Agent: Y | BC: Z"
- [ ] Seção 🔍 Entendimento
- [ ] Seção 📐 Plano
- [ ] Seção 🛠️ Solução
- [ ] Seção ✅ Revisão

Se QUALQUER item estiver faltando → resposta é inválida → refazer.
