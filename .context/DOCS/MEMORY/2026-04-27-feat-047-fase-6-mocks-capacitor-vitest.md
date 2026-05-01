# Memory: FEAT-047 Fase 6 — Estratégia de mocks para plugins Capacitor em Vitest

## Metadados

| Campo        | Valor                                                                  |
| ------------ | ---------------------------------------------------------------------- |
| **Tipo**     | 📚 Aprendizado                                                         |
| **Data**     | 2026-04-27                                                             |
| **Autor**    | FRONTEND (Copilot)                                                     |
| **Contexto** | FEAT-047 / TASK-047.12 / TASK-047.13 / testes de escopo em app Angular |
| **Tags**     | feat-047, capacitor, vitest, mobile, testes                            |

---

## Situação

> O que estava acontecendo? Qual o contexto?

Durante a validação da Fase 6, os testes de escopo falharam em runtime ao usar `vi.spyOn` diretamente em objetos de plugins Capacitor (`Camera.requestPermissions`, `Preferences.get`). No ambiente de teste compilado, essas propriedades não estavam definidas no objeto importado, gerando erro: `The property "..." is not defined on the object.`

---

## Decisão / Aprendizado

> O que foi decidido ou aprendido?

Para esse contexto, a abordagem estável foi mockar os métodos de serviço que encapsulam os plugins (wrappers internos) em vez de mockar o objeto do plugin diretamente.

- `OfflineQueueService`: mockar `readFromNativeStorage` e `writeToNativeStorage`
- `NativeBridgeService`: mockar `requestCameraPermissions`, `getPhoto` e `resolvePhotoBlob`

Isso preserva o contrato funcional do serviço e elimina dependência da forma como o bundler expõe os objetos nativos no runtime de testes.

---

## Alternativas Consideradas

> O que foi descartado e por quê?

| Alternativa                                                  | Por que descartada                                                               |
| ------------------------------------------------------------ | -------------------------------------------------------------------------------- |
| Mockar diretamente `Camera` e `Preferences` com `vi.spyOn`   | Falhou em runtime no builder de testes (`property is not defined`)               |
| Reescrever testes para usar apenas stubs globais de `window` | Aumenta acoplamento com detalhes de ambiente e perde foco no contrato de serviço |
| Ignorar falhas e aceitar only-gate global                    | Invalidaria evidência de escopo da fase                                          |

---

## Consequências

> O que muda por causa disso?

### Positivas

- Testes de escopo da Fase 6 ficaram estáveis e reproduzíveis
- Menor acoplamento dos specs com implementação dos plugins
- Falhas passam a refletir regressão real dos serviços, não do runtime de mocks

### Negativas / Trade-offs

- Testes dependem de wrappers internos do serviço
- Exige manter esses wrappers como pontos estáveis de integração

---

## Referências

- Task: `.context/DOCS/TASKS/FEAT-047-tasks.md`
- Changelog: `.context/DOCS/CHANGELOG/2026-04-27.md`
- Specs: `app/src/app/core/services/platform/native-bridge.service.spec.ts`, `app/src/app/core/services/platform/offline-queue.service.spec.ts`
