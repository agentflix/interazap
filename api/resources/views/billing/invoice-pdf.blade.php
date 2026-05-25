<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #1a1a1a; background: #fff; }
  .page { padding: 40px; }
  .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 2px solid #e5e7eb; padding-bottom: 20px; }
  .brand { font-size: 22px; font-weight: 700; color: #111827; }
  .brand span { color: #6366f1; }
  .invoice-title { text-align: right; }
  .invoice-title h1 { font-size: 18px; font-weight: 700; color: #111827; }
  .invoice-title .id { font-size: 11px; color: #6b7280; margin-top: 4px; }
  .section { margin-bottom: 24px; }
  .section-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; margin-bottom: 10px; }
  .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .field label { display: block; font-size: 10px; color: #6b7280; margin-bottom: 2px; }
  .field p { font-size: 12px; color: #111827; font-weight: 500; }
  .amount-box { background: #f3f4f6; border-radius: 8px; padding: 20px; text-align: center; margin: 24px 0; }
  .amount-box .label { font-size: 11px; color: #6b7280; margin-bottom: 6px; }
  .amount-box .value { font-size: 28px; font-weight: 700; color: #111827; }
  .status-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
  .status-draft    { background: #fef9c3; color: #854d0e; }
  .status-pending  { background: #dbeafe; color: #1e40af; }
  .status-paid     { background: #dcfce7; color: #166534; }
  .status-overdue  { background: #fee2e2; color: #991b1b; }
  .status-cancelled{ background: #f3f4f6; color: #6b7280; }
  .footer { margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 16px; text-align: center; font-size: 10px; color: #9ca3af; }
</style>
</head>
<body>
<div class="page">

  <div class="header">
    <div class="brand">Intera<span>Zap</span></div>
    <div class="invoice-title">
      <h1>FATURA</h1>
      <div class="id">ID: {{ strtoupper(substr($invoice->id, 0, 8)) }}</div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Empresa</div>
    <div class="grid-2">
      <div class="field">
        <label>Razão Social</label>
        <p>{{ $invoice->tenant?->name ?? '—' }}</p>
      </div>
      <div class="field">
        <label>Código do Tenant</label>
        <p>{{ $invoice->tenant?->code ?? '—' }}</p>
      </div>
    </div>
  </div>

  <div class="section">
    <div class="section-title">Dados da Fatura</div>
    <div class="grid-2">
      <div class="field">
        <label>Mês de Referência</label>
        <p>{{ $invoice->reference_month }}</p>
      </div>
      <div class="field">
        <label>Plano</label>
        <p>{{ $invoice->plan?->name ?? '—' }}</p>
      </div>
      <div class="field">
        <label>Status</label>
        <p>
          <span class="status-badge status-{{ $invoice->status->value }}">
            {{ $invoice->status->label() }}
          </span>
        </p>
      </div>
      <div class="field">
        <label>Método de Pagamento</label>
        <p>{{ $invoice->payment_method ? ucfirst($invoice->payment_method) : '—' }}</p>
      </div>
      <div class="field">
        <label>Vencimento</label>
        <p>{{ $invoice->due_date?->format('d/m/Y') ?? '—' }}</p>
      </div>
      <div class="field">
        <label>Pago em</label>
        <p>{{ $invoice->paid_at?->format('d/m/Y H:i') ?? '—' }}</p>
      </div>
    </div>
  </div>

  <div class="amount-box">
    <div class="label">Valor Total</div>
    <div class="value">R$ {{ number_format((float) $invoice->amount, 2, ',', '.') }}</div>
  </div>

  <div class="footer">
    Gerado em {{ now()->format('d/m/Y \à\s H:i') }} &nbsp;·&nbsp; InteraZap — Sistema de Gestão de Comunicação
  </div>

</div>
</body>
</html>
