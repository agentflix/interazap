# TESTE_CHAT.md - Plano de Testes Playwright do Chat

## Resumo

Este arquivo e o mapa operacional para testar o modulo Chat com `playwright-cli`.

Nao iniciar testes sem confirmacao explicita. Quando a execucao for autorizada, seguir a ordem deste documento e parar imediatamente na primeira falha critica.

Ambiente assumido:

- Frontend Angular: `http://localhost:4200`
- API local via proxy: `/api`
- Login seed: `admin@interazap.com.br` / `password`
- Tenant de teste com pelo menos 1 canal WhatsApp/integracao ativo
- Permissoes: `chat.called.view`, `chat.transmission_lists.view`, `chat.channel.view`, `chat.routing.manage`, `chat.templates.manage`
- Arquivo de teste para anexo: `storage/e2e/chat-anexo.pdf` ou qualquer PDF/PNG pequeno local

Escopo:

- Conversas (`/chat`)
- Tickets (`/chat/ticket`, `/chat/ticket/:ticketId`)
- Chat Externo (`/chat/external`, rota publica `/chat/external/:tenantId` e embed `/embed/:tenantId`)
- Avaliacao publica de atendimento (`/chat/evaluations/:token`)
- Resposta Automatica (`/chat/auto-reply`)
- Listas de Transmissao (`/chat/transmission-list`)
- Respostas Rapidas (`/chat/quick-answers`)
- Canais (`/chat/channel`)
- Configuracao de roteamento (`/chat/configuration`)
- Templates Meta (`/chat/template-meta`)

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
   - Layout autenticado deve aparecer
   - Menu lateral deve mostrar o grupo Chat

4. Salvar estado autenticado:

   ```bash
   playwright-cli state-save .playwright-chat-auth.json
   ```

5. Antes de cada tela:

   ```bash
   playwright-cli snapshot
   playwright-cli console
   playwright-cli requests
   ```

6. Em qualquer falha:

   - Parar se a falha impedir continuar o fluxo
   - Coletar screenshot, console, requests e URL atual
   - Registrar o passo exato e o comportamento obtido

## Criterios Globais

### Sucesso

- A rota abre sem redirect indevido.
- Nao ha tela branca, crash Angular ou erro de console inesperado.
- Requests esperados retornam 2xx.
- CRUDs persistem depois de reload.
- Chat realtime exibe mensagens sem duplicar, perder ordem ou travar composer.
- Estados de pending, open/in_progress e closed mudam visualmente conforme acao.
- Upload de midia gera preview/bolha e nao quebra o historico.

### Falha

- URL redireciona para `/access-denied` quando o usuario deveria ter permissao.
- Mensagem enviada nao aparece localmente nem apos reload.
- Mensagem aparece duplicada apos evento realtime.
- Ticket nao e criado no painel interno apos chat externo iniciar sessao.
- Anexo nao envia, nao aparece, ou quebra a listagem.
- Modal abre sem campos obrigatorios, salva invalido, ou trava carregando.

## Protocolo De Falha E Correcao

Comandos de evidencia:

```bash
playwright-cli screenshot --filename=chat-failure-<ID>.png
playwright-cli snapshot
playwright-cli console
playwright-cli requests
```

Modelo:

```md
## Falha <ID>

- Teste:
- URL:
- Passo:
- Esperado:
- Obtido:
- Requests:
- Console:
- Causa provavel:
- Arquivos provaveis:
- Como confirmar depois:
```

## CHAT-00 - Login E Menu Chat

### Passos

1. Abrir `/auth/login`.
2. Fazer login com o seed.
3. Abrir grupo Chat no sidenav.
4. Validar links:
   - Conversas
   - Tickets
   - Chat Externo
   - Resposta Automatica
   - Listas de Transmissao
   - Respostas Rapidas
   - Canais
   - Configuracao
   - Templates Meta

### Passou Se

- Login funciona.
- Menu Chat aparece com os links esperados.
- Nenhum link do escopo leva para rota inexistente.

### Evidencia De Sucesso

- Snapshot do menu Chat aberto.
- Requests de `/api/auth/me` e `/api/auth/get-menu` com 2xx.

## CHAT-01 - Conversas: Layout, Lista E Selecao

### Passos

1. Abrir `/chat`.
2. Validar painel esquerdo de tickets/conversas.
3. Validar estado vazio quando nao houver ticket selecionado.
4. Usar busca da lista para procurar contato conhecido.
5. Selecionar um ticket.
6. Validar header da conversa com nome/telefone/protocolo/status.
7. Clicar no protocolo quando existir.
8. Alternar abas laterais: Chat, Contato e Negociacao/CRM.
9. Recarregar a pagina mantendo a rota do ticket selecionado, se aplicavel.

### Passou Se

- A lista carrega sem spinner infinito.
- Ticket selecionado abre o painel central.
- Header mostra nome ou telefone, status e protocolo quando disponivel.
- Aba Contato mostra campos editaveis do contato.
- Aba Negociacao mostra a secao CRM ou estado vazio sem quebrar.

### Falha E Correcao

- Se lista nao carregar, verificar `CalledService`, `ChatListStateService` e requests de chamados.
- Se ticket selecionado nao abrir, verificar `app/src/app/pages/chat/chat.ts` e componentes de shell/lista/conversa.

## CHAT-02 - Criar Conversa Interna Pelo Modal

### Passos

1. Abrir `/chat`.
2. Clicar em "Nova conversa" ou acao equivalente no estado vazio/lista.
3. Validar modal de nova conversa.
4. Selecionar canal/instancia ativa.
5. Preencher telefone unico: `1199<timestamp>`.
6. Preencher nome: `E2E Chat Interno <timestamp>`.
7. Preencher mensagem inicial: `Ola, iniciando atendimento E2E interno.`
8. Clicar em `Enviar e abrir`.
9. Aguardar redirecionamento/abertura da conversa criada.

### Passou Se

- Modal fecha.
- Novo ticket aparece selecionado.
- Mensagem inicial aparece no historico.
- Request de criacao/envio retorna 2xx.

### Falha E Correcao

- Se janela de 24h bloquear envio, validar modal/servico `WindowVerificationService`.
- Se contato duplicado impedir criacao, trocar telefone e repetir.

## CHAT-03 - Conversa Interna: Mensagem, Anexo, Audio E Fechamento

### Passos

1. Abrir um ticket em andamento.
2. Digitar `Mensagem texto E2E interna 01` no textarea `Digite uma mensagem...`.
3. Enviar pelo botao `Enviar mensagem`.
4. Confirmar bolha enviada e status visual.
5. Clicar em `Anexar arquivo`.
6. Selecionar `Documento` ou `Arquivo`.
7. Fazer upload de um PDF/PNG pequeno.
8. Confirmar preview/bolha de anexo.
9. Se audio estiver habilitado, iniciar gravacao, cancelar, iniciar novamente e enviar.
10. Clicar em `Transferir atendimento`.
11. Cancelar transferencia.
12. Clicar em `Fechar atendimento`.
13. Cancelar confirmacao.
14. Reabrir confirmacao e fechar somente se o ticket for de teste.

### Passou Se

- Texto aparece uma vez.
- Anexo aparece com tipo/nome/preview coerente.
- Cancelar transferencia e fechamento nao altera status.
- Fechamento muda status para `Encerrado` e bloqueia composer.

### Falha E Correcao

- Se anexo falhar, verificar `called-message.service.ts`, `chat-media-loader.service.ts` e permissao/tamanho de arquivo.
- Se mensagem duplica, verificar adaptadores realtime e optimistic update.

## CHAT-04 - Chat Externo: Codigo De Integracao E Preview

### Passos

1. Abrir `/chat/external`.
2. Validar titulo `Chat Externo` e subtitulo `Widget para sites externos`.
3. Validar bloco `URL Publica do Widget`.
4. Copiar URL publica.
5. Copiar URL de embed.
6. Alterar seletor `Tipo de embed` para cada opcao disponivel.
7. Validar que o codigo muda no bloco de codigo.
8. Copiar codigo de integracao.
9. Validar preview em iframe de 500px.
10. Abrir a URL publica copiada em nova aba/contexto anonimo.

### Passou Se

- URLs contem tenant atual.
- Botao copiar mostra feedback.
- Codigo de widget/iframe/full page nao fica vazio.
- Preview nao quebra a pagina mesmo se localhost impedir renderizacao.

### Falha E Correcao

- Se tenant vier vazio, verificar `external-chat-page.ts` e AuthStore/tenant atual.
- Se iframe quebrar, validar sanitizacao `safeEmbedUrl`.

## CHAT-05 - Cenario Completo: Chat Externo Com 30 Mensagens E Arquivo

### Objetivo

Simular duas pessoas conversando:

- Pessoa A: visitante no chat externo publico.
- Pessoa B: atendente logado no painel interno `/chat`.

Este e o principal cenario de validacao do sistema completo de chat.

### Preparacao

1. Aba A autenticada: abrir `/chat/external` e copiar a URL publica do widget.
2. Aba B autenticada: abrir `/chat` e deixar a lista de conversas visivel.
3. Aba C anonima/incognito: abrir URL publica `/chat/external/:tenantId`.
4. No pre-chat publico, preencher:
   - Nome: `Visitante E2E 30 Mensagens <timestamp>`
   - WhatsApp: `(11) 98888-<ultimos 4 timestamp>`
5. Iniciar atendimento.
6. Na aba B, localizar o novo ticket pelo nome/telefone.
7. Abrir o ticket.

### Script De Mensagens

Enviar exatamente na ordem abaixo. Apos cada envio, validar que a outra ponta recebeu antes de continuar.

| # | Origem | Mensagem / Acao |
|---|--------|------------------|
| 1 | Visitante | Ola, estou testando o chat externo. |
| 2 | Atendente | Ola! Recebi sua mensagem no painel interno. |
| 3 | Visitante | Quero saber se o atendimento esta em tempo real. |
| 4 | Atendente | Sim, vou responder cada mensagem para validar. |
| 5 | Visitante | Mensagem 05 do visitante. |
| 6 | Atendente | Mensagem 06 do atendente. |
| 7 | Visitante | Mensagem 07 com acento e pontuacao? Tudo certo! |
| 8 | Atendente | Mensagem 08 confirmando caracteres especiais. |
| 9 | Visitante | Mensagem 09 com numero de pedido 12345. |
| 10 | Atendente | Mensagem 10 validando protocolo do ticket. |
| 11 | Visitante | Mensagem 11 antes do envio de arquivo. |
| 12 | Visitante | Enviar arquivo `chat-anexo.pdf` ou imagem pequena pelo composer publico. |
| 13 | Atendente | Recebi o arquivo. Consigo abrir/visualizar o anexo. |
| 14 | Visitante | Mensagem 14 depois do anexo. |
| 15 | Atendente | Mensagem 15 no meio do teste. |
| 16 | Visitante | Mensagem 16 para testar scroll. |
| 17 | Atendente | Mensagem 17 para testar scroll no painel interno. |
| 18 | Visitante | Mensagem 18, ainda conectado. |
| 19 | Atendente | Mensagem 19, conexao segue ativa. |
| 20 | Visitante | Mensagem 20, metade final do teste. |
| 21 | Atendente | Mensagem 21, validando ordem cronologica. |
| 22 | Visitante | Mensagem 22, sem duplicidade ate aqui. |
| 23 | Atendente | Mensagem 23, confirmando sem duplicidade. |
| 24 | Visitante | Mensagem 24, vou enviar varias mensagens curtas. |
| 25 | Visitante | Mensagem 25 curta. |
| 26 | Visitante | Mensagem 26 curta. |
| 27 | Atendente | Mensagem 27 agrupando resposta do operador. |
| 28 | Visitante | Mensagem 28 validando contador/rolagem. |
| 29 | Atendente | Mensagem 29 quase finalizando. |
| 30 | Visitante | Mensagem 30 final do visitante. |
| 31 | Atendente | Mensagem 31 final do atendente para fechar o ciclo. |

### Validacoes Durante O Script

1. A mensagem enviada aparece imediatamente na propria ponta.
2. A mensagem recebida aparece na outra ponta sem reload.
3. A ordem visual permanece 1 -> 31.
4. Nenhuma mensagem duplica.
5. O anexo da mensagem 12 aparece no chat externo e no painel interno.
6. O scroll acompanha novas mensagens ou exibe botao de rolar para novas mensagens.
7. Status/conexao nao fica permanentemente em erro.
8. O ticket aparece em `/chat/ticket` apos a conversa.
9. Recarregar as duas abas mantem historico completo.

### Encerramento

1. Na aba B, clicar em `Fechar atendimento`.
2. Confirmar fechamento.
3. Na aba C, validar que composer publico fica bloqueado ou mostra estado de atendimento encerrado.
4. Tentar enviar nova mensagem pelo visitante.

### Passou Se

- Pelo menos 31 mensagens foram trocadas.
- O arquivo foi enviado e visivel.
- Historico persistiu apos reload.
- Ticket mudou para `Encerrado` depois do fechamento.
- Visitante nao consegue continuar enviando apos encerramento.

### Falha E Correcao

- Se visitante nao cria ticket: verificar `/api/webchat/sessions` e `/api/webchat/tenant/:tenantId`.
- Se mensagem publica nao chega ao painel: verificar `/api/webchat/messages`, `ChatRealtimeService` e eventos realtime.
- Se mensagem interna nao chega ao publico: verificar fan-out do gateway e cache/historico em `/api/webchat/sessions/:id/messages`.
- Se arquivo falha: verificar `/api/webchat/media`, limite/tipo MIME e composer publico.

## CHAT-06 - Tickets: Lista, Filtros E Detalhe

### Passos

1. Abrir `/chat/ticket`.
2. Validar titulo/gestao de atendimentos.
3. Buscar pelo contato criado no CHAT-05.
4. Abrir detalhe `/chat/ticket/:ticketId`.
5. Validar dados do atendimento: contato, protocolo, status, historico.
6. Voltar para lista.
7. Testar filtro/status quando disponivel.
8. Validar paginacao.

### Passou Se

- Ticket do chat externo aparece.
- Detalhe abre sem erro.
- Status fechado aparece apos encerramento.

## CHAT-07 - Resposta Automatica

### Passos

1. Abrir `/chat/auto-reply`.
2. Validar tabela com regras ou estado vazio.
3. Clicar para criar nova regra.
4. Selecionar tipo de mensagem texto.
5. Preencher palavra-chave: `e2e-auto-<timestamp>`.
6. Preencher legenda/mensagem.
7. Marcar `Ativo`.
8. Salvar.
9. Editar a regra criada.
10. Alternar `Mensagem de boas-vindas`.
11. Alterar acao para `transferir`, se houver departamento disponivel.
12. Salvar.
13. Excluir a regra criada.

### Passou Se

- CRUD completo funciona.
- Regra aparece na tabela com badges de boas-vindas e ativo/inativo.
- Validacao impede salvar sem campos obrigatorios.

## CHAT-08 - Respostas Rapidas

### Passos

1. Abrir `/chat/quick-answers`.
2. Buscar por resposta existente e limpar.
3. Criar resposta `E2E QA <timestamp>`.
4. Preencher atalho `/e2e<timestamp>`.
5. Preencher conteudo da resposta.
6. Salvar.
7. Editar conteudo.
8. Validar que aparece na lista.
9. Excluir resposta criada.

### Passou Se

- Lista, busca, criacao, edicao e exclusao funcionam.
- Atalho nao duplica outro existente.

## CHAT-09 - Canais

### Passos

1. Abrir `/chat/channel`.
2. Validar listagem de canais/instancias.
3. Criar canal de teste se ambiente permitir.
4. Preencher nome, pais/DDI e dados obrigatorios.
5. Salvar.
6. Validar status visual da conexao.
7. Testar acao conectar/desconectar quando disponivel.
8. Editar nome do canal.
9. Excluir somente canal criado pelo teste.

### Passou Se

- Canal aparece na lista.
- Acoes respeitam loading e nao duplicam registros.
- Estado de conexao e roteamento e compreensivel.

## CHAT-10 - Configuracao De Roteamento

### Passos

1. Abrir `/chat/configuration`.
2. Validar tela de configuracao do chat.
3. Validar lista de agentes/regras de roteamento.
4. Criar agente/regra de roteamento quando houver formulario.
5. Preencher campos obrigatorios.
6. Salvar.
7. Editar item criado.
8. Excluir item criado.

### Passou Se

- Regras aparecem e persistem.
- Campos obrigatorios bloqueiam submit invalido.
- Alteracoes refletem na listagem depois de reload.

## CHAT-11 - Listas De Transmissao

### Passos

1. Abrir `/chat/transmission-list`.
2. Validar titulo `Listas de Transmissao`.
3. Clicar `Nova Lista`.
4. Selecionar `Conexao WhatsApp`.
5. Preencher `Nome da Lista`: `E2E Lista <timestamp>`.
6. Selecionar filtro de status.
7. Preencher tags por IDs somente se houver tags conhecidas.
8. Validar `Contatos estimados`.
9. Preencher mensagem: `Ola {{name}}, teste E2E de transmissao.`
10. Inserir variaveis pelos botoes `name`, `phone`, `email`.
11. Validar preview da mensagem.
12. Agendar envio para uma data futura ou deixar vazio conforme regra.
13. Salvar.
14. Editar lista criada.
15. Cancelar exclusao.
16. Excluir lista criada.

### Passou Se

- Preview substitui/mostra variaveis.
- Lista salva e aparece na listagem.
- Excluir remove a lista.

## CHAT-12 - Templates Meta

### Passos

1. Abrir `/chat/template-meta`.
2. Validar tabela com colunas Nome, Canal, Idioma, Categoria, Status, Atualizado em, Acoes.
3. Abrir filtros.
4. Filtrar por canal e status.
5. Limpar filtros.
6. Clicar `Sincronizar com Meta` somente se houver canal selecionado e ambiente permitir.
7. Clicar `Novo Template Meta`.
8. Selecionar canal WhatsApp.
9. Preencher nome `e2e_template_<timestamp>` em minusculo com underline.
10. Selecionar idioma e categoria.
11. Preencher corpo `Ola {{1}}, seu atendimento foi confirmado.`
12. Preencher cabecalho e rodape opcionais.
13. Preencher atalho `/e2e-template`.
14. Validar pre-visualizacao.
15. Salvar/enviar para aprovacao Meta se ambiente permitir.
16. Editar template criado quando permitido pelo status.
17. Excluir somente template criado pelo teste.

### Passou Se

- Formulario valida nome invalido.
- Preview atualiza em tempo real.
- Status Meta bloqueia conteudo quando template aprovado/em aprovacao.

## CHAT-13 - Estados Offline E Reconnect

### Passos

1. Abrir `/chat` com ticket selecionado.
2. Desconectar rede via ferramenta do navegador ou derrubar gateway realtime.
3. Enviar uma mensagem de teste curta.
4. Validar banner/contador de pendencias.
5. Restaurar conexao.
6. Aguardar flush.
7. Recarregar pagina.

### Passou Se

- UI mostra pendencia em vez de perder mensagem.
- Mensagem e enviada apos reconexao.
- Historico final nao duplica mensagem.

## CHAT-14 - Chat Embed Publico

**Rota:** `/embed/:tenantId`
**Endpoint esperado:** `/api/webchat/tenant/:tenantId`, `/api/webchat/sessions`, `/api/webchat/messages`

### Objetivo

Validar a versao embutida do webchat, usada pelo codigo de iframe gerado em `/chat/external`.

### Passos

1. Abrir `/chat/external`.
2. Copiar a URL de embed ou extrair o `tenantId`.
3. Abrir `/embed/:tenantId` em contexto anonimo.
4. Validar estado de loading `Restaurando sessao...` quando houver sessao anterior.
5. Validar formulario pre-chat quando nao houver sessao.
6. Preencher:
   - Nome: `Visitante Embed E2E <timestamp>`
   - WhatsApp: `(11) 97777-<ultimos 4 timestamp>`
7. Iniciar atendimento.
8. Enviar mensagem: `Mensagem enviada pelo embed E2E.`
9. Abrir `/chat` em aba autenticada.
10. Localizar ticket do visitante embed.
11. Responder pelo painel interno: `Resposta interna para embed E2E.`
12. Validar recebimento no embed sem reload.
13. Recarregar `/embed/:tenantId`.
14. Validar restauracao da sessao e historico.
15. Encerrar atendimento pelo painel interno.
16. Validar estado encerrado no embed.

### Passou Se

- Embed abre sem layout quebrado e sem depender do layout autenticado.
- Pre-chat cria sessao corretamente.
- Mensagem visitante -> atendente e atendente -> visitante funcionam.
- Historico restaura apos reload.
- Encerramento bloqueia composer do embed.

### Falha E Correcao

- Se embed nao abre, verificar `webchat-embed.component.ts`.
- Se sessao nao restaura, verificar `WebchatService` e armazenamento `webchat_session`.
- Se mensagens nao chegam, verificar endpoints `/api/webchat/messages` e realtime.

## CHAT-15 - Avaliacao Publica De Atendimento

**Rota:** `/chat/evaluations/:token`
**Endpoint esperado:** `/api/public/chat/evaluations/:token`

### Objetivo

Validar o fluxo publico de avaliacao CSAT/NPS apos encerramento de atendimento.

### Pre-condicoes

- Ter um token valido de avaliacao gerado por atendimento encerrado.
- Ter um token invalido/expirado para validar estado de erro.
- Ter, se possivel, um token ja respondido para validar estado `Avaliacao ja enviada`.

### Passos - Token Valido

1. Abrir `/chat/evaluations/<token-valido>`.
2. Aguardar loading finalizar.
3. Validar titulo `Como foi seu atendimento?`.
4. Clicar em `Enviar avaliacao` sem selecionar nota.
5. Validar erro de nota obrigatoria.
6. Selecionar 1 estrela.
7. Alterar para 5 estrelas.
8. Preencher comentario: `Atendimento validado por teste E2E.`
9. Clicar `Enviar avaliacao`.
10. Validar tela de sucesso `Obrigado pelo seu feedback!`.
11. Reabrir o mesmo link.
12. Validar estado `Avaliacao ja enviada`.

### Passos - Token Invalido Ou Expirado

1. Abrir `/chat/evaluations/token-invalido-e2e`.
2. Validar estado `Avaliacao nao encontrada`.
3. Confirmar que nao ha form de envio visivel.

### Passou Se

- Loading, not found, already submitted, form e submitted renderizam corretamente.
- Nota obrigatoria e validada.
- Comentario opcional aceita texto ate o limite.
- Segundo envio do mesmo token e bloqueado.

### Falha E Correcao

- Se token valido aparece como invalido, verificar geracao do link pelo backend.
- Se permite enviar sem nota, verificar `chat-evaluation.ts`.
- Se segundo envio duplica avaliacao, verificar idempotencia do endpoint publico.

## CHAT-16 - Responsividade Mobile Do Chat

### Viewports

- Desktop: `1440x900`
- Tablet: `768x1024`
- Mobile: `390x844`

### Passos

1. Em desktop, abrir `/chat` e selecionar um ticket.
2. Validar lista, conversa e painel lateral.
3. Trocar para mobile.
4. Abrir `/chat`.
5. Validar que lista e conversa nao se sobrepoem.
6. Selecionar ticket.
7. Validar que a conversa ocupa a tela e o composer permanece visivel.
8. Enviar mensagem curta.
9. Abrir abas de contato/negociacao, se disponiveis.
10. Abrir `/chat/external/:tenantId` em mobile.
11. Preencher pre-chat e enviar mensagem.
12. Abrir `/embed/:tenantId` em mobile.
13. Validar que header, historico e composer cabem na tela.

### Passou Se

- Nao ha sobreposicao, clipping ou texto cortado em controles principais.
- Composer fica acessivel no mobile.
- Scroll do historico funciona.
- Chat externo e embed funcionam em tela pequena.

## Checklist Final Do Modulo

- [ ] Login e menu Chat validados.
- [ ] Conversa interna criada.
- [ ] Mensagem interna enviada.
- [ ] Anexo interno enviado.
- [ ] Chat externo aberto por URL publica.
- [ ] Cenario de 30+ mensagens concluido.
- [ ] Arquivo enviado pelo visitante.
- [ ] Ticket externo apareceu no painel.
- [ ] Historico persistiu apos reload.
- [ ] Ticket fechado e composer bloqueado.
- [ ] Tickets listados e detalhe aberto.
- [ ] Auto-reply CRUD validado.
- [ ] Respostas rapidas CRUD validado.
- [ ] Canais validados.
- [ ] Configuracao de roteamento validada.
- [ ] Lista de transmissao CRUD validada.
- [ ] Templates Meta validados.
- [ ] Chat embed publico validado.
- [ ] Avaliacao publica de atendimento validada.
- [ ] Responsividade mobile validada.
