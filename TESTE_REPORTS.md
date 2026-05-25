# TESTE_REPORTS.md - Plano de Testes Playwright dos Relatorios

## Resumo

Este arquivo e o mapa operacional para testar o modulo Relatorios com `playwright-cli`.

Nao iniciar testes sem confirmacao explicita. Quando a execucao for autorizada, seguir a ordem deste documento e parar imediatamente na primeira falha critica.

Ambiente assumido:

- Frontend Angular: `http://localhost:4200`
- API local via proxy: `/api`
- Login seed: `admin@interazap.com.br` / `password`
- Dados minimos de CRM, chat, IA e faturamento ja existentes para evitar todos os relatorios vazios
- Permissoes: `reports.crm.view`, `reports.chat.view`, `reports.ai.view`, `reports.billing.view`, `reports.admin.view`

Escopo:

- `/reports/sales-funnel`
- `/reports/revenue-sales`
- `/reports/salesperson-performance`
- `/reports/loss-reasons`
- `/reports/sla-resolution`
- `/reports/agent-performance`
- `/reports/csat-nps`
- `/reports/chat-volume`
- `/reports/ai-usage-cost`
- `/reports/media-transcription`
- `/reports/billing`
- `/reports/product-performance`
- `/reports/autopilot-performance`
- `/reports/team-activity`
- `/reports/contact-crm`

## Protocolo De Execucao

1. Abrir:

   ```bash
   playwright-cli open http://localhost:4200/auth/login
   ```

2. Fazer login e salvar estado:

   ```bash
   playwright-cli state-save .playwright-reports-auth.json
   ```

3. Antes de cada relatorio:

   ```bash
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

4. Em falha:

   ```bash
   playwright-cli screenshot --filename=reports-failure-<ID>.png
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

## Criterios Globais

### Sucesso

- Rota abre sem redirect indevido.
- Titulo e subtitulo do relatorio aparecem.
- Loading some.
- Estado final e um destes: dados renderizados, estado vazio controlado, ou erro controlado com botao `Tentar novamente`.
- Filtros abrem em drawer `Filtros do relatorio`.
- Campos comuns aparecem: busca, data inicial, data final, granularidade.
- Aplicar filtros refaz request com `start_date`, `end_date` e `granularity`.
- Limpar filtros volta para padrao.
- Exportacao CSV e XLSX dispara download quando suportada.
- Graficos/tabelas/KPIs nao quebram com valores zero.

### Falha

- Tela branca ou erro Angular no console.
- Request `/api/reports/**` retorna 500.
- Drawer de filtros nao abre.
- Aplicar filtros nao dispara request.
- Exportacao retorna erro silencioso ou trava loading.
- Grafico renderiza area vazia sem estado vazio/erro.

## REPORTS-00 - Login E Menu Relatorios

### Passos

1. Login em `/auth/login`.
2. Abrir grupo `Relatorios` no sidenav.
3. Validar links:
   - Funil de Vendas
   - Receita e Vendas
   - Performance de Vendedores
   - Motivos de Perda
   - SLA e Resolucao
   - Performance de Agentes
   - CSAT / NPS
   - Volume de Chat
   - Uso e Custo IA
   - Faturamento
   - Produtos
   - Automacoes
   - Atividade da Equipe
   - Contatos CRM

### Passou Se

- Menu mostra todos os links disponiveis ao usuario.
- Nenhum link do menu leva para `access-denied` com usuario admin.

## Fluxo Padrao Para Cada Relatorio

Executar este roteiro em todos os relatorios listados nas secoes seguintes.

### Passos Padrao

1. Navegar para a rota.
2. Aguardar loading finalizar.
3. Validar titulo da tela.
4. Abrir `Filtros`.
5. Preencher busca com `e2e`.
6. Definir data inicial para 30 dias atras.
7. Definir data final para hoje.
8. Alterar granularidade para `Dia`.
9. Clicar `Aplicar`.
10. Validar novo request com query params.
11. Abrir filtros novamente.
12. Clicar `Limpar filtros`.
13. Aplicar novamente.
14. Exportar CSV.
15. Exportar XLSX.
16. Clicar `Tentar novamente` somente se tela estiver em estado de erro controlado.

### Passou Se

- Relatorio nao trava em nenhuma etapa.
- Filtros fecham apos aplicar.
- Exportacao gera request `/api/reports/<slug>/export?format=csv|xlsx`, exceto transcricao de midia quando backend usar endpoint especifico.
- Estado vazio e aceitavel se nao houver dados, desde que seja visualmente explicito.

## REPORTS-01 - Funil De Vendas

**Rota:** `/reports/sales-funnel`
**Endpoint esperado:** `/api/reports/sales-funnel`

### Validacoes Especificas

- Titulo `Funil de Vendas`.
- KPIs: `Total de Negociacoes`, `Valor Total no Pipeline`.
- Grafico de funil/pipeline renderizado.
- Tabela por etapa com valores e conversao.

## REPORTS-02 - Receita E Vendas

**Rota:** `/reports/revenue-sales`
**Endpoint esperado:** `/api/reports/revenue-sales`

### Validacoes Especificas

- Titulo `Receita e Vendas`.
- KPIs: `Receita Total`, `Ticket Medio`, `Ganhas`, `Perdidas`, `Abertas`, `Taxa de Conversao`.
- Grafico de receita por periodo.
- Tabela de resumo por responsavel/funil conforme dados.

## REPORTS-03 - Performance De Vendedores

**Rota:** `/reports/salesperson-performance`
**Endpoint esperado:** `/api/reports/salesperson-performance`

### Validacoes Especificas

- Titulo `Desempenho de Vendas`.
- Grafico por vendedor.
- Tabela com vendedores, negocios, ganhos, perdidos, receita e taxa.
- Ordenacao visual nao quebra valores monetarios.

## REPORTS-04 - Motivos De Perda

**Rota:** `/reports/loss-reasons`
**Endpoint esperado:** `/api/reports/loss-reasons`

### Validacoes Especificas

- Titulo `Motivos de Perda`.
- Grafico de distribuicao por motivo.
- Tabela com motivo, quantidade e valor perdido.

## REPORTS-05 - SLA E Resolucao

**Rota:** `/reports/sla-resolution`
**Endpoint esperado:** `/api/reports/sla-resolution`

### Validacoes Especificas

- Titulo `SLA de Resolucao`.
- KPIs: `SLA 1a Resposta`, `SLA Resolucao`, `Tempo Medio 1a Resposta`, `Tempo Medio Resolucao`.
- Grafico por periodo.
- Tabela por agente/fila/canal.
- Secao adicional de detalhamento nao quebra quando sem dados.

## REPORTS-06 - Performance De Agentes

**Rota:** `/reports/agent-performance`
**Endpoint esperado:** `/api/reports/agent-performance`

### Validacoes Especificas

- Titulo `Desempenho de Agentes`.
- Tabela `Agentes`.
- Colunas numericas de atendimentos, tempo medio, SLA e avaliacoes renderizam.

## REPORTS-07 - CSAT E NPS

**Rota:** `/reports/csat-nps`
**Endpoint esperado:** `/api/reports/csat-nps`

### Validacoes Especificas

- Titulo `CSAT e NPS`.
- KPIs: `NPS Score`, `CSAT Medio`, `Total de Avaliacoes`, `Taxa de Resposta`.
- Graficos de CSAT e NPS.
- Tabela de avaliacoes.

## REPORTS-08 - Volume De Chat

**Rota:** `/reports/chat-volume`
**Endpoint esperado:** `/api/reports/chat-volume`

### Validacoes Especificas

- Titulo `Volume de Atendimento`.
- KPIs: `Total de Tickets`, `Media Diaria`, `Resolucao Automatica`, `Escalonados p/ Humano`.
- Grafico de volume.
- Tabela `Por Canal`.

## REPORTS-09 - Uso E Custo IA

**Rota:** `/reports/ai-usage-cost`
**Endpoint esperado:** `/api/reports/ai-usage-cost`

### Validacoes Especificas

- Titulo `Custos de IA`.
- KPIs: `Total de Tokens`, `Custo Total`, `Latencia Media`, `Total de Chamadas`.
- Graficos de custo/tokens.
- Tabela por modelo/operacao.

## REPORTS-10 - Transcricao De Midia

**Rota:** `/reports/media-transcription`
**Endpoint esperado:** `/api/ai/usage/transcription`

### Validacoes Especificas

- Titulo `Transcricao de Midia`.
- KPIs: `Total de Transcricoes`, `Custo Total`, `Tokens Utilizados`, `Latencia Media`.
- Graficos de uso e custo.
- Tabela de transcricoes por tipo/status.

## REPORTS-11 - Faturamento

**Rota:** `/reports/billing`
**Endpoint esperado:** `/api/reports/billing`

### Validacoes Especificas

- Titulo `Faturamento`.
- KPIs: `MRR`, `Faturado`, `Pago`, `Pendente`, `Vencido`, `Inadimplencia`.
- Grafico financeiro por periodo.
- Tabela de status de faturas/tenants.

## REPORTS-12 - Performance De Produtos

**Rota:** `/reports/product-performance`
**Endpoint esperado:** `/api/reports/product-performance`

### Validacoes Especificas

- Titulo `Desempenho de Produtos`.
- Grafico por produto.
- Tabela de produtos com quantidade, receita e taxa.

## REPORTS-13 - Automacoes

**Rota:** `/reports/autopilot-performance`
**Endpoint esperado:** `/api/reports/autopilot-performance`

### Validacoes Especificas

- Titulo `Desempenho do Autopilot`.
- KPI `Aprovacoes Pendentes`.
- Grafico de automacoes.
- Tabela `Playbooks`.

## REPORTS-14 - Atividade Da Equipe

**Rota:** `/reports/team-activity`
**Endpoint esperado:** `/api/reports/team-activity`

### Validacoes Especificas

- Titulo `Atividade da Equipe`.
- KPI `Membros Inativos`.
- Tabela com membros, atividade, interacoes e ultima acao.

## REPORTS-15 - Contatos CRM

**Rota:** `/reports/contact-crm`
**Endpoint esperado:** `/api/reports/contact-crm`

### Validacoes Especificas

- Titulo `Contatos CRM`.
- KPIs: `Ativos`, `Inativos`, `Novos no Periodo`, `Leads Frios`.
- Grafico de contatos.
- Tabela `Top Tags`.
- Secao adicional de contatos frios/segmentacao renderiza.

## REPORTS-16 - Permissoes E Acesso Negado

### Passos

1. Entrar com usuario sem uma permissao de relatorio, se existir.
2. Tentar abrir a rota correspondente.
3. Validar redirect ou tela `Acesso Negado`.
4. Entrar novamente como admin.
5. Validar acesso liberado.

### Passou Se

- Guards bloqueiam apenas usuario sem permissao.
- Usuario admin consegue acessar todos os relatorios do escopo.

## Checklist Final Do Modulo

- [ ] Menu Relatorios validado.
- [ ] Todos os 15 relatorios abriram.
- [ ] Filtros aplicaram em todos.
- [ ] Limpar filtros validado.
- [ ] Export CSV validado.
- [ ] Export XLSX validado.
- [ ] Estados vazio/erro/loading validados.
- [ ] Sem erros inesperados no console.
