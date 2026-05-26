---
feature: trial-signup-checkout
tipo: ux-flow
status: rascunho
aprovado-por: pending
data: 2026-05-25
---

# Design: Página `/signup` + Google Callback

> Página pública de cadastro com email/senha + botão Google OAuth.
> Página `/auth/google/callback` é técnica (sem UI relevante além de loading).

---

## Visão Geral

Página de cadastro público minimalista que entrega trial automaticamente ao concluir. Dois caminhos: (a) form tradicional email/senha com aceite de termos, (b) "Continuar com Google" que pula form e ativa trial imediatamente. UX prioriza Google no topo para reduzir fricção.

---

## Fluxo do Usuário

```
[Visita /signup]
   ├─ clica "Continuar com Google" → /auth/google/redirect → consent screen Google
   │                                                          │
   │                                                          ▼
   │                                                /auth/google/callback (api)
   │                                                          │
   │                                                          ▼
   │                                              cria tenant + user + trial
   │                                                          │
   │                                                          ▼
   │                                     redirect → app /auth/google-callback?token=...
   │                                                          │
   │                                                          ▼
   │                                          persiste token, redirect /dashboard
   │
   └─ preenche form (name/email/senha) → POST /auth/signup
                                                  │
                                                  ▼
                                       cria tenant + user + trial
                                                  │
                                                  ▼
                                  retorna {user, token, permissions, tenant_plan}
                                                  │
                                                  ▼
                                       persiste token, redirect /dashboard
```

---

## Wireframes / Layout

### Página `/signup`

```
┌────────────────────────────────────────────────────────────┐
│                       [ InteraZap logo ]                   │
│                                                            │
│                    Crie sua conta grátis                   │
│                7 dias e 100 mensagens IA                   │
│                                                            │
│   ┌────────────────────────────────────────────────────┐   │
│   │  G   Continuar com Google                          │   │
│   └────────────────────────────────────────────────────┘   │
│                                                            │
│   ─────────── ou cadastre com email ───────────            │
│                                                            │
│   Nome completo                                            │
│   [ ________________________________________________ ]     │
│                                                            │
│   Email                                                    │
│   [ ________________________________________________ ]     │
│                                                            │
│   Senha (mín 8 caracteres)                                 │
│   [ ________________________________________________ ]     │
│                                                            │
│   ☐ Aceito os termos de uso e política de privacidade      │
│                                                            │
│   ┌────────────────────────────────────────────────────┐   │
│   │              Criar conta grátis                    │   │
│   └────────────────────────────────────────────────────┘   │
│                                                            │
│         Já tem conta? [ Entrar ]                           │
└────────────────────────────────────────────────────────────┘
```

### Página `/auth/google-callback`

```
┌────────────────────────────────────────────────────────────┐
│                                                            │
│                     [ Spinner animado ]                    │
│                                                            │
│              Finalizando seu cadastro...                   │
│                                                            │
└────────────────────────────────────────────────────────────┘
```

Sem interação. Loading max 5s; se exceder, exibe erro com link "Tentar novamente".

**Elementos obrigatórios `/signup`:**
- [ ] `logo`: logo InteraZap centralizado no topo
- [ ] `headline`: "Crie sua conta grátis"
- [ ] `subhead`: "7 dias e 100 mensagens IA"
- [ ] `google_button`: `GoogleLoginButtonComponent` no topo (call `/auth/google/redirect`)
- [ ] `divider`: "ou cadastre com email"
- [ ] `name_input`: required, min 2 chars
- [ ] `email_input`: required, validação RFC 5322
- [ ] `password_input`: required, min 8 chars, sugestão de força (low/med/high)
- [ ] `terms_checkbox`: required (botão submit fica disabled enquanto false)
- [ ] `submit_button`: "Criar conta grátis", disabled enquanto form inválido
- [ ] `login_link`: rodapé "Já tem conta? Entrar"

**Estados:**
- **Default:** form vazio, submit disabled
- **Form válido:** submit habilitado (azul primary)
- **Loading submit:** spinner no botão, form readonly
- **Erro 422 (email duplicado):** mensagem inline abaixo do campo email "Email já cadastrado · Entrar?"
- **Erro 429 (rate-limit):** mensagem topo "Muitas tentativas. Tente novamente em alguns minutos."
- **Erro 5xx:** toast "Erro inesperado. Tente novamente."
- **Sucesso:** redirect imediato para `/dashboard` (sem confirmação adicional na UI)

---

## Especificação de Componentes

### SignupPageComponent

**Selector:** `app-signup-page`
**Standalone:** sim
**Localização:** `app/src/app/pages/auth/signup/signup-page.ts`
**Route:** `/signup` (público, fora do `MainLayout`)

**Form (Reactive Forms):**
```typescript
form = this.fb.group({
  name: ['', [Validators.required, Validators.minLength(2)]],
  email: ['', [Validators.required, Validators.email]],
  password: ['', [Validators.required, Validators.minLength(8)]],
  accept_terms: [false, Validators.requiredTrue],
});
```

**Comportamento:**
- Submit chama `authService.signup(form.value)` → persiste token via `AuthStorageService` → `router.navigate(['/dashboard'])`
- Erro `409 conflict` em email → highlight campo + mensagem inline "Email já cadastrado"
- Mantém estado se navegar fora e voltar (Form Builder default)
- Google button reusa `GoogleLoginButtonComponent` — apenas chama `window.location.href = '/api/auth/google/redirect'`

### GoogleCallbackPageComponent

**Selector:** `app-google-callback-page`
**Standalone:** sim
**Localização:** `app/src/app/pages/auth/google-callback/google-callback-page.ts`
**Route:** `/auth/google-callback`

**Comportamento:**
- No `ngOnInit`, lê `?token=...` da query string
- Chama `authService.handleGoogleToken(token)` que faz GET `/auth/me` p/ buscar user + permissions
- Persiste token e dados, navega para `/dashboard`
- Em caso de falha, navega para `/signup?error=google_callback_failed`

### GoogleLoginButtonComponent

**Selector:** `app-google-login-button`
**Standalone:** sim
**Localização:** `app/src/app/core/components/google-login-button/google-login-button.ts`

**Comportamento:**
- Botão padrão Google com logo oficial (SVG inline)
- Click → `window.location.href = environment.apiUrl + '/auth/google/redirect'`
- Estilo: padrão "Google Sign-In" (white bg, dark text, soft border)
- Inputs: `label` (default "Continuar com Google")

**Referência de estilo:**
- Cor primária botão Google: `#FFFFFF` bg, `#1F1F1F` text, `#DADCE0` border
- Tipografia: Roboto 14px peso 500 (cumpre branding Google)
- Altura: 40px
- Espaçamento entre seções da página: 24px

---

## Validações e Regras

| Campo | Tipo | Obrigatório | Validação | Mensagem |
|---|---|---|---|---|
| name | text | sim | min 2 chars | "Informe seu nome" |
| email | email | sim | RFC 5322 | "Email inválido" |
| password | password | sim | min 8 chars | "Senha precisa ter ao menos 8 caracteres" |
| accept_terms | checkbox | sim | true | "Aceite os termos para continuar" |

API erros tratados:
- `422 email duplicate` → "Email já cadastrado. [Entrar?]"
- `422 weak password` → "Senha muito fraca. Use letras, números e símbolos"
- `429 rate limit` → toast topo "Muitas tentativas. Aguarde alguns minutos"

---

## Responsividade

| Breakpoint | Comportamento |
|---|---|
| Mobile (< 768px) | Form full-width com padding 16px; submit button sticky no bottom em iOS |
| Tablet (768–1024px) | Card centralizado max-width 480px |
| Desktop (> 1024px) | Card centralizado max-width 480px, altura adapta ao conteúdo |

---

## Acessibilidade

- `<form>` com `aria-labelledby="signup-title"`
- Labels associados a inputs via `for`/`id`
- Erros inline com `aria-describedby` ligando ao input
- `aria-required="true"` em campos obrigatórios
- Foco automático no campo `name` ao montar página
- Tab order: Google button → divider (skip) → name → email → password → terms → submit → login link

---

## Critérios de Aprovação Visual

- [ ] Layout corresponde ao wireframe da página `/signup`
- [ ] Botão Google segue branding oficial (logo, cor, peso de fonte)
- [ ] Todos os estados (default, loading, erro 422, erro 429, erro 5xx) implementados
- [ ] Responsividade testada em mobile e desktop
- [ ] Página `/auth/google-callback` exibe spinner durante processamento
- [ ] Sucesso redireciona para `/dashboard` sem flicker do `/signup`
- [ ] Tab order acessível e foco visível
- [ ] Mensagens de erro PT-BR alinhadas com padrão do projeto
