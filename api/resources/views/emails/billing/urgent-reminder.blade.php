<h2>Pagamento pendente — ação necessária</h2>
<p>{{ $tenant->name }}, sua fatura está em atraso há <strong>{{ $daysOverdue }} dias</strong>.</p>
<p>Valor: <strong>R$ {{ number_format((float) ($invoice?->amount ?? 0), 2, ',', '.') }}</strong></p>
<p>Vencimento: <strong>{{ $invoice?->due_date?->format('d/m/Y') }}</strong></p>
<p>Regularize para evitar bloqueio temporário da conta.</p>
@if(!empty($invoice?->payment_url))
<p><a href="{{ $invoice?->payment_url }}">Pagar fatura</a></p>
@endif
