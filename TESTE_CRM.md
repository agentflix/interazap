# TESTE_CRM.md - Plano de Testes Playwright do CRM

## Resumo

Este arquivo e o mapa operacional para testar o modulo CRM com `playwright-cli`.

Nao iniciar testes sem confirmacao explicita. Quando a execucao for autorizada, seguir a ordem deste documento e parar imediatamente na primeira falha.

Ambiente assumido:

- Frontend Angular: `http://localhost:4200`
- API local via proxy: `/api`
- Login seed: `admin@interazap.com.br` / `password`
- Escopo: menu CRM principal: Contatos, Empresas, Funis, Negociacoes, Agenda, Tarefas, Produtos, Motivos de Perda

Fora do escopo deste mapa:

- Tags
- Departamentos
- Horario de Atendimento
- Relatorios CRM

## Protocolo De Execucao

1. Abrir o browser:

   ```bash
   playwright-cli open http://localhost:4200/auth/login
   ```

2. Fazer login:

   - Preencher `dataTest="login-email"` com `admin@interazap.com.br`
   - Preencher `dataTest="login-password"` com `password`
   - Clicar em `Entrar`

3. Confirmar autenticacao:

   - URL deve sair de `/auth/login`
   - Dashboard ou layout autenticado deve aparecer
   - Menu lateral deve mostrar o grupo CRM

4. Salvar estado autenticado:

   ```bash
   playwright-cli state-save .playwright-crm-auth.json
   ```

5. Antes de cada tela:

   ```bash
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

6. Em qualquer falha:

   - Parar imediatamente
   - Nao continuar para o proximo caso
   - Coletar evidencia
   - Reportar plano de correcao antes de retomar

## Criterios Globais

### Sucesso

- A rota abre sem redirect indevido.
- A tela nao fica branca e nao quebra o layout principal.
- Nao ha erro visual bloqueante.
- Nao ha `console error` inesperado.
- Requests esperados para `/api/crm/**` retornam 2xx.
- Acoes de CRUD, filtros e navegacao mostram feedback visual ou mudanca persistida.

### Falha

- Tela branca, crash Angular ou erro no console.
- Request `/api/crm/**` retorna 4xx/5xx inesperado.
- Modal trava aberto ou nao abre.
- Botao aparenta funcionar, mas nao dispara request nem feedback.
- Item criado/editado nao aparece depois de reload/listagem.
- Usuario e redirecionado por permissao quando deveria acessar a tela.

## Protocolo De Falha E Correcao

Ao primeiro erro, registrar:

- Teste que falhou
- URL atual
- Passo exato
- Resultado esperado
- Resultado obtido
- Requests relevantes
- Console relevante
- Snapshot da pagina
- Screenshot
- Plano de correcao

Comandos de evidencia:

```bash
playwright-cli screenshot --filename=crm-failure-<ID>.png
playwright-cli snapshot
playwright-cli console
playwright-cli requests
```

Modelo de plano de correcao:

```md
## Falha <ID>

- Teste: CRM-XX
- URL:
- Passo:
- Esperado:
- Obtido:
- Evidencia:
- Causa provavel:
- Arquivos provaveis:
- Correcao proposta:
- Como confirmar depois:
```

## CRM-00 - Login E Navegacao Base

### Passos

1. Abrir `/auth/login`.
2. Preencher email e senha seed.
3. Entrar.
4. Validar que o layout autenticado carregou.
5. Abrir o grupo CRM no sidenav.
6. Validar links:
   - Contatos
   - Empresas
   - Funis
   - Negociacoes
   - Agenda
   - Tarefas
   - Produtos
   - Motivos de Perda

### Passou Se

- Login retorna sucesso.
- Usuario chega ao dashboard ou a uma rota autenticada.
- Menu CRM aparece com todos os links do escopo.

### Evidencia De Sucesso

- Snapshot mostrando layout autenticado.
- Requests de auth com 2xx.
- Snapshot mostrando links CRM no menu.

### Falha E Correcao

- Se login falhar, verificar `/api/auth/login`, credenciais seed e estado do backend.
- Se menu nao aparecer, verificar permissoes retornadas por `/api/auth/me` e `/api/auth/get-menu`.
- Arquivos provaveis: `app/src/app/pages/auth/login/login.ts`, `app/src/app/layout/components/sidenav/menu-config.ts`, guards de permissao.

## CRM-01 - Contatos

### Passos

1. Abrir `/crm/contacts`.
2. Validar carregamento da lista.
3. Buscar por termo existente e depois limpar busca.
4. Abrir filtros e alternar status Ativo/Inativo/Todos.
5. Validar ordenacao por colunas disponiveis.
6. Validar paginacao se houver mais de uma pagina.
7. Selecionar um ou mais registros e confirmar que aparece acao de exclusao em massa.
8. Criar contato `E2E CRM Contato <timestamp>` com telefone unico.
9. Editar email/notas do contato criado.
10. Abrir exclusao individual e cancelar.
11. Excluir o contato criado.
12. Acionar exportacao CSV.
13. Abrir modal de importacao e validar etapa de selecao de arquivo CSV, sem importar massa real.

### Passou Se

- Lista carrega sem erro.
- Busca/filtro/ordenacao alteram a lista ou requests.
- Contato criado aparece na lista.
- Edicao persiste depois de recarregar/listar.
- Exclusao remove o contato criado.
- Exportacao dispara request/download.
- Modal de importacao abre e mostra campo de arquivo.

### Evidencia De Sucesso

- Snapshot da lista.
- Requests `/api/crm/contacts` 2xx.
- Toasts de sucesso.
- Snapshot do contato criado/editado.
- Request de exportacao 2xx/blob.

### Falha E Correcao

- Se listagem falhar, verificar `ContactService.list`.
- Se formulario nao salvar, verificar validacoes e payload em `crm-contact-form`.
- Se import/export falhar, verificar componentes `crm-contact-import` e `crm-contact-export`.
- Arquivos provaveis: `crm-contacts.ts`, `crm-contacts.html`, `crm-contact-form.ts`, `crm-contact.service.ts`.

## CRM-02 - Empresas

### Passos

1. Abrir `/crm/companies`.
2. Validar lista, busca, filtro status, ordenacao e paginacao.
3. Criar empresa `E2E CRM Empresa <timestamp>` com email e telefone.
4. Abrir painel de detalhes da empresa criada.
5. Editar cidade/UF ou outro campo nao destrutivo.
6. Abrir exclusao e cancelar.
7. Excluir a empresa criada.

### Passou Se

- Empresa criada aparece na lista.
- Painel de detalhes abre com os dados corretos.
- Edicao persiste.
- Exclusao remove o registro criado.

### Evidencia De Sucesso

- Snapshot da lista e painel de detalhes.
- Requests `/api/crm/companies` 2xx.
- Toast de sucesso de criacao/edicao/exclusao.

### Falha E Correcao

- Se painel nao abrir, verificar estado `detailCompany`.
- Se formulario nao salvar, verificar `crm-company-form`.
- Arquivos provaveis: `crm-companies.ts`, `crm-company-form.ts`, `crm-company.service.ts`.

## CRM-03 - Funis

### Passos

1. Abrir `/crm/funnels`.
2. Validar lista, busca e filtro status.
3. Criar funil `E2E Funil <timestamp>`.
4. No formulario, adicionar pelo menos duas etapas.
5. Validar obrigatoriedade do nome da etapa.
6. Salvar funil.
7. Editar descricao/status.
8. Excluir funil criado.

### Passou Se

- Funil criado aparece na lista.
- Etapas aparecem no formulario/detalhe.
- Validacao bloqueia etapa sem nome.
- Edicao persiste.
- Exclusao remove o funil criado.

### Evidencia De Sucesso

- Requests `/api/crm/funnels` 2xx.
- Requests de `/steps` 2xx quando aplicavel.
- Snapshot do funil com etapas.

### Falha E Correcao

- Se etapas nao salvarem, verificar payload de `createStep`/`updateStep`.
- Se filtro nao funcionar, verificar `filterStatusControl`.
- Arquivos provaveis: `crm-funnels.ts`, `crm-funnel-form.ts`, `crm-funnel.service.ts`.

## CRM-04 - Produtos

### Passos

1. Abrir `/crm/products-services`.
2. Validar busca.
3. Validar filtros de status e tipo.
4. Criar produto `E2E Produto <timestamp>` com preco e estoque quando aplicavel.
5. Criar servico `E2E Servico <timestamp>` sem estoque.
6. Abrir detalhes dos itens criados.
7. Editar preco/descricao.
8. Excluir os itens criados.

### Passou Se

- Produto e servico aparecem na lista.
- Filtro de tipo separa produto/servico.
- Painel de detalhes abre.
- Edicao persiste.
- Exclusao remove os itens criados.

### Evidencia De Sucesso

- Snapshot da lista filtrada.
- Requests `/api/crm/products` 2xx.
- Toasts de sucesso.

### Falha E Correcao

- Se tipo produto/servico nao filtrar, verificar query `type`.
- Se estoque aparecer para servico indevidamente, verificar formulario.
- Arquivos provaveis: `crm-products-services.ts`, `crm-product-service-form.ts`, `crm-product-service.service.ts`.

## CRM-05 - Motivos De Perda

### Passos

1. Abrir `/crm/reason-losses`.
2. Validar busca local.
3. Validar filtro status.
4. Validar selecao multipla.
5. Criar motivo `E2E Motivo <timestamp>`.
6. Alternar `requer comentario`.
7. Editar descricao/status.
8. Excluir motivo criado.

### Passou Se

- Motivo criado aparece.
- Busca encontra o motivo criado.
- Edicao persiste.
- Exclusao remove o motivo criado.

### Evidencia De Sucesso

- Snapshot da lista.
- Requests `/api/crm/reason-losses` 2xx.
- Toasts de sucesso.

### Falha E Correcao

- Se busca local nao filtrar, verificar `filteredReasons`.
- Se formulario nao salvar, verificar validators e payload.
- Arquivos provaveis: `crm-reason-losses.ts`, `crm-reason-loss-form.ts`, `crm-reason-loss.service.ts`.

## CRM-06 - Negociacoes Lista E Kanban

### Passos

1. Abrir `/crm/negotiations`.
2. Validar modo Lista via `negotiation-toggle-list`.
3. Validar modo Kanban via `negotiation-toggle-kanban`.
4. Abrir filtros.
5. Aplicar busca/status/funil quando houver dados.
6. Criar negociacao `E2E Negociacao <timestamp>` usando empresa, contato, funil e etapa existentes.
7. Confirmar que a negociacao aparece na lista.
8. Abrir detalhe pelo link da tabela/card.
9. Voltar ao Kanban.
10. Se houver duas etapas disponiveis, mover card entre etapas.

### Passou Se

- Lista e Kanban alternam corretamente.
- Filtros nao quebram a tela.
- Selects obrigatorios carregam opcoes.
- Negociacao criada aparece.
- Detalhe abre.
- Move no Kanban chama endpoint de movimentacao e atualiza card.

### Evidencia De Sucesso

- Snapshot modo Lista.
- Snapshot modo Kanban.
- Requests `/api/crm/negotiations` 2xx.
- Request `/api/crm/negotiations-kanban` 2xx.
- Request `/api/crm/negotiations/<id>/move` 2xx quando houver drag/drop.

### Falha E Correcao

- Se selects nao carregarem, verificar services de empresas/contatos/funis.
- Se Kanban nao carregar, verificar `loadKanban`.
- Se drag/drop falhar, verificar `onKanbanDrop`.
- Arquivos provaveis: `negotiations.ts`, `negotiations.html`, `negotiation-form.ts`, `negotiation.service.ts`.

## CRM-07 - Detalhe Da Negociacao

### Passos

1. Abrir detalhe da negociacao criada em CRM-06.
2. Validar header, resumo, contato principal e empresa.
3. Validar abas:
   - Historico
   - Tarefas
   - Contatos
   - Produtos e Servicos
   - Propostas
   - Arquivos
4. Criar anotacao no Historico.
5. Criar tarefa.
6. Vincular contato se houver contato disponivel.
7. Adicionar produto/servico se houver item disponivel.
8. Abrir edicao de detalhes e alterar campo nao destrutivo.
9. Abrir modal de perda e validar campos sem confirmar perda se dados obrigatorios faltarem.

### Passou Se

- Todas as abas carregam sem erro.
- Anotacao criada aparece no historico.
- Tarefa criada aparece.
- Vinculo de contato e produto aparecem quando executados.
- Edicao do resumo persiste.

### Evidencia De Sucesso

- Snapshot de cada aba.
- Requests aninhados de negociacao 2xx.
- Toasts de sucesso.

### Falha E Correcao

- Se uma aba quebra, reportar a aba especifica.
- Se item criado nao aparece, verificar service aninhado correspondente.
- Arquivos provaveis: `negotiation-show.ts` e componentes em `negotiation-show/components`.

## CRM-08 - Agenda

### Passos

1. Abrir `/crm/agenda`.
2. Validar modos:
   - Mes
   - Semana
   - Dia
   - Lista
3. Abrir filtros.
4. Buscar por texto.
5. Aplicar tipo/local/recorrencia/lembretes quando disponivel.
6. Criar evento `E2E Evento <timestamp>` com inicio/fim validos.
7. Editar local/descricao.
8. Excluir evento criado.
9. Acionar exportacao CSV.
10. Acionar exportacao XLSX.

### Passou Se

- Calendario e lista carregam.
- Alternancia de modos funciona.
- Filtros criam chips ou alteram resultado/request.
- Evento criado aparece no calendario/lista.
- Edicao persiste.
- Exclusao remove evento.
- Exportacoes disparam request/download.

### Evidencia De Sucesso

- Snapshot dos modos.
- Requests `/api/crm/events` 2xx.
- Requests de exportacao 2xx/blob.
- Toasts de sucesso.

### Falha E Correcao

- Se calendario nao renderizar, verificar FullCalendar/options.
- Se formulario nao salvar, verificar datas e payload.
- Arquivos provaveis: `agenda.ts`, `agenda.html`, `agenda-form.ts`, `event.service.ts`.

## CRM-09 - Tarefas

### Passos

1. Abrir `/crm/negotiation-tasks`.
2. Validar lista de tarefas do usuario.
3. Validar botao de atualizar.
4. Validar link para `/crm/negotiations`.
5. Se a tarefa criada em CRM-07 aparecer, alternar status/conclusao.
6. Confirmar atualizacao visual.

### Passou Se

- Lista carrega sem erro.
- Refresh dispara nova consulta.
- Link para negociacoes funciona.
- Status da tarefa muda quando aplicavel.

### Evidencia De Sucesso

- Snapshot da lista.
- Request `/api/crm/negotiations/tasks/user` 2xx.
- Snapshot antes/depois da mudanca de status.

### Falha E Correcao

- Se lista nao carregar, verificar endpoint de tarefas do usuario.
- Se realtime interferir ou duplicar itens, registrar console/requests.
- Arquivos provaveis: `negotiation-tasks.ts`, `negotiation-tasks.html`, `negotiation-task.service.ts`.

## Ordem Recomendada

1. CRM-00 Login e Navegacao Base
2. CRM-02 Empresas
3. CRM-01 Contatos
4. CRM-03 Funis
5. CRM-04 Produtos
6. CRM-05 Motivos de Perda
7. CRM-06 Negociacoes Lista e Kanban
8. CRM-07 Detalhe da Negociacao
9. CRM-08 Agenda
10. CRM-09 Tarefas

Esta ordem cria dados base antes dos fluxos dependentes.

## Dados E Limpeza

Usar prefixo `E2E` em todos os dados criados:

- `E2E CRM Empresa <timestamp>`
- `E2E CRM Contato <timestamp>`
- `E2E Funil <timestamp>`
- `E2E Produto <timestamp>`
- `E2E Servico <timestamp>`
- `E2E Motivo <timestamp>`
- `E2E Negociacao <timestamp>`
- `E2E Evento <timestamp>`

Ao final da execucao completa, remover todos os registros criados durante o teste, preferencialmente pela propria UI para validar exclusao.

## Checklist De Execucao

- [ ] CRM-00 Login e Navegacao Base
- [ ] CRM-01 Contatos
- [ ] CRM-02 Empresas
- [ ] CRM-03 Funis
- [ ] CRM-04 Produtos
- [ ] CRM-05 Motivos de Perda
- [ ] CRM-06 Negociacoes Lista e Kanban
- [ ] CRM-07 Detalhe da Negociacao
- [ ] CRM-08 Agenda
- [ ] CRM-09 Tarefas

## Resultado Final

Preencher ao terminar a execucao autorizada:

```md
## Resultado

- Data:
- Ambiente:
- Executor:
- Resultado geral: PASSOU / FALHOU
- Ultimo teste executado:
- Evidencias:
- Falhas encontradas:
- Correcoes propostas:
```
