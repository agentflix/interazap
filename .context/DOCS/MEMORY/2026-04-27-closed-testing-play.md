# Google Play Closed Testing — 14 Dias Obrigatórios

## Metadados

| Campo        | Valor                                                            |
| ------------ | ---------------------------------------------------------------- |
| **Tipo**     | 📋 Processo                                                      |
| **Data**     | 2026-04-27                                                       |
| **Contexto** | FEAT-047 TASK-047.22                                             |
| **Tags**     | mobile, android, google-play, closed-testing, policy, compliance |

---

## Por Que é Obrigatório?

O Google Play exige que **novas contas de developer** completem um período de **Closed Testing com no mínimo 12 testers opt-in** por **14 dias corridos** antes de publicar para produção.

> **Política oficial:** [support.google.com/googleplay/android-developer/answer/14151465](https://support.google.com/googleplay/android-developer/answer/14151465)
>
> Sem cumprir esse requisito, o botão "Promote to production" fica desabilitado no Play Console.

### Critérios de elegibilidade

- [ ] Mínimo **12 testers distintos** que fizeram opt-in e instalaram o app
- [ ] Período mínimo de **14 dias corridos** de Closed Testing ativo
- [ ] Crash-free rate ≥ 99% ao longo do período
- [ ] Nenhuma violação de política detectada pelo Google Play Protect

---

## Diferença: Internal Testing vs Closed Testing

| Critério                 | Internal Testing | Closed Testing        |
| ------------------------ | ---------------- | --------------------- |
| Máximo de testers        | 100              | Ilimitado (por grupo) |
| Opt-in público           | Não              | Sim (URL pública)     |
| Conta para elegibilidade | ❌               | ✅                    |
| Review do Google         | Automático       | Review parcial        |
| Promoção para Prod       | Não direta       | ✅ Após 14 dias       |

---

## Passo a Passo: Criar Closed Testing Track

### Pré-requisito

- Internal Testing ativo com pelo menos uma build (TASK-047.21 concluída)
- Mínimo 12 endereços de e-mail disponíveis para convidar

### 1. Criar track de Closed Testing

```
Play Console → App → Testing → Closed testing
→ [Create closed testing release]
→ Selecionar mesma .aab já usada no Internal Testing
→ Preencher release notes (igual ao Internal)
→ [Save] → [Review release] → [Start rollout to Closed testing]
```

### 2. Criar grupo de testers

```
Play Console → Closed testing → [Create email list]
→ Nome: "InteraZap Closed Beta"
→ Adicionar mínimo 12 e-mails distintos
```

**Quem convidar:**

- Atendentes internos InteraZap (priority)
- Clientes beta voluntários
- Familiares/amigos Android (emergência)
- OBS: e-mails devem ter conta Google ativa com Play Store

### 3. Enviar link de opt-in

O Play Console gera um link único para opt-in:

```
https://play.google.com/apps/testing/com.interazap.app
```

**Template de e-mail para testers:**

```
Assunto: [InteraZap] Convite para teste do novo app Android 🤖

Olá [nome],

Você foi selecionado(a) para testar o novo app Android do InteraZap!

Para instalar, clique no link abaixo e aceite o convite:
[LINK DE OPT-IN]

Após aceitar:
1. Abra a Play Store
2. Busque por "InteraZap"
3. Instale normalmente

O período de testes dura 14 dias. Seu feedback é essencial!

Qualquer problema: reply neste e-mail ou WhatsApp [CONTATO]

Obrigado!
— Time InteraZap
```

### 4. Monitorar opt-ins

- Play Console → Closed testing → ver coluna "Opted-in testers"
- Meta: 12 testers opt-in até o **Dia 3**
- Se < 12 testers no Dia 3: recrutar mais ativamente

---

## Monitoramento Durante os 14 Dias

### Métricas a verificar diariamente

```
Play Console → Android vitals → Crashes & ANRs
```

| Métrica         | Alvo               | Ação se Falhar        |
| --------------- | ------------------ | --------------------- |
| Crash-free rate | > 99%              | Novo build IMEDIATO   |
| ANR rate        | < 0.5%             | Investigar e corrigir |
| Testers ativos  | ≥ 12               | Recrutar mais         |
| Sessões > 5 min | ≥ 50% das sessions | Investigar UX         |

### Checklist semana 1 (Dias 1–7)

- [ ] Track Closed Testing ativo (data de início registrada)
- [ ] 12+ testers com opt-in confirmado
- [ ] Zero crashes P1 em 48h
- [ ] Primeira sessão de feedback coletada
- [ ] Sentry configurado: `crash_rate < 1%`

### Checklist semana 2 (Dias 8–14)

- [ ] Crash-free sessions ≥ 99%
- [ ] Nenhum novo P1 aberto
- [ ] Release notes atualizadas se houver patch
- [ ] Play Console: verificar se aparece mensagem "Your app is eligible for production"
- [ ] Preparar submission checklist para produção

---

## Acelerar o Processo (se necessário)

O requisito de 14 dias é fixo — não pode ser acelerado. Porém:

1. **Múltiplos grupos de Closed Testing**: Criar um grupo adicional com mais testers não acelera o contador, mas melhora a qualidade do feedback.
2. **Build patches**: Novas builds durante o período não reiniciam o contador (desde que dentro do mesmo track).
3. **Internal Testing em paralelo**: Pode manter internal testing ativo para testers de confiança receberem builds mais frequentes.

---

## Transição para Produção

Após os 14 dias com 12+ testers:

1. Play Console → Closed testing → **Promote release**
2. Selecionar track **Production**
3. Revisar dados do app (screenshots, descrição, classificação etária)
4. **Rollout gradual recomendado**: iniciar com 10% → monitor 48h → 50% → monitor 48h → 100%

```
Play Console → Production → Create production release
→ Selecionar build aprovada no Closed Testing
→ Rollout percentage: 10%
→ [Start rollout to production]
```

---

## Log de Execução

Preencher durante execução:

```
Data de início do Closed Testing: ___________
Data de término (D+14): ___________
Testers opt-in no Dia 1: ___
Testers opt-in no Dia 3: ___
Testers opt-in no Dia 14: ___
Crash-free rate médio: ___%
Bugs encontrados: ___
Go/No-Go para Produção: ✅ GO / ❌ NO-GO
```
