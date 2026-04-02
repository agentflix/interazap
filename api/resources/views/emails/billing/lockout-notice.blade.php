<h2>Conta bloqueada por inadimplência</h2>
<p>{{ $tenant->name }}, sua conta foi bloqueada temporariamente devido ao atraso no pagamento.</p>
<p>Após a quitação da fatura, o acesso será restabelecido automaticamente.</p>
@if(!empty($invoice?->payment_url))
<p><a href="{{ $invoice?->payment_url }}">Pagar agora</a></p>
@endif
@if(!empty($purgeDeadline))
<p>Prazo limite antes da exclusão de dados: <strong>{{ \Illuminate\Support\Carbon::parse($purgeDeadline)->format('d/m/Y') }}</strong></p>
@endif
