# TESTE_PLATFORM.md
> Plano de testes E2E com Playwright — módulos de Plataforma
> **Status:** PLANEJADO | **Execução:** sob demanda via `/playwright-cli`

---

## Pré-condições Globais

| # | Condição | Evidência esperada |
|---|----------|--------------------|
| P1 | App rodando em `http://localhost:4200` | HTTP 200 na raiz |
| P2 | Usuário super-admin existe no sistema | Login bem-sucedido |
| P3 | Pelo menos 1 plano ativo disponível | Dropdown de planos preenchido |

**Credenciais de teste:** definir via variável de ambiente `E2E_ADMIN_EMAIL` / `E2E_ADMIN_PASSWORD`

---

## Ordem de Execução

> Dependências: Planos → Tenants → Usuários → Leads → Faturas → Perfis de Acesso
> Cada grupo pode ser executado independentemente desde que pré-condições estejam satisfeitas.

---

## BLOCO 1 — Login

### TC-LOGIN-01: Login com credenciais válidas
**Rota:** `/login`

| Passo | Ação | Seletor / Dado |
|-------|------|----------------|
| 1 | Navegar para `/login` | — |
| 2 | Preencher campo e-mail | `input[type=email]` → valor: admin@email |
| 3 | Preencher senha | `input[type=password]` → valor: senha |
| 4 | Clicar em "Entrar" | `button[type=submit]` |
| 5 | Aguardar redirecionamento | URL muda para `/dashboard` ou `/` |

**Evidência de sucesso:** URL não contém `/login`; sem mensagem de erro na tela.
**Evidência de falha:** Toast com erro OU URL permanece `/login`.
**Ação em falha:** PARAR todos os testes — sem login nada funciona.

---

## BLOCO 2 — Planos (`/platform/plans`)

### TC-PLANS-01: Listar planos
| Passo | Ação |
|-------|------|
| 1 | Navegar para `/platform/plans` |
| 2 | Aguardar tabela carregar |

**Evidência de sucesso:** Tabela visível com colunas Nome, Preço, Status; sem spinner.
**Evidência de falha:** Mensagem de erro ou tabela vazia com alert de falha de rede.

---

### TC-PLANS-02: Criar plano válido
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Clicar "Novo Plano" | — |
| 2 | Verificar modal aberto | Título "Novo plano" visível |
| 3 | Preencher Nome | `Plano E2E Test` |
| 4 | Verificar slug auto-gerado | Campo slug deve conter `plano-e2e-test` |
| 5 | Definir Limite de Usuários | `5` |
| 6 | Definir Armazenamento (modo) | `LIMITED` |
| 7 | Definir Armazenamento (GB) | `10` |
| 8 | Definir IA | `Desabilitado` |
| 9 | Definir Integrações WhatsApp | `2` |
| 10 | Definir Modo Negociações | `LIMITED` |
| 11 | Definir Limite Negociações | `200` |
| 12 | Definir Preço Mensal | `199` |
| 13 | Manter Status Ativo | switch ON |
| 14 | Clicar "Salvar" | — |

**Evidência de sucesso:** Modal fecha; item `Plano E2E Test` aparece na tabela; toast de sucesso.
**Evidência de falha:** Modal permanece aberto com erros de validação OU toast de erro da API.
**Ação em falha:** Capturar screenshot + log do console; PARAR BLOCO 3 (depende de plano).

---

### TC-PLANS-03: Validação — criar plano sem nome
| Passo | Ação |
|-------|------|
| 1 | Clicar "Novo Plano" |
| 2 | Deixar campo Nome vazio |
| 3 | Clicar "Salvar" sem preencher nada |

**Evidência de sucesso:** Mensagem de erro inline no campo Nome ("Nome é obrigatório" ou similar); modal não fecha; botão Salvar desabilitado ou dispara validação.
**Evidência de falha:** Requisição é enviada mesmo com form inválido OU modal fecha sem criar.

---

### TC-PLANS-04: Slug auto-gerado a partir do nome
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Abrir modal "Novo Plano" | — |
| 2 | Digitar no campo Nome | `Plano Especial 2025` |
| 3 | Verificar campo Slug (sem editar) | deve conter `plano-especial-2025` |

**Evidência de sucesso:** Slug preenchido automaticamente com valor slugificado correto.
**Evidência de falha:** Campo Slug vazio ou com valor diferente do esperado.

---

### TC-PLANS-05: Editar plano existente
**Pré-condição:** TC-PLANS-02 passou (plano `Plano E2E Test` existe).

| Passo | Ação | Dado |
|-------|------|------|
| 1 | Localizar linha `Plano E2E Test` na tabela | — |
| 2 | Clicar ícone editar (lápis) | — |
| 3 | Verificar campo Nome pré-preenchido | `Plano E2E Test` |
| 4 | Alterar Preço Mensal | `249` |
| 5 | Clicar "Salvar" | — |

**Evidência de sucesso:** Modal fecha; linha na tabela exibe novo preço `R$ 249,00`; toast de sucesso.
**Evidência de falha:** Valor não atualizado OU toast de erro.

---

### TC-PLANS-06: Desativar plano (toggle is_active)
**Pré-condição:** TC-PLANS-02 passou.

| Passo | Ação |
|-------|------|
| 1 | Abrir edição do plano `Plano E2E Test` |
| 2 | Desligar switch "Ativo" |
| 3 | Salvar |

**Evidência de sucesso:** Badge de status na tabela muda para "Inativo".
**Evidência de falha:** Badge permanece "Ativo".

---

### TC-PLANS-07: Modo armazenamento UNLIMITED desabilita campo GB
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Abrir "Novo Plano" | — |
| 2 | Selecionar Armazenamento → `UNLIMITED` | — |
| 3 | Verificar campo GB | deve estar desabilitado/oculto |
| 4 | Reverter para `LIMITED` | — |
| 5 | Verificar campo GB | deve estar habilitado |

**Evidência de sucesso:** Campo `storage_limit_gb` habilitado/desabilitado conforme seleção de modo.
**Evidência de falha:** Campo sempre visível/editável independente do modo.

---

### TC-PLANS-08: Excluir plano
**Pré-condição:** Existe plano criado para deleção (não o usado em tenants).

| Passo | Ação |
|-------|------|
| 1 | Clicar ícone lixeira no plano alvo |
| 2 | Modal de confirmação aparece |
| 3 | Confirmar exclusão |

**Evidência de sucesso:** Plano some da tabela; toast de sucesso.
**Evidência de falha:** Plano permanece ou toast de erro.

---

## BLOCO 3 — Tenants / Inquilinos (`/platform/tenants`)

**Pré-condição:** Plano ativo disponível (TC-PLANS-02 ou pre-existing).

### TC-TENANT-01: Listar tenants
| Passo | Ação |
|-------|------|
| 1 | Navegar para `/platform/tenants` |
| 2 | Aguardar tabela carregar |

**Evidência de sucesso:** Tabela visível com colunas Nome, Segmento, Plano, Status.
**Evidência de falha:** Spinner infinito ou mensagem de erro.

---

### TC-TENANT-02: Criar tenant válido
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Clicar "Novo Inquilino" | — |
| 2 | Preencher Nome | `Empresa E2E Test` |
| 3 | Preencher Documento (CNPJ) | `12.345.678/0001-90` |
| 4 | Preencher E-mail | `e2e@empresa.com` |
| 5 | Selecionar Segmento IA | primeiro item disponível |
| 6 | Selecionar Plano | `Plano E2E Test` (ou primeiro ativo) |
| 7 | Manter Status Ativo | switch ON |
| 8 | Clicar "Salvar" | — |

**Evidência de sucesso:** Modal fecha; `Empresa E2E Test` aparece na tabela; toast de sucesso.
**Evidência de falha:** Modal com erros de validação OU toast de erro da API.
**Ação em falha:** Capturar screenshot; PARAR TC-TENANT-03 a TC-TENANT-10 e TC-USERS-02.

---

### TC-TENANT-03: Auto-preenchimento por CNPJ
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Abrir "Novo Inquilino" | — |
| 2 | Digitar CNPJ válido de empresa real | ex: `00.000.000/0001-91` |
| 3 | Aguardar botão "Pesquisar CNPJ" habilitar | — |
| 4 | Clicar "Pesquisar CNPJ" | — |
| 5 | Verificar campos preenchidos | Nome, Endereço, etc. |

**Evidência de sucesso:** Toast/feedback `"Dados do CNPJ preenchidos automaticamente."` + campos preenchidos.
**Evidência de falha:** Campos permanecem vazios ou erro de lookup.

---

### TC-TENANT-04: Auto-preenchimento por CEP
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Abrir modal de tenant | — |
| 2 | Digitar CEP válido | `01310-100` |
| 3 | Aguardar auto-preenchimento | — |
| 4 | Verificar campos Logradouro, Bairro, Cidade, UF | preenchidos |

**Evidência de sucesso:** Campos de endereço preenchidos automaticamente após 8 dígitos do CEP.
**Evidência de falha:** Campos permanecem vazios.

---

### TC-TENANT-05: Validação — criar tenant sem nome
| Passo | Ação |
|-------|------|
| 1 | Abrir "Novo Inquilino" |
| 2 | Preencher apenas Documento e Plano |
| 3 | Clicar "Salvar" |

**Evidência de sucesso:** Erro inline no campo Nome; modal não fecha.
**Evidência de falha:** Requisição enviada com nome vazio.

---

### TC-TENANT-06: Editar tenant
**Pré-condição:** TC-TENANT-02 passou.

| Passo | Ação | Dado |
|-------|------|------|
| 1 | Localizar `Empresa E2E Test` | — |
| 2 | Clicar ícone editar | — |
| 3 | Verificar campo Plano desabilitado | campo `plan_id` deve ser readonly em edit |
| 4 | Alterar E-mail | `novo@empresa.com` |
| 5 | Salvar | — |

**Evidência de sucesso:** Modal fecha; dados atualizados no painel de detalhes; toast de sucesso.
**Evidência de falha:** Campo plano editável OU dados não atualizados.

---

### TC-TENANT-07: Ver detalhes do tenant (side panel)
**Pré-condição:** TC-TENANT-02 passou.

| Passo | Ação |
|-------|------|
| 1 | Clicar ícone "olho" na linha de `Empresa E2E Test` |
| 2 | Painel lateral abre |
| 3 | Verificar seções: Empresa, Plano Contratado, Recursos Disponíveis |
| 4 | Verificar progress bars de Usuários, WhatsApp, Armazenamento, Negociações |

**Evidência de sucesso:** Painel visível com todas as seções; valores numéricos corretos; progress bars renderizadas.
**Evidência de falha:** Painel vazio, seções ausentes, ou valores `undefined`.

---

### TC-TENANT-08: Filtro por status
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Selecionar filtro de status | `Inativo` |
| 2 | Verificar lista filtrada | apenas tenants inativos |
| 3 | Selecionar | `Ativo` |
| 4 | Verificar lista filtrada | apenas tenants ativos |
| 5 | Selecionar | `Todos` |
| 6 | Verificar lista completa | — |

**Evidência de sucesso:** Tabela muda conforme filtro selecionado; status das linhas coerente com filtro.
**Evidência de falha:** Tabela não muda ou exibe itens incorretos.

---

### TC-TENANT-09: Busca por nome
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Digitar no campo de busca | `Empresa E2E` |
| 2 | Aguardar debounce | — |
| 3 | Verificar resultado | apenas `Empresa E2E Test` na tabela |
| 4 | Limpar busca | — |
| 5 | Verificar lista completa restaurada | — |

**Evidência de sucesso:** Filtragem funcional; limpar restaura lista.
**Evidência de falha:** Busca não filtra ou não restaura.

---

### TC-TENANT-10: Soft delete e restauração
**Pré-condição:** TC-TENANT-02 passou.

| Passo | Ação |
|-------|------|
| 1 | Clicar ícone lixeira em `Empresa E2E Test` |
| 2 | Confirmar exclusão |
| 3 | Selecionar filtro "Lixeira" |
| 4 | Localizar `Empresa E2E Test` na lixeira |
| 5 | Clicar ícone de restauração |
| 6 | Verificar tenant restaurado na lista ativa |

**Evidência de sucesso:** Tenant vai para lixeira; ícone restauração visível; após restaurar, aparece na lista ativa com status "Ativo".
**Evidência de falha:** Tenant não vai para lixeira OU restauração não funciona.

---

### TC-TENANT-11: Criar usuário para tenant via ícone user-plus
**Pré-condição:** TC-TENANT-02 passou.

| Passo | Ação | Dado |
|-------|------|------|
| 1 | Localizar `Empresa E2E Test` na lista | — |
| 2 | Clicar ícone "user-plus" (criar usuário) | — |
| 3 | Verificar modal de usuário aberto | campo empresa pré-preenchido e desabilitado |
| 4 | Preencher Nome | `User do Tenant E2E` |
| 5 | Preencher E-mail | `user-tenant@empresa.com` |
| 6 | Preencher Senha | `Senha@123` |
| 7 | Confirmar Senha | `Senha@123` |
| 8 | Salvar | — |

**Evidência de sucesso:** Modal fecha; toast de sucesso; usuário aparece em `/platform/users` vinculado a `Empresa E2E Test`.
**Evidência de falha:** Modal de usuário não pré-preenche empresa OU erro ao salvar.

---

## BLOCO 4 — Usuários de Plataforma (`/platform/users`)

### TC-USERS-01: Listar usuários
| Passo | Ação |
|-------|------|
| 1 | Navegar para `/platform/users` |
| 2 | Aguardar tabela |

**Evidência de sucesso:** Tabela com colunas Usuário (Avatar+Nome+Email+Badge), Empresa, Status.
**Evidência de falha:** Tabela vazia sem mensagem ou spinner infinito.

---

### TC-USERS-02: Criar usuário de plataforma
**Pré-condição:** Existe ao menos 1 tenant ativo.

| Passo | Ação | Dado |
|-------|------|------|
| 1 | Clicar "Novo Usuário" | — |
| 2 | Preencher Nome | `Usuário E2E` |
| 3 | Preencher E-mail | `usuario-e2e@test.com` |
| 4 | Selecionar Empresa | `Empresa E2E Test` |
| 5 | Preencher Senha | `Senha@123` |
| 6 | Confirmar Senha | `Senha@123` |
| 7 | Manter Status Ativo | — |
| 8 | Salvar | — |

**Evidência de sucesso:** Modal fecha; usuário `Usuário E2E` na tabela; toast de sucesso.
**Evidência de falha:** Erro de validação OU toast de erro API.

---

### TC-USERS-03: Validação — senha obrigatória na criação
| Passo | Ação |
|-------|------|
| 1 | Abrir "Novo Usuário" |
| 2 | Preencher Nome, E-mail, Empresa |
| 3 | Deixar campos Senha vazios |
| 4 | Tentar Salvar |

**Evidência de sucesso:** Erro inline em Senha ("Senha é obrigatória"); modal não fecha.
**Evidência de falha:** Requisição enviada sem senha.

---

### TC-USERS-04: Validação — senhas não coincidem
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Abrir "Novo Usuário" | — |
| 2 | Preencher todos os campos | — |
| 3 | Senha | `Senha@123` |
| 4 | Confirmar Senha | `OutraSenha@456` |
| 5 | Salvar | — |

**Evidência de sucesso:** Erro inline em Confirmar Senha (`"As senhas precisam ser idênticas."`); modal não fecha.
**Evidência de falha:** Requisição enviada com senhas divergentes.

---

### TC-USERS-05: Editar usuário (sem alterar senha)
**Pré-condição:** TC-USERS-02 passou.

| Passo | Ação | Dado |
|-------|------|------|
| 1 | Localizar `Usuário E2E` | — |
| 2 | Clicar ícone editar | — |
| 3 | Verificar campos pré-preenchidos | Nome, E-mail corretos |
| 4 | Verificar senha não obrigatória em edição | campos Senha vazios = permitido |
| 5 | Alterar Nome | `Usuário E2E Editado` |
| 6 | Salvar | — |

**Evidência de sucesso:** Modal fecha; nome atualizado na tabela; toast de sucesso.
**Evidência de falha:** Erro de senha obrigatória em edição OU nome não atualizado.

---

### TC-USERS-06: Ativar/desativar usuário
**Pré-condição:** TC-USERS-02 passou.

| Passo | Ação |
|-------|------|
| 1 | Abrir edição de `Usuário E2E Editado` |
| 2 | Desligar switch "Ativo" |
| 3 | Salvar |
| 4 | Verificar badge "Inativo" na tabela |
| 5 | Reabrir e religar switch |
| 6 | Salvar |
| 7 | Verificar badge "Ativo" |

**Evidência de sucesso:** Badge alterna entre Ativo/Inativo conforme switch.
**Evidência de falha:** Badge não muda.

---

### TC-USERS-07: Busca por nome/email
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Digitar no campo busca | `E2E` |
| 2 | Verificar resultado filtrado | apenas usuários com `E2E` no nome ou email |
| 3 | Limpar busca | — |

**Evidência de sucesso:** Filtragem correta; limpeza restaura lista.
**Evidência de falha:** Busca não filtra.

---

### TC-USERS-08: Usuário principal não pode ser excluído
| Passo | Ação |
|-------|------|
| 1 | Localizar usuário marcado como "Principal" na tabela |
| 2 | Verificar ícone lixeira ausente/desabilitado |
| 3 | Verificar checkbox de seleção desabilitado |

**Evidência de sucesso:** Usuário principal sem ícone de exclusão e sem checkbox habilitado.
**Evidência de falha:** Ícone de exclusão visível e clicável para usuário principal.

---

### TC-USERS-09: Excluir usuário
**Pré-condição:** TC-USERS-02 passou; usuário não é principal.

| Passo | Ação |
|-------|------|
| 1 | Clicar ícone lixeira em `Usuário E2E Editado` |
| 2 | Confirmar exclusão no modal |

**Evidência de sucesso:** Usuário removido da tabela; toast de sucesso.
**Evidência de falha:** Usuário permanece OU toast de erro.

---

## BLOCO 5 — Leads (`/platform/leads`)

### TC-LEADS-01: Listar leads
| Passo | Ação |
|-------|------|
| 1 | Navegar para `/platform/leads` |
| 2 | Aguardar tabela |

**Evidência de sucesso:** Tabela com colunas Nome+Telefone, E-mail, Empresa; botão Export CSV visível.
**Evidência de falha:** Acesso negado (403) ou tabela vazia com erro.

---

### TC-LEADS-02: Busca por nome/email/telefone
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Digitar no campo busca | qualquer termo visível na tabela |
| 2 | Verificar resultados filtrados | apenas itens que contêm o termo |
| 3 | Limpar busca | — |

**Evidência de sucesso:** Filtro funcional; limpeza restaura lista.
**Evidência de falha:** Busca não filtra.

---

### TC-LEADS-03: Exportar leads CSV
| Passo | Ação |
|-------|------|
| 1 | Clicar botão de export CSV |
| 2 | Verificar download iniciado |

**Evidência de sucesso:** Arquivo CSV baixado (dialog de download OU arquivo em `~/Downloads`).
**Evidência de falha:** Nenhum download iniciado OU toast de erro.

---

### TC-LEADS-04: Converter lead em contato
**Pré-condição:** Existe ao menos 1 lead na lista.

| Passo | Ação |
|-------|------|
| 1 | Clicar botão "Converter" em um lead |
| 2 | Modal `LeadConvertModalComponent` abre |
| 3 | Verificar campos de conversão disponíveis |
| 4 | Preencher campos requeridos |
| 5 | Confirmar conversão |

**Evidência de sucesso:** Modal fecha; toast de sucesso; lead convertido (removido da lista ou marcado como convertido).
**Evidência de falha:** Modal não abre OU erro na conversão.

---

## BLOCO 6 — Faturas (`/platform/invoices`)

### TC-INVOICES-01: Listar faturas
| Passo | Ação |
|-------|------|
| 1 | Navegar para `/platform/invoices` |
| 2 | Aguardar tabela |

**Evidência de sucesso:** Tabela com colunas Empresa, Referência, Plano, Valor, Vencimento, Pago em, Status; filtros visíveis.
**Evidência de falha:** Spinner infinito ou mensagem de erro.

---

### TC-INVOICES-02: Criar fatura válida
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Clicar "Nova Fatura" | — |
| 2 | Selecionar Empresa | `Empresa E2E Test` |
| 3 | Selecionar Plano | qualquer plano ativo |
| 4 | Preencher Referência (YYYY-MM) | mês atual ex: `2026-05` |
| 5 | Preencher Valor | `199.00` |
| 6 | Preencher Vencimento | data futura ex: `2026-06-01` |
| 7 | Status | `draft` (Rascunho) |
| 8 | Salvar | — |

**Evidência de sucesso:** Modal fecha; fatura aparece na tabela com status "Rascunho"; toast de sucesso.
**Evidência de falha:** Erro de validação OU toast de erro API.

---

### TC-INVOICES-03: Validações do formulário de fatura
| Sub-teste | Campo ausente | Resultado esperado |
|-----------|---------------|--------------------|
| 03a | Empresa | Erro inline em Empresa |
| 03b | Referência | Erro inline em Referência |
| 03c | Valor = 0 | Erro inline (mínimo > 0) |
| 03d | Referência formato errado (`2026-13`) | Erro de pattern inválido |
| 03e | Vencimento vazio | Erro inline em Vencimento |

**Evidência de sucesso:** Cada sub-teste mostra erro inline correto; modal não fecha.
**Evidência de falha:** Requisição enviada com dados inválidos.

---

### TC-INVOICES-04: Filtrar por status
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Selecionar filtro Status | `Rascunho` |
| 2 | Verificar lista | apenas faturas com badge "Rascunho" |
| 3 | Selecionar | `Pendente` |
| 4 | Verificar lista | apenas faturas com badge "Pendente" |
| 5 | Limpar filtros | botão "Limpar filtros" |
| 6 | Verificar lista completa | — |

**Evidência de sucesso:** Filtragem por status funcional; limpar restaura lista.
**Evidência de falha:** Lista não muda com filtro.

---

### TC-INVOICES-05: Filtrar por empresa
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Selecionar filtro Empresa | `Empresa E2E Test` |
| 2 | Verificar lista | apenas faturas de `Empresa E2E Test` |
| 3 | Limpar filtros | — |

**Evidência de sucesso:** Apenas faturas da empresa selecionada visíveis.
**Evidência de falha:** Faturas de outras empresas visíveis.

---

### TC-INVOICES-06: Filtrar por intervalo de vencimento
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Definir "Vencimento de" | `2026-01-01` |
| 2 | Definir "Vencimento até" | `2026-12-31` |
| 3 | Verificar lista | apenas faturas com vencimento no período |

**Evidência de sucesso:** Faturas fora do intervalo não aparecem.
**Evidência de falha:** Intervalo ignorado.

---

### TC-INVOICES-07: Cancelar fatura
**Pré-condição:** TC-INVOICES-02 passou; fatura com status != paid && != cancelled.

| Passo | Ação |
|-------|------|
| 1 | Localizar fatura criada em TC-INVOICES-02 |
| 2 | Clicar botão cancelar |
| 3 | Modal de confirmação aparece com mensagem dinâmica |
| 4 | Confirmar cancelamento |

**Evidência de sucesso:** Status da fatura muda para "Cancelado"; toast de sucesso.
**Evidência de falha:** Status não muda OU toast de erro.

---

### TC-INVOICES-08: Botão cancelar ausente para fatura paga/cancelada
| Passo | Ação |
|-------|------|
| 1 | Localizar fatura com status `Pago` ou `Cancelado` |
| 2 | Verificar ausência do botão cancelar na linha |

**Evidência de sucesso:** Botão cancelar não visível para esses status.
**Evidência de falha:** Botão cancelar visível mesmo para fatura paga/cancelada.

---

## BLOCO 7 — Perfis de Acesso (`/settings/roles`)

> **Nota:** Este módulo está em `/settings/roles`, não em `/platform/`. Rota: `settings > Perfis de Acesso`.

### TC-ROLES-01: Listar perfis de acesso
| Passo | Ação |
|-------|------|
| 1 | Navegar para `/settings/roles` |
| 2 | Aguardar tabela |

**Evidência de sucesso:** Tabela com perfis listados; perfil "Administrador" visível (system role).
**Evidência de falha:** Tabela vazia ou erro de acesso.

---

### TC-ROLES-02: Criar perfil de acesso com permissões
| Passo | Ação | Dado |
|-------|------|------|
| 1 | Clicar "Novo perfil de acesso" | — |
| 2 | Modal com título "Novo perfil de acesso" | — |
| 3 | Preencher Nome | `Perfil E2E Test` |
| 4 | Expandir módulo "Usuários" | clicar no módulo |
| 5 | Marcar permissão "Visualizar" | checkbox |
| 6 | Verificar contador do módulo | deve mostrar `1/N` |
| 7 | Salvar | — |

**Evidência de sucesso:** Modal fecha; `Perfil E2E Test` na tabela; toast `"Perfil salvo com sucesso."`.
**Evidência de falha:** Erro de validação OU toast de erro API.

---

### TC-ROLES-03: Validação — nome obrigatório
| Passo | Ação |
|-------|------|
| 1 | Abrir "Novo perfil de acesso" |
| 2 | Deixar campo Nome vazio |
| 3 | Salvar |

**Evidência de sucesso:** Erro `"Nome é obrigatório."` inline; modal não fecha.
**Evidência de falha:** Requisição enviada sem nome.

---

### TC-ROLES-04: Toggle módulo inteiro seleciona/deseleciona todas permissões
**Pré-condição:** Modal de criação/edição aberto com permissões carregadas.

| Passo | Ação |
|-------|------|
| 1 | Expandir módulo "Chat" |
| 2 | Clicar checkbox do módulo "Chat" (selecionar tudo) |
| 3 | Verificar todas as permissões do módulo marcadas |
| 4 | Verificar `isModuleFullySelected` = true |
| 5 | Clicar checkbox do módulo novamente (desselecionar tudo) |
| 6 | Verificar todas as permissões desmarcadas |

**Evidência de sucesso:** Toggle de módulo funciona como "selecionar todos" / "desselecionar todos".
**Evidência de falha:** Permissões individuais não mudam com toggle do módulo.

---

### TC-ROLES-05: Editar perfil de acesso
**Pré-condição:** TC-ROLES-02 passou.

| Passo | Ação | Dado |
|-------|------|------|
| 1 | Clicar ícone editar em `Perfil E2E Test` | — |
| 2 | Verificar Nome pré-preenchido | `Perfil E2E Test` |
| 3 | Verificar permissões carregadas | checkbox de "Visualizar Usuários" marcado |
| 4 | Adicionar permissão "Gerenciar" no módulo Usuários | — |
| 5 | Salvar | — |

**Evidência de sucesso:** Modal fecha; toast de sucesso; permissões salvas (verificável reabrindo edição).
**Evidência de falha:** Permissões não persistidas após salvar.

---

### TC-ROLES-06: Perfil "Administrador" não pode ser excluído (system role)
| Passo | Ação |
|-------|------|
| 1 | Localizar perfil "Administrador" na tabela |
| 2 | Verificar ausência do ícone de exclusão |

**Evidência de sucesso:** Ícone lixeira ausente para o perfil "Administrador".
**Evidência de falha:** Ícone lixeira visível e clicável.

---

### TC-ROLES-07: Excluir perfil de acesso
**Pré-condição:** TC-ROLES-02 passou; perfil não é system role.

| Passo | Ação |
|-------|------|
| 1 | Clicar ícone lixeira em `Perfil E2E Test` |
| 2 | Modal de confirmação: `"Tem certeza que deseja excluir o perfil \"Perfil E2E Test\"? Esta ação não pode ser desfeita."` |
| 3 | Confirmar |

**Evidência de sucesso:** Perfil removido da tabela; toast `"Perfil excluído com sucesso."`.
**Evidência de falha:** Perfil permanece OU toast de erro.

---

### TC-ROLES-08: Modal de usuários do perfil
**Pré-condição:** Existe perfil com usuários vinculados.

| Passo | Ação |
|-------|------|
| 1 | Clicar ícone de usuários em um perfil |
| 2 | Modal de usuários abre |
| 3 | Verificar lista de usuários vinculados |
| 4 | Clicar "Editar" em um usuário |
| 5 | Verificar redirecionamento para `/settings/users?edit=<id>` |

**Evidência de sucesso:** Modal abre com lista; redirecionamento correto ao editar usuário.
**Evidência de falha:** Modal vazio OU redirecionamento para URL errada.

---

## Resumo de Dependências

```
TC-PLANS-02 ──────────────────────────────► TC-TENANT-02
                                                   │
                                    ┌──────────────┤
                                    │              │
                               TC-USERS-02    TC-INVOICES-02
                                    │
                               TC-USERS-05...TC-USERS-09
```

---

## Protocolo de Falha

1. **Login falhou** → PARAR TUDO. Reportar credenciais/URL.
2. **TC-PLANS-02 falhou** → PARAR BLOCO 3. Executar restante com plano pré-existente se houver.
3. **TC-TENANT-02 falhou** → PARAR TC-TENANT-03..11 e TC-USERS-02. Restante segue com tenant pré-existente.
4. **Qualquer TC falhou** → Capturar:
   - Screenshot da tela atual
   - Log do console (erros JS)
   - URL atual
   - Request/Response da API (Network tab)
5. Reportar com: `[TC-ID] FALHOU — [motivo] — evidência: [screenshot path]`

---

## Checklist de Conclusão

- [ ] BLOCO 1 — Login: todos passaram
- [ ] BLOCO 2 — Planos: TC-PLANS-01 a TC-PLANS-08
- [ ] BLOCO 3 — Tenants: TC-TENANT-01 a TC-TENANT-11
- [ ] BLOCO 4 — Usuários: TC-USERS-01 a TC-USERS-09
- [ ] BLOCO 5 — Leads: TC-LEADS-01 a TC-LEADS-04
- [ ] BLOCO 6 — Faturas: TC-INVOICES-01 a TC-INVOICES-08
- [ ] BLOCO 7 — Perfis de Acesso: TC-ROLES-01 a TC-ROLES-08

**Total de casos:** 50 TCs

---

*Gerado em 2026-05-24 — atualizar ao encontrar novos comportamentos durante execução.*
