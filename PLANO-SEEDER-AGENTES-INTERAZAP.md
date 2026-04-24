# Plano Seeder de Agentes InteraZap

## Objetivo

Definir a criacao de um seeder idempotente para popular os agentes padrao do InteraZap no tenant default InteraZap/AGENTFLX, com configuracao inicial consistente para canais, skills, ferramentas, delegacoes e documento base de conhecimento.

## Escopo aprovado

- Seedar apenas no tenant default InteraZap/AGENTFLX.
- Preferir `gpt-4o-mini`, com fallback para outro modelo apenas se necessario.
- Usar nomes de agentes concisos.
- Garantir idempotencia com `updateOrCreate`.

## Contexto factual do produto

InteraZap e uma plataforma de comunicacao multicanal com IA integrada. O produto combina CRM, knowledge base/RAG, autopilot, relatorios e fluxos de atendimento apoiados por IA. Os canais mais relevantes para o seed inicial sao WhatsApp, webchat e Instagram. O posicionamento comercial atual considera os planos Starter (R$ 97), Professional (R$ 297) e Business (R$ 897), o que exige uma base de agentes pronta para operacao e expansao comercial.

## Estrategia de implementacao do seeder

1. Criar um seeder dedicado para agentes padrao do tenant default.
2. Resolver o tenant InteraZap/AGENTFLX antes de qualquer insert.
3. Persistir agentes com `updateOrCreate`, usando chaves estaveis por tenant e nome.
4. Definir `gpt-4o-mini` como modelo preferencial e fallback configuravel apenas quando indisponivel.
5. Popular relacoes dependentes em ordem previsivel: agentes, skills, arquivos, canais, tools, delegations e knowledge document.
6. Manter o seed seguro para reexecucao, sem duplicar registros nem quebrar customizacoes fora do escopo padrao.

## Agentes a criar

- Atendimento
- Vendas
- Suporte
- Qualificacao
- Reativacao

## Estrutura dos dados a popular

- Agentes: nome, slug, objetivo, modelo preferencial, fallback, status, tenant.
- Skills: instrucoes base, papel do agente, limites operacionais e estilo de resposta.
- Files: anexos ou referencias iniciais de configuracao por agente.
- Channels: vinculacao por agente para WhatsApp, webchat e Instagram.
- Tools: conjunto minimo de ferramentas habilitadas por papel.
- Delegations: mapa simples de transferencia entre agentes conforme contexto.
- Knowledge document: documento base com contexto institucional, oferta comercial e orientacoes de atendimento.

## Passos de execucao

1. Localizar o tenant default InteraZap/AGENTFLX.
2. Criar ou atualizar os agentes base com `updateOrCreate`.
3. Associar modelo preferencial e fallback por agente.
4. Popular skills e arquivos iniciais de cada agente.
5. Vincular canais suportados no seed inicial.
6. Registrar tools e delegations necessarias.
7. Criar ou atualizar o knowledge document padrao.
8. Executar o seeder novamente para confirmar idempotencia.

## Criterios de validacao

- O seed cria dados apenas no tenant default InteraZap/AGENTFLX.
- A reexecucao nao duplica agentes nem relacoes dependentes.
- Todos os agentes seeded usam `gpt-4o-mini` como preferencia inicial.
- Os canais WhatsApp, webchat e Instagram ficam associados conforme esperado.
- O knowledge document fica disponivel para a base inicial de RAG.
- O resultado final atende ao portfolio central do produto sem depender de ajuste manual imediato.

## Riscos e observacoes

- Divergencia entre nomes exibidos e slugs pode quebrar idempotencia se a chave de busca for instavel.
- Fallback de modelo deve ser controlado para nao mascarar erro de configuracao.
- O knowledge document inicial deve ser factual e curto para evitar ruido na base RAG.
- Customizacoes futuras por tenant nao devem ser sobrescritas por seeds padrao fora do escopo default.
