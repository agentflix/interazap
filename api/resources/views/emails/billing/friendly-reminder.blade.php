<h2>Olá, {{ $tenant->name }}!</h2>
<p>Identificamos uma fatura em aberto no valor de <strong>R$ {{ number_format((float) ($invoice?->amount ?? 0), 2, ',', '.') }}</strong>.</p>
<p>Vencimento: <strong>{{ $invoice?->due_date?->format('d/m/Y') }}</strong>.</p>
<p>Para evitar bloqueio de acesso, regularize o pagamento o quanto antes.</p>
@if(!empty($invoice?->payment_url))
<p><a href="{{ $invoice?->payment_url }}">Clique aqui para pagar agora</a></p>
@endif
