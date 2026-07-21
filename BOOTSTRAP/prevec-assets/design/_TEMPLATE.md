---
feature: [nome-da-feature]
tipo: [wireframe | component-spec | ux-flow | design-system]
status: [rascunho | aprovado]
aprovado-por: [quem aprovou]
data: [YYYY-MM-DD]
---

# Design: [Nome da Feature]

> Arquivo obrigatório para toda task de Frontend.
> Caminho: `.context/DESIGN/[feature]-[tipo].md`
> BUILDER deve ler este arquivo ANTES de implementar qualquer componente ou página.

---

## Visão Geral

[2-3 frases descrevendo o objetivo visual e UX desta feature]

---

## Fluxo do Usuário

```
[Passo 1] → [Passo 2] → [Passo 3] → [Resultado]
```

Descrever cada passo:
- **[Passo 1]:** [o que o usuário vê/faz]
- **[Passo 2]:** [o que acontece a seguir]
- **[Resultado]:** [estado final esperado]

---

## Wireframes / Layout

### [Nome da Tela ou Componente]

```
┌─────────────────────────────────┐
│ [Cabeçalho / Header]            │
├─────────────────────────────────┤
│                                 │
│  [Área principal de conteúdo]   │
│                                 │
│  [Campo 1]  [Campo 2]           │
│                                 │
│  [Ação primária] [Ação sec.]    │
│                                 │
└─────────────────────────────────┘
```

**Elementos obrigatórios:**
- [ ] [elemento-1]: [descrição e comportamento]
- [ ] [elemento-2]: [descrição e comportamento]

**Estados:**
- **Default:** [como aparece normalmente]
- **Loading:** [feedback visual durante carregamento]
- **Erro:** [como exibir erros de validação]
- **Sucesso:** [confirmação visual]

---

## Especificação de Componentes

### [NomeDoComponente]

**Props obrigatórias:**
```typescript
{
  [prop1]: [tipo]  // [descrição]
  [prop2]: [tipo]  // [descrição]
}
```

**Comportamento:**
- [regra de negócio ou interação]
- [validação ou restrição]

**Referência de estilo:**
- Cor primária: [token ou hex]
- Tipografia: [estilo]
- Espaçamento: [valor]

---

## Validações e Regras

| Campo | Tipo | Obrigatório | Validação | Mensagem de Erro |
|---|---|---|---|---|
| [campo] | [text/number/select] | [sim/não] | [regex ou regra] | [mensagem] |

---

## Responsividade

| Breakpoint | Comportamento |
|---|---|
| Mobile (< 768px) | [descrição do layout mobile] |
| Tablet (768–1024px) | [descrição do layout tablet] |
| Desktop (> 1024px) | [descrição do layout desktop] |

---

## Critérios de Aprovação Visual

- [ ] Layout corresponde ao wireframe aprovado
- [ ] Todos os estados (default, loading, erro, sucesso) implementados
- [ ] Responsividade testada em mobile e desktop
- [ ] Acessibilidade: labels, aria-*, foco de teclado
- [ ] Integração com API / dados reais funcionando
