# Regras Laravel: Validação

Use estas regras antes de concluir qualquer task backend.

## Gates

Rodar no workspace `api/`:

```bash
composer gate:all
```

O atalho cobre formatação, análise estática, testes e Rector. Se precisar isolar uma falha:

```bash
composer format
composer analyse
composer test
composer refactor
```

## Criterios

- Pint sem violações.
- PHPStan/Larastan sem erros.
- Pest sem falhas e sem testes pulados.
- Rector sem sugestoes pendentes ou com justificativa clara.
- Migrations avaliadas com `php artisan migrate --pretend` quando houver schema.

## Confirm PREVC

- Registrar evidência de validação na resposta final.
- Criar entrada em `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`.
- Criar MEMORY quando houver decisão relevante, armadilha ou padrão novo.
- Atualizar `.context/ARCHITECTURE/project-state.yaml` quando a task fechar feature ou alterar estado do projeto.
