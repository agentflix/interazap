# 7 Revisores do Code Review Confiável

Abra os 7 subagents em paralelo quando a invocação autorizar subagents. Cada agente deve ler somente o contexto necessário, revisar o diff inteiro dentro do seu escopo e retornar achados com evidência.

Se a política/ferramenta exigir autorização explícita para subagents e o pedido não trouxer essa autorização, peça confirmação curta antes de abrir os revisores.

## 1. Especialização

**Escopo:** arquitetura e regras do workspace alterado.

**Prompt base:**

> Revise o diff como especialista do workspace afetado. Carregue a skill obrigatória correspondente (`laravel-especialista`, `nestjs-especialista` ou `angular-especialista`) e aponte apenas violações reais de arquitetura, padrão do repositório, multi-tenancy, contratos ou segurança. Inclua arquivo/linha, evidência e correção sugerida.

## 2. Grounding

**Escopo:** aderência a documentos, skills, agents e contexto do projeto.

**Prompt base:**

> Revise se a entrega respeita `AGENTS.md`, `.context/SKILLS/`, `.context/AGENTS/` e documentação relevante. Não use conhecimento genérico quando houver regra local. Aponte divergências verificáveis entre diff e fonte da verdade.

## 3. Second Pass

**Escopo:** releitura integral do diff e omissões do review.

**Prompt base:**

> Releia o diff inteiro. Liste áreas revisadas que estão limpas e aponte somente problemas com evidência. Procure bugs silenciosos, caminhos não testados, estados de erro, regressões e mudanças incompletas.

## 4. Precision

**Escopo:** reduzir falsos positivos.

**Prompt base:**

> Revise os possíveis achados com limiar alto: reporte apenas se houver confiança >= 80%, impacto real e evidência. Classifique incertezas como perguntas, não como bugs. Remova estilo subjetivo, preferência pessoal e suposição sem prova.

## 5. Human-in-the-Loop

**Escopo:** decisão humana e próximos passos.

**Prompt base:**

> Avalie quais achados exigem decisão humana, contexto de produto ou trade-off. Não aprove nem rejeite a entrega. Indique perguntas objetivas para o autor quando uma decisão depender de requisito não documentado.

## 6. Rastreabilidade

**Escopo:** vínculo entre diff, task, Linear, PRD, T.A.C.E e evidências.

**Prompt base:**

> Verifique se a entrega bate com task/issue/documentação funcional, critérios de aceite e arquivos alterados. Confirme que testes/gates cobrem o comportamento. Aponte lacunas de rastreabilidade, documentação ou evidência.

## 7. Meta-review

**Escopo:** revisar o review.

**Prompt base:**

> Revise os achados dos outros agentes. Procure hallucinations, imports inexistentes, dead code, assinaturas erradas, duplicidade, falso positivo e achados sem prova. Mantenha apenas comentários acionáveis e verificáveis.

## Saída de Cada Subagent

Cada subagent deve retornar:

- Achados por severidade: `Crítico`, `Alto`, `Médio`, `Baixo`.
- Evidência: arquivo/linha, trecho do diff, teste/gate, regra ou contrato.
- Correção sugerida.
- Itens verificados sem achados relevantes.
- Confiança: percentual aproximado.
