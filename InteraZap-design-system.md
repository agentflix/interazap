# InteraZap — Guia de Design / Design System

Guia de referência para aplicar a mesma identidade visual da landing page no app (painel, CRM, inbox, mobile). Mantém o padrão de cores, tipografia, espaçamento e componentes.

---

## 1. Paleta de cores

### Verde da marca (accent / ação)
| Token | HEX | Uso |
|---|---|---|
| `--green-500` | `#2fc85a` | Cor principal da marca. Botões primários, ícones de destaque, links, bordas ativas. |
| `--green-400` | `#3de86e` | Hover de botões e links. |
| `--green-300` | `#7fe6a0` | Textos/labels de destaque sobre fundo escuro (status "online", eyebrows). |
| `--green-200` | `#8ff0ad` | Textos verdes claros, chips de destaque. |
| `--green-ink` | `#05230f` | Texto/ícone **sobre** o verde (ex: label dentro de botão verde). |
| `--green-deep` | `#1f7a3d` | Balões de mensagem enviada, tons verdes escuros. |

### Neutros escuros (fundo / superfícies)
| Token | HEX | Uso |
|---|---|---|
| `--bg-900` | `#0a0b0a` | Fundo base da aplicação (body). |
| `--bg-850` | `#0b0e0c` | Áreas de chat / painéis internos mais fundos. |
| `--bg-800` | `#0c0e0c` | Seções alternadas, faixas. |
| `--bg-750` | `#0d100e` | Cabeçalhos de card, input backgrounds. |
| `--surface-1` | `#111412` | Cards, painéis, itens de lista. |
| `--surface-2` | `#141814` | Itens internos de card (linhas, pills). |
| `--surface-input` | `#161a17` | Campos de input. |

### Texto
| Token | HEX | Uso |
|---|---|---|
| `--text-100` | `#f4f6f2` | Títulos / headings. |
| `--text-200` | `#e8ebe6` | Texto padrão forte (body em UI). |
| `--text-300` | `#d5dad3` | Texto de ênfase secundária. |
| `--text-400` | `#c3c8c1` | Texto corrido em cards. |
| `--text-500` | `#a2a8a1` | Parágrafos secundários / descrições. |
| `--text-600` | `#8b918a` | Legendas, textos auxiliares. |
| `--text-700` | `#6f756e` | Micro-copy, placeholders, timestamps. |
| `--text-placeholder` | `#5a615a` | Placeholder de input, texto desativado. |

### Bordas / divisores
| Token | Valor | Uso |
|---|---|---|
| `--border-subtle` | `rgba(255,255,255,0.06)` | Divisores de seção, bordas de nav. |
| `--border-card` | `rgba(255,255,255,0.07-0.08)` | Bordas de cards e painéis. |
| `--border-input` | `rgba(255,255,255,0.08)` | Bordas de input. |
| `--border-green` | `rgba(47,200,90,0.28-0.32)` | Bordas de destaque / card ativo. |

### Cor de alerta / atenção (uso pontual)
| Token | HEX | Uso |
|---|---|---|
| `--warn` | `#e0894f` / `#eab088` | Objeções de risco alto, avisos. Usar com muita moderação. |

### Verdes translúcidos (glows / fundos suaves)
- Glow de destaque: `radial-gradient(ellipse, rgba(47,200,90,0.16), transparent 68%)`
- Fundo de ícone/chip: `rgba(47,200,90,0.10)` a `rgba(47,200,90,0.14)`
- Card destacado: `linear-gradient(180deg, #12281b, #0d130f)`

---

## 2. Tipografia

- **Fonte principal:** `Figtree` (400, 500, 600, 700, 800, 900) — títulos e corpo.
- **Fonte mono:** `JetBrains Mono` (400, 500) — eyebrows, labels técnicos, números, tags, timestamps.

```
@import url('https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap');
```

### Escala (referência)
| Papel | Tamanho | Peso | Notas |
|---|---|---|---|
| Display / H1 | `clamp(38px, 5.4vw, 68px)` | 800 | `letter-spacing:-0.025em; line-height:1.02` |
| H2 seção | `clamp(28px, 3.4vw, 44px)` | 800 | `letter-spacing:-0.02em; line-height:1.1` |
| H3 card | `18–30px` | 700 | |
| Body | `15–19px` | 400 | `line-height:1.55–1.6; color:var(--text-500)` |
| Eyebrow / label | `12px` | 400 (mono) | `letter-spacing:.12–.14em; color:var(--green-500); UPPERCASE` |
| Micro-copy | `12.5–13px` | 400 (mono) | `color:var(--text-700)` |

---

## 3. Forma & espaçamento

- **Raio de borda:**
  - Botões / pills / chips: `999px` (totalmente arredondado)
  - Cards: `16px`
  - Cards grandes / painéis: `22–26px`
  - Inputs: `999px` (chat) ou `12–14px` (formulários)
  - Ícone-container: `10–11px`
- **Sombras:**
  - Botão primário: `0 12px 34px rgba(47,200,90,0.30)`
  - Card elevado: `0 24px 70px rgba(0,0,0,0.55)` ou glow verde `0 24px 70px rgba(47,200,90,0.12)`
- **Espaçamento de seção:** `90–110px` vertical; padding lateral `40px` (desktop).
- **Gap padrão de grid/flex:** `16–22px`. Sempre usar `gap` em flex/grid, nunca margens soltas.

---

## 4. Componentes

### Botão primário
```
background: #2fc85a;
color: #05230f;
font-weight: 700;
padding: 15px 28px;
border-radius: 999px;
box-shadow: 0 12px 34px rgba(47,200,90,0.32);
/* hover */ background: #3de86e;
```

### Botão secundário (ghost)
```
background: transparent;
color: #e8ebe6;
border: 1px solid rgba(255,255,255,0.16);
border-radius: 999px;
padding: 15px 26px;
/* hover */ border-color: rgba(255,255,255,0.35);
```

### Card
```
background: #111412;
border: 1px solid rgba(255,255,255,0.07);
border-radius: 16px;
padding: 24–26px;
```

### Card destacado (ativo / plano recomendado)
```
background: linear-gradient(180deg, #12281b, #0d130f);
border: 1.5px solid #2fc85a;
border-radius: 22px;
box-shadow: 0 24px 70px rgba(47,200,90,0.14);
```

### Ícone-container
```
width: 38–40px; height: 38–40px;
border-radius: 10–11px;
background: rgba(47,200,90,0.10–0.12);
color: #2fc85a; /* ícone */
display: flex; align-items: center; justify-content: center;
```

### Eyebrow (label de seção)
```
font-family: 'JetBrains Mono';
font-size: 12px;
letter-spacing: .14em;
color: #2fc85a;
text-transform: uppercase;
```

### Chip / pill de status
```
background: rgba(47,200,90,0.10);
border: 1px solid rgba(47,200,90,0.28);
color: #8ff0ad;
border-radius: 999px;
padding: 6px 14px;
font-family: 'JetBrains Mono'; font-size: 12px;
```

### Input
```
background: #161a17;
border: 1px solid rgba(255,255,255,0.08);
border-radius: 999px; /* ou 12px em forms */
color: #e8ebe6;
padding: 11px 16px;
/* placeholder */ color: #5a615a;
/* focus */ border-color: rgba(47,200,90,0.4);
```

---

## 5. Ícones

- Biblioteca: **Lucide** (`https://unpkg.com/lucide@0.294.0`), `stroke-width: 2`.
- Cor padrão do ícone: `#2fc85a` (verde) sobre fundo escuro; `#05230f` quando dentro de botão verde.
- Tamanhos: `17px` inline, `19–21px` em cards, `24px+` em destaques.
- Ícones usados: `bot`, `zap`, `users`, `user-plus`, `trending-up`, `history`, `calendar-check`, `unlock`, `shield-check`, `inbox`, `repeat`, `hourglass`, `banknote`, `bar-chart-3`, `calendar`, `check-circle-2`, `x-circle`, `arrow-right`, `arrow-up-right`, `rocket`.

---

## 6. Princípios

1. **Fundo sempre escuro.** A base é `#0a0b0a`; superfícies sobem em luminância (`#111412`) — nunca fundo claro.
2. **Verde é ação e destaque, não decoração.** Reserve `#2fc85a` para o que o usuário deve notar/clicar. Não pinte áreas grandes de verde.
3. **Texto sobre verde é sempre o verde-tinta escuro** (`#05230f`), nunca branco.
4. **Mono para o "técnico", Figtree para o humano.** Números, tags, labels e status em JetBrains Mono; tudo que é conversa/venda em Figtree.
5. **Cantos arredondados generosos** (cards 16px+, botões pill).
6. **Glows verdes sutis** para dar profundidade, nunca gradientes berrantes de fundo inteiro.
7. **Hierarquia de texto por opacidade/cinza**, não por cor — a escala `--text-*` cobre do título ao micro-copy.
8. **Evite:** emoji na UI (use Lucide), gradientes chamativos, sombras coloridas fortes fora do verde da marca, fundos claros.

---

## 7. Tokens (CSS custom properties)

```css
:root {
  /* brand */
  --green-500:#2fc85a; --green-400:#3de86e; --green-300:#7fe6a0;
  --green-200:#8ff0ad; --green-ink:#05230f; --green-deep:#1f7a3d;
  /* backgrounds */
  --bg-900:#0a0b0a; --bg-850:#0b0e0c; --bg-800:#0c0e0c; --bg-750:#0d100e;
  --surface-1:#111412; --surface-2:#141814; --surface-input:#161a17;
  /* text */
  --text-100:#f4f6f2; --text-200:#e8ebe6; --text-300:#d5dad3;
  --text-400:#c3c8c1; --text-500:#a2a8a1; --text-600:#8b918a;
  --text-700:#6f756e; --text-placeholder:#5a615a;
  /* borders */
  --border-subtle:rgba(255,255,255,0.06);
  --border-card:rgba(255,255,255,0.07);
  --border-input:rgba(255,255,255,0.08);
  --border-green:rgba(47,200,90,0.30);
  /* feedback */
  --warn:#e0894f;
  /* radius */
  --r-pill:999px; --r-card:16px; --r-panel:22px;
  /* shadow */
  --shadow-btn:0 12px 34px rgba(47,200,90,0.32);
  --shadow-card:0 24px 70px rgba(0,0,0,0.55);
  /* fonts */
  --font-sans:'Figtree',sans-serif;
  --font-mono:'JetBrains Mono',monospace;
}
```
