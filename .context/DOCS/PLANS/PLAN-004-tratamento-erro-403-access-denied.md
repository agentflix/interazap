# TDD - Tratamento de Erro 403 Forbidden com Página de Acesso Negado

## Metadata

| Campo             | Valor                                        |
| ----------------- | -------------------------------------------- |
| Tech Lead         | @Rafael (AgentFlix Team)                     |
| Status            | Implemented                                 |
| Criado            | 2026-03-28                                  |
| Última Atualização| 2026-03-28                                  |

---

## 1. Context

### Background

Quando um usuário tenta executar uma ação que não tem permissão (ex: criar/editar usuário em `/settings/users`), o backend Laravel retorna um erro HTTP 403 com a mensagem "This action is unauthorized.". Atualmente, o frontend Angular não trata esse erro de forma amigável — a tela simplesmente quebra como se a API estivesse fora do ar, sem feedback ao usuário sobre o motivo real.

### Domínio

- **Autenticação e Autorização** (Auth)
- **Shared** (HTTP Interceptors)
- **UI/UX** (Páginas de erro)

### Stakeholders

- **Usuários finais**: Precisam entender que não têm permissão para executar uma ação
- **Desenvolvedores**: Precisam de logs claros quando erros 403 ocorrem
- **Produto**: Necessita de feedback amigável em vez de telas quebradas

---

## 2. Problem Statement & Motivation

### Problemas a Resolver

1. **Tela quebrada ao receber 403**: Quando o backend retorna erro 403, o usuário vê uma tela em branco ou com erro genérico em vez de uma mensagem explicativa
2. **Usuário sem feedback**: O usuário não sabe se a ação foi negada por falta de permissão ou por outro motivo
3. **Interceptors inconsistentes**: O `authInterceptor` já trata 401 (logout) e 402 (billing), mas ignora 403

### Por Que Agora?

-Melhora a experiência do usuário (UX)
- Falta de tratamento de 403 afeta múltiplas telas (settings/users, platform/users, CRM, etc.)
- Padronizar como erros de autorização são tratados em toda a aplicação

### Impacto de Não Resolver

- **UX**: Usuários frustrados sem entender por que suas ações falham
- **Suporte**: Aumento de tickets de suporte perguntando "por que não consigo fazer X?"
- **Percepção de qualidade**: Tela quebrada parece um bug, não uma restrição de permissão

---

## 3. Scope

### ✅ In Scope

1. **Criar página de "Acesso Negado"**
   - Componente Angular `AccessDeniedComponent`
   - Localização: `app/src/app/pages/auth/access-denied/`
   - Mensagem amigável com botão "Voltar ao início"

2. **Intercetar erro 403 no `authInterceptor`**
   - Adicionar tratamento de 403 após tratamento de 401
   - Redirecionar para `/access-denied` passando mensagem de erro
   - Consumir erro para não propagar

3. **Criar rota `/access-denied`**
   - Lazy loading do componente
   - Receber mensagem via queryParams

### ❌ Out of Scope

- Alterações no backend para mudar formato de erro
- Tratamento de erros 404 (não encontrado)
- Criação de novos componentes compartilhados
- internacionalização (i18n) - usar PT-BR padrão

### 🔮 Future Considerations

- Adicionar toast notification para erros 403 menos críticos
- Página customizada por tipo de erro (403, 500, 404)
- Log de tentativas de acesso não autorizado

---

## 4. Technical Solution

### Arquitetura

```
┌─────────────┐     403     ┌──────────────────┐
│   Browser   │◄────────────│  authInterceptor │
└─────────────┘             └────────┬─────────┘
                                    │
                                    │ navigate('/access-denied')
                                    ▼
                            ┌──────────────────┐
                            │ AccessDeniedPage│
                            └──────────────────┘
```

### Componentes

1. **`AccessDeniedComponent`** (`app/src/app/pages/auth/access-denied/access-denied.ts`)
   - Exibe mensagem de erro passada via queryParam
   - Botão "Voltar ao início" que redireciona para `/`
   - Utiliza shared components: `AfAlertComponent`, `AfButtonComponent`

2. **`authInterceptor`** (`app/src/app/core/interceptors/auth.interceptor.ts`)
   - Já trata 401 (logout + redirect to login)
   - Já trata 402 (redirect to /financial/invoices)
   - **Novo**: Tratar 403 - redirect to `/access-denied`

### API de Erro 403 (Backend - não modificado)

O backend já retorna:

```json
{
  "message": "This action is unauthorized.",
  "exception": "Symfony\\Component\\HttpKernel\\Exception\\AccessDeniedHttpException",
  "file": ".../Handler.php",
  "line": 672,
  "trace": [...]
}
```

**Não há mudanças no backend.**

### Fluxo de Dados

1. Usuário tenta criar/editar usuário sem permissão
2. Backend retorna HTTP 403 com `{ "message": "This action is unauthorized." }`
3. `authInterceptor` captura erro
4. Redireciona para `/access-denied?message=This%20action%20is%20unauthorized.`
5. `AccessDeniedComponent` exibe mensagem amigável
6. Usuário clica "Voltar ao início"
7. Redireciona para `/`

### Planos de Implementação

#### Fase 1: Criar AccessDeniedComponent

**Arquivo**: `app/src/app/pages/auth/access-denied/access-denied.ts`

```typescript
@Component({
  selector: 'app-access-denied',
  standalone: true,
  imports: [ReactiveFormsModule, AfAlertComponent, AfButtonComponent, RouterModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    <div class="flex items-center justify-center min-h-[400px]">
      <div class="max-w-md w-full space-y-6 text-center">
        <af-alert variant="warning" title="Acesso Negado" [message]="errorMessage()" />
        <af-button variant="primary" (click)="goHome()">Voltar ao início</af-button>
      </div>
    </div>
  `,
})
export class AccessDeniedComponent {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly errorMessage = signal('Você não tem permissão para realizar esta ação.');

  constructor() {
    const message = this.route.snapshot.queryParamMap.get('message');
    if (message) {
      this.errorMessage.set(decodeURIComponent(message));
    }
  }

  goHome(): void {
    void this.router.navigate(['/']);
  }
}
```

#### Fase 2: Adicionar Rota

**Arquivo**: `app/src/app/app.routes.ts`

```typescript
{
  path: 'access-denied',
  loadComponent: () =>
    import('./pages/auth/access-denied/access-denied').then((m) => m.AccessDeniedComponent),
},
```

#### Fase 3: Modificar authInterceptor

**Arquivo**: `app/src/app/core/interceptors/auth.interceptor.ts`

```typescript
// Adicionar após tratamento de 401:

if (error.status === 403) {
  const errorMessage = error.error?.message || 'This action is unauthorized.';
  void router.navigate(['/access-denied'], {
    queryParams: { message: encodeURIComponent(errorMessage) },
  });
  return EMPTY;
}
```

---

## 5. Risks

| Risco                                          | Impacto | Probabilidade | Mitigação                                           |
| ---------------------------------------------- | ------- | ------------ | --------------------------------------------------- |
| Rota `/access-denied` conflitando com existente | Baixo   | Baixa        | Verificar se rota já não existe antes de adicionar |
| QueryParam muito longo para mensagens           | Baixo   | Baixa        | Truncar mensagem se > 500 caracteres                |
| Looping de redirect se interceptor falhar       | Alto    | Muito baixa  | Não redirecionar se já estiver em `/access-denied`   |

---

## 6. Implementation Plan

| Fase | Task                        | Descrição                                          | Owner    | Status | Estimativa |
| ---- | --------------------------- | -------------------------------------------------- | -------- | ------ | ---------- |
| 1    | Criar AccessDeniedComponent | Criar página de acesso negado com mensagem e botão | @Frontend| TODO   | 1h         |
| 2    | Adicionar rota               | Criar rota `/access-denied` em app.routes.ts      | @Frontend| TODO   | 15min      |
| 3    | Modificar authInterceptor   | Adicionar tratamento de 403 no interceptor         | @Frontend| TODO   | 30min      |
| 4    | Testar cenário              | Testar erro 403 em /settings/users                | @Frontend| TODO   | 15min      |
| 5    | Gates                       | `pnpm run gate:all`                               | @Frontend| TODO   | 10min      |

**Total Estimado**: ~2h 30min

---

## 7. Testing Strategy

### Test Scenarios

1. **Erro 403 em /settings/users**
   - Acessar sem permissão
   - Verificar redirect para `/access-denied`
   - Verificar mensagem exibida

2. **Erro 403 em /platform/users**
   - Mesmo teste para outra tela

3. **Redirect loop prevention**
   - Tentar acessar `/access-denied` deve funcionar sem loop

### Test Types

| Tipo          | Escopo                              | Cobertura |
| ------------- | ----------------------------------- | --------- |
| **Unit Test** | AccessDeniedComponent (se existir) | -         |
| **Manual**    | Cenários de erro 403                | 100%      |

---

## 8. Security Considerations

### ✅ Seguro

- Erros 403 são legítimos — não há vulnerabilidade
- Mensagem de erro do backend é sanitizada via `encodeURIComponent`
- Rota `/access-denied` é pública (não requer autenticação)

### O que NÃO fazer

- ❌ Não exibir stack trace ou informações internas
- ❌ Não redirecionar para login (isso é 401, não 403)
- ❌ Não logar a mensagem de erro no cliente (já está no backend)

---

## 9. Monitoring & Observability

### Métricas a Rastrear (Opcional para V1)

| Métrica                      | Tipo     | Limiar de Alerta |
| ---------------------------- | -------- | ---------------- |
| `access_denied.page_views`   | Counter  | > 10/min         |
| `auth.forbidden_error_count` | Counter  | > 5/min          |

### Logs

**Backend** (já existe):
```
[LogAccessDeniedMiddleware] 403 Forbidden - User: {userId} - Action: {action} - Message: {message}
```

**Frontend** (opcional para V2):
- Não é necessário para V1

---

## 10. Rollback Plan

### Gatilhos

| Gatilho                           | Ação                                      |
| --------------------------------- | ---------------------------------------- |
| Comportamento inesperado na página | Reverter commit com `git revert`          |
| Erro de build                    | Não fazer deploy, reverter mudanças      |

### Passos de Rollback

1. `git revert {commit_hash}`
2. `pnpm run gate:all`
3. Deploy do revert

---

## 11. Alternatives Considered

| Opção                                   | Prós                          | Contras                                    | Status  |
| --------------------------------------- | ----------------------------- | ----------------------------------------- | ------- |
| **Toast notification (V1)**             | Menos intrusivo, usuário continua na página | Menos visível, pode passar despercebido | Descartada - usuário prefers clareza |
| Página de erro genérica (500)         | Simplicidade                   | Não diferencia 403 de outros erros         | Descartada - precisa ser específico para 403 |
| Alert/Modal no lugar da página          | Meno navegação                 | Pode conflitar com modais existentes       | Descartada - página é mais robusta |

---

## 12. Open Questions

| #   | Questão                                   | Context                                  | Status  |
| --- | ----------------------------------------- | ---------------------------------------- | ------- |
| 1   | Devemos logar acesso negado no frontend? | Analytics de uso / compliance             | 🟡 A definir |
| 2   | Qual deve ser o comportamento de "Voltar"? | Voltar para página anterior ou home?      | ✅ Resolved: Home |

---

## 13. Files to Modify

### Frontend (Angular)

| Arquivo                                         | Ação     | Descrição                              |
| ----------------------------------------------- | -------- | -------------------------------------- |
| `app/src/app/pages/auth/access-denied/access-denied.ts` | Criar   | Página de Acesso Negado               |
| `app/src/app/app.routes.ts`                    | Modificar | Adicionar rota `/access-denied`         |
| `app/src/app/core/interceptors/auth.interceptor.ts` | Modificar | Tratar erro 403 e redirecionar        |

### Backend

| Arquivo | Ação | Descrição |
| ------- | ---- | --------- |
| Nenhum  | -    | Não requer mudanças |

---

## 14. Acceptance Criteria

- [ ] Página de Acesso Negado exibida quando backend retorna 403
- [ ] Mensagem de erro do backend exibida na página
- [ ] Botão "Voltar ao início" redireciona para `/`
- [ ] Não há looping de redirect
- [ ] Gates passam (`pnpm run gate:all`)
- [ ] Commits realizados
