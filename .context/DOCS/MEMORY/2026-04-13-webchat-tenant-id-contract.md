# Memory: Webchat público deve enviar tenant_id explícito no contrato de sessão

## Metadados

| Campo        | Valor                                                      |
| ------------ | ---------------------------------------------------------- |
| **Tipo**     | ⚠️ Armadilha                                               |
| **Data**     | 2026-04-13                                                 |
| **Autor**    | DEBUG Agent                                                |
| **Contexto** | Correção de bug na rota pública `/chat/external/:tenantId` |
| **Tags**     | webchat, tenant, contrato-api, angular                     |

---

## Situação

Ao iniciar conversa na tela pública de webchat, a API retornava `tenant_id é obrigatório`.

---

## Decisão / Aprendizado

No fluxo público de webchat, o `tenant_id` deve ser enviado explicitamente no body de criação de sessão (`POST /api/webchat/sessions`) com os campos `visitor_name` e `visitor_phone`.

Também é obrigatório resolver o tenant pela rota atual (`/chat/external/:tenantId` ou `/embed/:tenantId`) e não por parsing legado de `/chat/:tenantSlug`.

---

## Alternativas Consideradas

| Alternativa                                                  | Por que descartada                                                                    |
| ------------------------------------------------------------ | ------------------------------------------------------------------------------------- |
| Manter `X-Tenant-Slug` e payload legado `{ name, whatsapp }` | Não atende ao contrato atual do backend (requer `tenant_id`).                         |
| Forçar fallback para tenant default                          | Mascararia erro de isolamento multi-tenant e poderia abrir conversa no tenant errado. |

---

## Consequências

### Positivas

- Evita erro de validação `tenant_id é obrigatório` no webchat público.
- Garante isolamento multi-tenant correto no início da sessão.

### Negativas / Trade-offs

- Exige repasse explícito de `tenantId` para o componente de pré-chat.

---

## Referências

- Feature: `.context/DOCS/FEATURES/FEAT-040-webchat-widget.md`
- Arquivos principais:
    - `app/src/app/pages/webchat/components/pre-chat/pre-chat.component.ts`
    - `app/src/app/pages/webchat/services/webchat.service.ts`
    - `api/src/Domain/Chat/Http/Controllers/WebChatSessionController.php`
