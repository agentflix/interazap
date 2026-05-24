<h2>Limite de mensagens atingido</h2>
<p>Olá, <strong>{{ $tenantName }}</strong>!</p>
<p>Sua conta atingiu <strong>100%</strong> do limite mensal de mensagens de IA.</p>
<p>
    Uso atual: <strong>{{ number_format($current) }}</strong> de
    <strong>{{ number_format($limit) }}</strong> mensagens.
</p>
@if($mode === 'stop')
<p>
    <strong>Novas mensagens de IA estão pausadas</strong> até o início do próximo ciclo de cobrança.
    Para retomar, você pode aguardar o próximo ciclo ou alterar seu plano.
</p>
@else
<p>
    Mensagens excedentes estão sendo cobradas a
    <strong>R$ {{ number_format((float) ($overagePrice ?? 0), 4, ',', '.') }}</strong> por mensagem.
    O valor excedente será incluído na sua próxima fatura.
</p>
@endif
<p>Para alterar seu modo de excedente, acesse as configurações de cobrança no painel.</p>
