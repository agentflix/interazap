<h2>Aviso de bloqueio por inadimplência</h2>
<p>{{ $tenant->name }}, seu acesso poderá ser bloqueado em breve caso o pagamento não seja regularizado.</p>
<p>Fatura em aberto: <strong>R$ {{ number_format((float) ($invoice?->amount ?? 0), 2, ',', '.') }}</strong></p>
@if(!empty($invoice?->payment_url))
<p><a href="{{ $invoice?->payment_url }}">Regularizar pagamento</a></p>
@endif
