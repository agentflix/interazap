<h2>Último aviso antes da exclusão de dados</h2>
<p>{{ $tenant->name }}, seu tenant está em fase final de inadimplência.</p>
@if(!empty($purgeDeadline))
<p>Data limite para regularização: <strong>{{ \Illuminate\Support\Carbon::parse($purgeDeadline)->format('d/m/Y') }}</strong>.</p>
@endif
<p>Após esse prazo, seus dados poderão ser excluídos conforme política LGPD.</p>
@if(!empty($invoice?->payment_url))
<p><a href="{{ $invoice?->payment_url }}">Quitar pendência</a></p>
@endif
