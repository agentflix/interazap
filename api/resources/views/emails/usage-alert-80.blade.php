<h2>Aviso de uso: 80% do limite de mensagens atingido</h2>
<p>Olá, <strong>{{ $tenantName }}</strong>!</p>
<p>Sua conta atingiu <strong>80%</strong> do limite mensal de mensagens de IA.</p>
<p>
    Uso atual: <strong>{{ number_format($current) }}</strong> de
    <strong>{{ number_format($limit) }}</strong> mensagens.
</p>
@if($mode === 'stop')
<p>
    <strong>Atenção:</strong> quando o limite for atingido, novas mensagens de IA serão pausadas
    até o início do próximo ciclo.
</p>
@else
<p>
    Quando o limite for ultrapassado, mensagens adicionais serão cobradas a
    <strong>R$ {{ number_format((float) ($overagePrice ?? 0), 4, ',', '.') }}</strong> por mensagem.
</p>
@endif
<p>Para alterar seu modo de excedente, acesse as configurações de cobrança no painel.</p>
