<h2>Confirmação de exclusão de dados</h2>
<p>{{ $tenant->name }}, concluímos a exclusão de dados do tenant por inadimplência prolongada.</p>
@if($purgeReport)
<p>Relatório de purge: <strong>{{ $purgeReport->id }}</strong></p>
@endif
<p>Se precisar de suporte, entre em contato com nossa equipe.</p>
