# TESTE_CONFIG.md - Plano de Testes Playwright de Configuracoes

## Resumo

Este arquivo e o mapa operacional para testar o modulo Configuracoes com `playwright-cli`.

Nao iniciar testes sem confirmacao explicita. Quando a execucao for autorizada, seguir a ordem deste documento e parar imediatamente na primeira falha critica.

Ambiente assumido:

- Frontend Angular: `http://localhost:4200`
- API local via proxy: `/api`
- Login seed: `admin@interazap.com.br` / `password`
- Permissoes: `settings.general.view`, `departments.department.view`, `settings.tags.manage`, `users.user.view`, `ai.autopilots.manage`, `platform.tenants.manage`

Escopo:

- Minhas Preferencias (`/settings/preferences`)
- Horario de Atendimento (`/settings/opening-hours`)
- Departamentos (`/settings/departments`)
- Etiquetas (`/settings/tags`)
- Usuarios (`/settings/users`)
- Meu Perfil (`/settings/profile`)
- Meu Plano (`/settings/my-plan`)
- 2FA (`/settings/two-factor`)
- Transcricao de Midia (`/settings/media-transcription`)
- Agendamento (`/settings/scheduling`)
- Bloqueio por inadimplencia (`/billing/lockout`)

## Protocolo De Execucao

1. Abrir login:

   ```bash
   playwright-cli open http://localhost:4200/auth/login
   ```

2. Fazer login e salvar estado:

   ```bash
   playwright-cli state-save .playwright-config-auth.json
   ```

3. Antes de cada tela:

   ```bash
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

4. Em falha:

   ```bash
   playwright-cli screenshot --filename=config-failure-<ID>.png
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

## Criterios Globais

### Sucesso

- Tela abre sem redirect indevido.
- Loading termina.
- Estados vazios e erros sao controlados.
- Forms bloqueiam submit invalido.
- Criacao/edicao/exclusao persistem apos reload.
- Preferencias salvas refletem na UI.
- Acoes destrutivas pedem confirmacao.

### Falha

- Tela branca, crash Angular ou console error inesperado.
- Request de configuracao retorna 500.
- Botao salvar dispara com form invalido.
- Modal trava aberto depois de salvar.
- Item criado nao aparece apos reload.
- Alteracao sensivel sem confirmacao.

## CONFIG-00 - Login E Menu Configuracoes

### Passos

1. Fazer login.
2. Abrir grupo `Configuracoes` no sidenav.
3. Validar links:
   - Minhas Preferencias
   - Horario de Atendimento
   - Departamentos
   - Etiquetas
   - Usuarios
4. Abrir `/settings/profile`, `/settings/my-plan`, `/settings/two-factor`, `/settings/media-transcription` e `/settings/scheduling` diretamente.
5. Abrir `/settings/tenant` e validar redirect para `/settings/preferences`.

### Passou Se

- Links visiveis respeitam permissoes.
- Rotas diretas abrem para usuario admin.
- `/settings/tenant` redireciona para preferencias sem erro.

## CONFIG-01 - Minhas Preferencias

**Rota:** `/settings/preferences`
**Endpoints esperados:** `/api/auth/profile/preferences`, `/api/platform/tenants/:id/settings`

### Passos

1. Abrir `/settings/preferences`.
2. Aguardar carregar.
3. Validar secoes:
   - Notificacoes
   - Aparencia
   - Comportamento
   - CRM - Valores padrao
   - Acessibilidade
   - Seguranca
   - Localizacao
   - Privacidade
   - Chat / fechamento automatico
4. Alternar `Receber notificacoes no aplicativo`.
5. Alterar tema entre claro/escuro/sistema.
6. Alterar densidade da interface.
7. Alterar tamanho da fonte.
8. Alternar `Som de notificacoes`.
9. Alternar `Notificar ao receber mensagem no chat`.
10. Alterar modo de abertura de ticket.
11. Alterar valores padrao de CRM se houver opcoes.
12. Alterar idioma, formato de data/hora/moeda se disponivel.
13. Ativar fechamento automatico de chat.
14. Selecionar tempo, alvo e mensagem.
15. Clicar `Salvar preferencias`.
16. Recarregar a pagina.

### Passou Se

- Mudancas persistem apos reload.
- Fechamento automatico mostra campos condicionais quando ativo.
- Validacao impede salvar fechamento automatico sem campos obrigatorios.
- Requests retornam 2xx.

## CONFIG-02 - Horario De Atendimento

**Rota:** `/settings/opening-hours`
**Endpoint esperado:** `/api/opening-hours`

### Passos

1. Abrir `/settings/opening-hours`.
2. Validar titulo `Horarios`.
3. Buscar por dia existente e limpar busca.
4. Clicar `Novo Horario`.
5. Selecionar dia da semana.
6. Preencher abertura `08:00`.
7. Preencher fechamento `18:00`.
8. Manter `Ativo`.
9. Salvar.
10. Editar horario criado para `09:00` - `17:30`.
11. Selecionar horario criado.
12. Validar acao `Excluir selecionados`.
13. Cancelar exclusao.
14. Excluir horario criado.

### Passou Se

- Tabela mostra Dia da Semana, Abertura, Fechamento, Status, Acoes.
- CRUD persiste.
- Horario invalido ou duplicado mostra erro controlado.

## CONFIG-03 - Departamentos

**Rota:** `/settings/departments`
**Endpoint esperado:** `/api/crm/departments`

### Passos

1. Abrir `/settings/departments`.
2. Validar tabela com Nome, Descricao, Status, Acoes.
3. Buscar por termo existente e limpar.
4. Abrir filtros.
5. Filtrar por status Ativo/Inativo/Todos.
6. Criar departamento:
   - Nome: `E2E Departamento <timestamp>`
   - Descricao: `Departamento criado por teste E2E`
   - Ativo: ligado
7. Salvar.
8. Editar descricao.
9. Alternar ativo/inativo se a UI permitir.
10. Selecionar linha e validar exclusao em massa.
11. Cancelar exclusao em massa.
12. Excluir departamento criado.

### Passou Se

- Modal abre e fecha corretamente.
- Form valida nome obrigatorio.
- Filtros alteram listagem.
- Exclusao exige confirmacao.

## CONFIG-04 - Etiquetas

**Rota:** `/settings/tags`
**Endpoint esperado:** `/api/crm/tags`

### Passos

1. Abrir `/settings/tags`.
2. Validar tabela com Cor, Nome, Categoria, Status, Acoes.
3. Ordenar por Nome.
4. Buscar por termo existente e limpar.
5. Abrir filtros e filtrar por status.
6. Criar etiqueta:
   - Nome: `E2E Etiqueta <timestamp>`
   - Cor: `#2563eb`
   - Categoria: `E2E`
   - Ativo: ligado
7. Salvar.
8. Editar cor para outra cor.
9. Editar categoria.
10. Selecionar etiqueta criada.
11. Validar acao `Excluir selecionadas`.
12. Cancelar exclusao.
13. Excluir etiqueta criada.

### Passou Se

- Swatch de cor aparece na tabela.
- Nome obrigatorio e cor valida sao exigidos.
- Item removido some apos reload.

## CONFIG-05 - Usuarios

**Rota:** `/settings/users`
**Endpoint esperado:** `/api/auth/users`

### Passos

1. Abrir `/settings/users`.
2. Validar tabela com Usuario, Perfil, Status, Acoes.
3. Buscar usuario existente.
4. Abrir filtros e filtrar por status.
5. Clicar `Novo Usuario`.
6. Preencher:
   - Nome: `E2E Usuario <timestamp>`
   - E-mail: `e2e.usuario.<timestamp>@interazap.test`
   - Perfil de acesso: primeiro perfil disponivel
   - Senha: `Password@12345`
   - Confirmar senha: `Password@12345`
   - Usuario ativo: ligado
7. Salvar.
8. Editar usuario criado.
9. Validar que e-mail fica readonly em edicao.
10. Alterar nome.
11. Deixar senha em branco e salvar.
12. Alternar ativo/inativo se acao existir.
13. Tentar salvar senha e confirmacao divergentes.
14. Validar erro.
15. Excluir usuario criado.

### Passou Se

- Criacao exige nome, email, perfil, senha e confirmacao.
- Senha mostra checklist/forca.
- Edicao nao obriga nova senha.
- Exclusao pede confirmacao.

## CONFIG-06 - Meu Perfil

**Rota:** `/settings/profile`
**Endpoints esperados:** perfil autenticado e atualizacao de senha/avatar

### Passos

1. Abrir `/settings/profile`.
2. Validar secoes:
   - Foto de perfil
   - Informacoes pessoais
   - Alterar senha
   - Autenticacao em dois fatores
3. Fazer upload de avatar PNG/JPEG pequeno.
4. Alterar nome para `Admin E2E <timestamp>`.
5. Salvar alteracoes.
6. Recarregar pagina e validar nome.
7. Testar troca de senha com confirmacao divergente sem salvar senha real.
8. Validar erro `As senhas nao coincidem`.
9. Clicar link `Configurar` ou `Gerenciar` 2FA.

### Passou Se

- Avatar aceita formatos permitidos e rejeita arquivo invalido/grande.
- E-mail e readonly.
- Nome persiste.
- Senha divergente nao envia request de troca.

## CONFIG-07 - 2FA

**Rota:** `/settings/two-factor`
**Endpoints esperados:** status/setup/verify/disable/regenerate de 2FA

### Passos

1. Abrir `/settings/two-factor`.
2. Validar status `Ativo` ou `Inativo`.
3. Se inativo, clicar `Ativar autenticacao em dois fatores`.
4. Validar QR Code ou chave secreta.
5. Copiar chave secreta.
6. Clicar `Continuar`.
7. Digitar codigo invalido `000000`.
8. Clicar validar.
9. Validar mensagem de erro.
10. Voltar para setup e cancelar.
11. Se ativo, abrir `Regenerar codigos de recuperacao`.
12. Cancelar modal.
13. Abrir `Desativar 2FA`.
14. Cancelar modal.

### Passou Se

- Fluxo inativo mostra setup com QR/chave.
- Codigo invalido nao ativa 2FA.
- Fluxo ativo pede senha para regenerar/desativar.
- Acoes sensiveis podem ser canceladas.

## CONFIG-08 - Transcricao De Midia

**Rota:** `/settings/media-transcription`
**Endpoint esperado:** `/api/media-transcription`

### Passos

1. Abrir `/settings/media-transcription`.
2. Validar callout de estimativa de custos.
3. Alternar `Transcricao de Audio`.
4. Quando ativo, preencher duracao maxima `5`.
5. Testar valores invalidos `0` e `26`.
6. Alternar `Descricao de Imagens`.
7. Quando ativo, preencher maximo de imagens `3`.
8. Testar valor invalido `11`.
9. Alternar `Transcricao de Videos`.
10. Quando ativo, preencher duracao maxima `30`.
11. Testar valores invalidos `4` e `121`.
12. Restaurar valores validos.
13. Clicar `Salvar Configuracoes`.
14. Recarregar pagina.

### Passou Se

- Campos condicionais aparecem somente quando toggle ativo.
- Audio aceita 1 a 25 minutos.
- Imagem aceita 1 a 10 imagens.
- Video aceita 5 a 120 segundos.
- Configuracoes persistem.

## CONFIG-09 - Agendamento

**Rota:** `/settings/scheduling`
**Endpoint esperado:** `/api/configuration/scheduling`

### Passos

1. Abrir `/settings/scheduling`.
2. Validar titulo `Agendamento`.
3. Alternar `Ativar confirmacao de agendamento`.
4. Quando ativo, validar campos:
   - Antecedencia do lembrete
   - Notificar via interface (UI)
   - Notificar via push
5. Alterar antecedencia.
6. Alternar notificacoes.
7. Clicar `Descartar alteracoes`.
8. Confirmar que valores voltaram ao estado anterior.
9. Alterar novamente.
10. Clicar `Salvar configuracoes`.
11. Recarregar pagina.

### Passou Se

- Campos condicionais aparecem quando confirmacao ativa.
- Descartar desfaz alteracoes locais.
- Salvar persiste apos reload.

## CONFIG-10 - Meu Plano

**Rota:** `/settings/my-plan`
**Endpoints esperados:** assinatura atual, planos disponiveis, preferencias de cobranca e preview de troca de plano

### Passos

1. Abrir `/settings/my-plan`.
2. Validar titulo `Meu Plano`.
3. Aguardar loading terminar.
4. Validar bloco `Plano atual`.
5. Validar campos:
   - Nome do plano
   - Preco mensal
   - Proxima fatura
   - Valor previsto
   - Credito disponivel
6. Validar cards/indicadores de consumo quando houver uso.
7. Validar secao `Planos disponiveis`.
8. Clicar `Atualizar`.
9. Se o plano atual tiver IA habilitada, abrir `Preferencias de cobranca`.
10. Alterar preferencia permitida no modal.
11. Cancelar e reabrir para validar que cancelamento nao persistiu.
12. Abrir novamente, salvar uma preferencia segura de teste e recarregar pagina.
13. Selecionar um plano de preco maior, se houver.
14. Validar modal de upgrade com preview de cobranca.
15. Cancelar upgrade.
16. Selecionar um plano de preco menor, se houver.
17. Validar modal de downgrade com impactos/confirmacao.
18. Cancelar downgrade.

### Passou Se

- Plano atual e consumo carregam sem erro.
- Estado vazio `Nenhum plano ativo` aparece quando aplicavel.
- Preferencias de cobranca abrem apenas quando permitidas.
- Preview de upgrade/downgrade aparece antes de confirmar.
- Nenhuma troca de plano e confirmada sem acao explicita.

### Falha E Correcao

- Se plano nao carrega, verificar service de assinatura/billing.
- Se modal confirma troca sem preview, revisar componentes de upgrade/downgrade.
- Se preferencia de cobranca nao persiste, verificar `billing-prefs.service.ts`.

## CONFIG-11 - Billing Lockout / Acesso Bloqueado

**Rota:** `/billing/lockout`

### Objetivo

Validar a tela exibida quando o tenant esta bloqueado por inadimplencia e os caminhos de recuperacao.

### Pre-condicoes

- Sessao com lockout ativo para validar dados reais; quando nao houver, validar estado vazio controlado.
- Pelo menos uma fatura vencida quando o lockout estiver ativo.

### Passos - Com Lockout Ativo

1. Abrir `/billing/lockout`.
2. Validar titulo `Acesso bloqueado por inadimplencia`.
3. Validar mensagem de bloqueio.
4. Validar card da primeira fatura:
   - Referencia
   - Valor
   - Vencimento
5. Validar `Prazo para exclusao de dados`.
6. Clicar `Ver Meu Plano`.
7. Validar navegacao para `/settings/my-plan`.
8. Voltar para `/billing/lockout`.
9. Clicar `Pagar Agora`.
10. Validar redirecionamento/link de pagamento ou feedback esperado.
11. Voltar para `/billing/lockout`.
12. Clicar `Sair`.
13. Validar logout e redirect para login.

### Passos - Sem Lockout Ativo

1. Abrir `/billing/lockout` com usuario sem bloqueio.
2. Validar estado `Nenhum bloqueio ativo`.
3. Clicar `Ir para Meu Plano`.
4. Validar navegacao para `/settings/my-plan`.

### Passou Se

- Tela mostra dados de bloqueio quando a sessao esta bloqueada.
- Estado sem bloqueio e amigavel.
- Botoes principais levam para pagamento, meu plano e logout.
- Nao ha acesso indevido ao app quando lockout esta ativo.

### Falha E Correcao

- Se lockout ativo nao mostra fatura, verificar dados retornados pelo interceptor/status de billing.
- Se `Pagar Agora` nao leva a pagamento, verificar `lockout.ts`.
- Se usuario bloqueado consegue acessar rotas protegidas indevidamente, verificar `billing-lockout.interceptor.ts`.

## CONFIG-12 - Permissoes

### Passos

1. Entrar com usuario sem permissao de usuarios, se existir.
2. Tentar abrir `/settings/users`.
3. Validar bloqueio por permissao.
4. Entrar como admin novamente.
5. Validar acesso liberado.

### Passou Se

- Guards bloqueiam apenas usuarios sem permissao.
- Admin consegue acessar todo escopo.

## Checklist Final Do Modulo

- [ ] Menu Configuracoes validado.
- [ ] Preferencias salvas e persistidas.
- [ ] Horario de atendimento CRUD validado.
- [ ] Departamentos CRUD validado.
- [ ] Etiquetas CRUD validado.
- [ ] Usuarios CRUD validado.
- [ ] Perfil validado.
- [ ] Meu Plano validado.
- [ ] 2FA validado sem ativacao acidental obrigatoria.
- [ ] Transcricao de midia validada.
- [ ] Agendamento validado.
- [ ] Billing lockout validado.
- [ ] Permissoes validadas.
