<!-- ════════════════════════════════════════════════════════════════════════
     MOCKUP: Formulário de Produto com Controle de Estoque
     Padrão: switch como toggle principal, campos condicionais indentados
     Referência: chat-configuration.html (auto-fechamento por inatividade)
     ════════════════════════════════════════════════════════════════════════ -->

<form [formGroup]="form">

  <!-- Campos fixos (sempre visíveis) -->
  <af-text-input
    label="Nome"
    [control]="form.controls.name"
    placeholder="Ex: Consultoria Empresarial"
    [required]="true"
  />

  <div class="grid grid-cols-2 gap-4">
    <af-text-input
      label="Código"
      [control]="form.controls.code"
      placeholder="Ex: PROD001"
    />
    <af-select-input
      label="Tipo"
      [control]="form.controls.type"
      [options]="typeOptions"
      [required]="true"
    />
  </div>

  <af-textarea-input
    label="Descrição"
    [control]="form.controls.description"
    placeholder="Descrição do produto ou serviço..."
    [rows]="2"
  />

  <div class="grid grid-cols-2 gap-4">
    <af-currency-input label="Preço" [control]="form.controls.price" placeholder="0,00" />
    <af-currency-input label="Custo" [control]="form.controls.cost" placeholder="0,00" />
  </div>

  <!-- ════════════════════════════════════════════════════════════════════════
       SEÇÃO DE ESTOQUE — aparece apenas para produtos
       ════════════════════════════════════════════════════════════════════════ -->
  @if (isProduct()) {

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
         TOGGLE PRINCIPAL: Controlar estoque
         ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    <div class="mt-4 mb-2">
      <af-switch-input
        label="Controlar estoque"
        [control]="form.controls.track_stock"
        helpText="Quando ativo, o sistema debita do estoque ao vincular este produto a uma negociação. Quando inativo, o produto é considerado ilimitado."
      />
    </div>

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
         CAMPOS CONDICIONAIS — indentados com borda lateral
         Aparecem apenas quando track_stock === true
         ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    @if (form.controls.track_stock.value) {
      <div class="flex flex-col gap-4 pl-6 border-l-2 border-accent-500/30">

        <div class="grid grid-cols-3 gap-4">
          <af-text-input
            label="Unidade"
            [control]="form.controls.unit"
            placeholder="Ex: un, kg"
          />
          <af-text-input
            label="Quantidade em estoque"
            [control]="form.controls.stock_quantity"
            type="number"
            placeholder="0"
            helpText="Quantidade disponível no momento."
          />
          <af-text-input
            label="Estoque mínimo"
            [control]="form.controls.min_stock"
            type="number"
            placeholder="0"
            helpText="Alerta quando o estoque estiver abaixo deste valor."
          />
        </div>

        <!-- Alerta de estoque baixo -->
        @if (isLowStock()) {
          <div class="rounded-md bg-amber-50 dark:bg-amber-950/30 px-3 py-2">
            <p class="text-xs text-amber-700 dark:text-amber-300">
              Atenção: estoque atual está abaixo do mínimo configurado.
            </p>
          </div>
        }

      </div>
    }

    <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
         ESTADO INATIVO — feedback visual quando track_stock === false
         ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
    @if (!form.controls.track_stock.value) {
      <div class="pl-6 border-l-2 border-neutral-200 dark:border-neutral-700">
        <div class="rounded-md bg-neutral-50 dark:bg-neutral-800/50 px-3 py-2">
          <p class="text-xs text-neutral-500 dark:text-neutral-400">
            Este produto está configurado como <strong>ilimitado</strong>. Não há controle de estoque — pode ser adicionado a qualquer negociação sem restrição de quantidade.
          </p>
        </div>
      </div>
    }

  }

  <!-- ════════════════════════════════════════════════════════════════════════
       STATUS DO PRODUTO
       ════════════════════════════════════════════════════════════════════════ -->
  <div class="flex items-center gap-4 mt-6 mb-2">
    <af-switch-input
      label="Ativo"
      [control]="form.controls.is_active"
    />
    <af-checkbox-input
      [control]="form.controls.is_featured"
      label="Destaque"
    />
  </div>

</form>

<!-- ════════════════════════════════════════════════════════════════════════
     COMPARAÇÃO VISUAL: Antes vs Depois
     ════════════════════════════════════════════════════════════════════════ -->

<!--
┌─────────────────────────────────────────────────────────────────────────────┐
│  ANTES (feio)                                                               │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─ Nome ─────────────────────────┐                                         │
│  ├─ Código ────┬─ Tipo ──────────┤                                         │
│  ├─ Descrição ────────────────────┤                                         │
│  ├─ Preço ─────┬─ Custo ─────────┤                                         │
│  ├─ Unidade ───┬─ Estoque ───────┬─ Est. Mín. ─┐  ← campos soltos, sem    │
│  └──────────────────────────────────────────────┘    relação com o toggle  │
│                                                                             │
│  [●] Ativo  [ ] Destaque                                                  │
│                                                                             │
│  [●] Controlar estoque   ← switch solto no final, sem contexto visual      │
│                                                                             │
│  Problema: usuário não entende que "Estoque" e "Estoque Mín." só fazem     │
│  sentido quando "Controlar estoque" está ligado.                           │
└─────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│  DEPOIS (mockup acima)                                                      │
├─────────────────────────────────────────────────────────────────────────────┤
│  ┌─ Nome ─────────────────────────┐                                         │
│  ├─ Código ────┬─ Tipo ──────────┤                                         │
│  ├─ Descrição ────────────────────┤                                         │
│  ├─ Preço ─────┬─ Custo ─────────┤                                         │
│                                                                             │
│  [●] Controlar estoque                                                    │
│  │   Quando ativo, o sistema debita do estoque ao vincular...             │
│  │                                                                         │
│  ├──┬─ Unidade ──┬─ Quantidade em estoque ─┬─ Estoque mínimo ─┐           │
│  │  └────────────┴─────────────────────────┴───────────────────┘           │
│  │                                                                         │
│  │  ┌──────────────────────────────────────────────────────────┐           │
│  │  │  Este produto está configurado como ilimitado...         │  ←       │
│  │  └──────────────────────────────────────────────────────────┘     ou   │
│  │                                                                         │
│  └──┬─ Unidade ──┬─ Quantidade em estoque ─┬─ Estoque mínimo ─┐  ←        │
│     └────────────┴─────────────────────────┴───────────────────┘     borda │
│                                                                         │
│  [●] Ativo  [ ] Destaque                                                  │
│                                                                             │
│  Melhorias:                                                                 │
│  1. Switch "Controlar estoque" vem ANTES dos campos relacionados           │
│  2. Campos de estoque aparecem apenas quando o switch está ativo           │
│  3. Indentação com borda lateral (border-l-2) cria hierarquia visual       │
│  4. Quando inativo, mostra feedback explicativo "produto ilimitado"        │
│  5. Unidade continua visível? NÃO — unidade faz parte do estoque também    │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
-->

<!-- ════════════════════════════════════════════════════════════════════════
     DECISÕES DE UX
     ════════════════════════════════════════════════════════════════════════ -->

<!--
1. UNIDADE: onde ficar?
   Opção A: Dentro do bloco condicional (como no mockup acima)
            → Faz sentido porque unidade só é relevante para produtos físicos
   Opção B: Fora, sempre visível para produtos
            → Faz sentido se o usuário quiser cadastrar "un" mesmo sem controle

   RECOMENDAÇÃO: Opção A. Se o produto é ilimitado, a unidade é meramente
   informativa na proposta. Se controla estoque, a unidade é operacional.

2. SERVIÇOS: o switch aparece?
   NÃO. Serviços nunca controlam estoque. O bloco inteiro de estoque só
   aparece quando type === 'product'.

3. ESTOQUE INICIAL ao criar produto:
   Quando track_stock = true e stock_quantity = null, sugerir 0 como default
   no campo para evitar confusão.

4. FEEDBACK VISUAL quando inativo:
   O banner "Este produto está configurado como ilimitado" é importante para
   o usuário entender que a ausência de campos de estoque é INTENCIONAL,
   não um bug.
-->
