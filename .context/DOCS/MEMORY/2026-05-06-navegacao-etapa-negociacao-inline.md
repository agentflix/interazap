# Memory: Navegação de etapa da negociação com controle inline

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-06 |
| **Autor** | Codex |
| **Contexto** | Chat CRM sidebar - card de negociação |
| **Tags** | chat, crm, negociacao, etapa, ux |

---

## Situação
> O que estava acontecendo? Qual o contexto?

As setas de navegação de etapa no card de negociação podiam parar de funcionar após interação, e a UI não deixava explícita a etapa atual no padrão solicitado.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Implementar controle de etapa inline no rodapé do card no formato `< [NOME] >` e mover o estado de atualização para o container (`CRMSection`) por negociação, evitando lock local no card.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter loading local no `DealCard` | Pode travar quando não há reset explícito após erro/sucesso assíncrono. |
| Atualizar lista completa após cada troca de etapa | Mais custo de render/rede e UX menos fluida para ação simples. |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Feedback visual claro da etapa atual.
- Navegação previsível com limites de primeira/última etapa.
- Menor risco de travamento das setas.

### Negativas / Trade-offs
- Aumenta ligeiramente a responsabilidade de estado no container.

---

## Referências
- Task: `TASK-CHAT-NEGOCIACAO-ETAPA-INLINE`
