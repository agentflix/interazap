# Memory: Correção do comportamento da flag `track_stock` em produtos CRM

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-05 |
| **Autor** | ORCHESTRATOR / DEV |
| **Contexto** | Feature: flag de controle de estoque no cadastro de produtos CRM |
| **Tags** | [crm, stock, track_stock, shouldTrackStock, backend, frontend, ddd] |

---

## Situação
O campo `track_stock` já existia no banco de dados, model, DTO, request e resource do produto CRM, mas nunca foi exposto na UI. A lógica de controle de estoque em `CRMNegotiationProductActions::shouldTrackStock()` retornava `(bool) $product->track_stock || $product->stock > 0`, o que significava que **qualquer produto com estoque > 0 era automaticamente rastreado**, mesmo que o usuário explicitamente desmarcasse a flag.

Isso violava a intenção do usuário: se ele quer um produto "infinito" (sem controle de estoque), ele deve poder ter `stock > 0` no cadastro sem que o sistema automaticamente passe a debitar.

---

## Decisão / Aprendizado
1. **`shouldTrackStock()` deve respeitar APENAS a flag explícita.** O valor de `stock` é um dado, não uma regra de negócio. A regra é: se `track_stock = false`, o produto é infinito — independente do valor de `stock`.

2. **O frontend deve expor a flag ao usuário.** O comportamento anterior (auto-definir `track_stock = isProduct` no submit do form) tirava a decisão do usuário. Agora o usuário controla via switch.

3. **Serviços (`type = 'service'`) sempre têm `track_stock = false`.** Isso já era garantido no DTO do backend e foi mantido.

4. **Duas interfaces `ProductService` separadas no frontend.** O monorepo tem `crm-product-service.service.ts` (usado pela página de produtos) e `product-service.service.ts` (usado pela negociação). Ambas precisam ter `track_stock` e `stock` sincronizados.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter `|| $product->stock > 0` como fallback de segurança | Conflita diretamente com o requisito de "produto infinito". Um produto com estoque cadastrado mas `track_stock = false` continuaria debitando. |
| Default `track_stock = true` para produtos existentes | Mudaria comportamento retroativamente. Produtos existentes com `track_stock = false` (default da migration) passariam a debitar estoque sem o usuário saber. |
| Controlar estoque via trigger no banco | Descartado — viola o princípio de manter regras de negócio na camada de Domain (Actions), não no banco. |
| Criar nova tabela de movimentação de estoque (inventory transactions) | Fora do escopo solicitado. O usuário pediu apenas uma flag. Uma tabela de movimentação seria uma feature separada e maior. |

---

## Consequências

### Positivas
- Usuário tem controle explícito sobre quais produtos debitam estoque
- Produtos podem ter `stock` cadastrado para referência mas não debitar (ex: estoque de showroom)
- A lógica fica mais previsível: a flag é a fonte da verdade
- Testes cobrem tanto o cenário de debito quanto o cenário de produto infinito

### Negativas / Trade-offs
- Produtos existentes com `stock > 0` e `track_stock = false` (default) continuam não debitando. Isso é intencional, mas pode surpreender usuários que esperavam que todo produto com estoque cadastrado automaticamente controlasse estoque.
- O frontend mostra "Ilimitado" para produtos sem controle, o que pode ser confuso se o produto tiver um valor de `stock` cadastrado. O valor de `stock` ainda aparece no painel de detalhes para referência.

---

## Decisão de Layout (atualização)

O layout inicial colocava o switch "Controlar estoque" no final do formulário, após os campos de estoque (`Unidade`, `Estoque`, `Estoque Mín.`). Isso criava uma hierarquia visual invertida — os campos filhos apareciam antes do pai (toggle). O usuário não entendia que aqueles campos só faziam sentido quando o switch estava ligado.

**Padrão adotado:** O mesmo usado nas Configurações do Chat (`chat-configuration.html`):
1. Switch vem **antes** dos campos condicionais
2. Campos aparecem dentro de `@if (switch.value)`
3. Indentação com `pl-6 border-l-2 border-accent-500/30` cria hierarquia visual clara
4. Quando inativo, exibe banner informativo explicando o estado ("produto ilimitado")
5. `helpText` no switch explica a consequência da ação

**Mockup:** `.context/LAYOUT/mockup-product-stock-toggle.md`

## Referências
- Feature doc: não criado (escopo direto da conversa)
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-05-05.md`
- Arquivos principais:
  - `api/src/Domain/CRM/Actions/CRMNegotiationProductActions.php`
  - `api/src/Domain/CRM/Actions/CRMProductActions.php`
  - `app/src/app/pages/crm/products-services/components/product-service-form/crm-product-service-form.ts`
  - `app/src/app/pages/crm/products-services/components/product-service-form/crm-product-service-form.html`
  - `app/src/app/pages/crm/negotiation-show/components/negotiation-products-tab/negotiation-products-tab.ts`
