# Memory: Build de Executáveis Desktop (Electron) — InteraZap

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão / 📚 Aprendizado |
| **Data** | 2026-04-26 |
| **Autor** | ORCHESTRATOR |
| **Contexto** | Geração inicial dos executáveis `.dmg` (macOS) e `.exe` (Windows) do InteraZap Desktop |
| **Tags** | electron, build, desktop, packaging, icons, pnpm |

---

## Situação

O projeto possui pasta `electron/` (main process, IPC, tray, shortcuts, auto-updater) já estruturada, mas **nunca havia sido empacotada**. Faltavam: ícones reais, integração ao workspace pnpm, pipeline que copiasse o renderer Angular para dentro do pacote, e validação de que o build cross-platform funcionaria a partir de macOS.

Usuário pediu: gerar `.dmg` e `.exe` sem code signing, usando o logo (raio teal) da landing page, via pnpm, com URL local (segurança e velocidade), pelo caminho mais rápido.

---

## Decisão / Aprendizado

### Decisões

1. **Sem code signing** — DMG ad-hoc + EXE não-assinado. Trade-off: avisos de "desenvolvedor não verificado" em ambos OS na primeira execução. Aceitável para distribuição interna/beta.
2. **Renderer Angular embarcado (URL `file://`)** — `main.ts` carrega `../app/browser/index.html` em produção. Mais rápido (sem latência de rede) e mais seguro (sem dependência de servidor remoto, evita MITM em assets).
3. **`electron/` integrado ao pnpm-workspace** — elimina duplicação de `node_modules` e o `package-lock.json` antigo (era npm).
4. **Build do `.exe` direto no macOS via Wine portátil** — electron-builder baixa Wine 4.0.1 automaticamente quando o target é Windows; funcionou de primeira sem precisar instalar Wine no sistema.
5. **Ícones gerados no host (sem ImageMagick)** — usei `sips` + `iconutil` (nativos macOS) para `.icns`, `npx png-to-ico` para `.ico`, `sharp-cli` (via npx) para o PNG-base 1024×1024 a partir do SVG do favicon.

### Aprendizados / Armadilhas

- ⚠️ **`hdiutil detach` falha em retry quando o volume DMG anterior ainda está montado.** O primeiro `--mac` (universal) gerou o arm64.dmg mas falhou no x64.dmg porque um volume residual `/Volumes/InteraZap Desktop 1.0.0` ficou preso. Solução: rodar de novo (volume é descartado entre execuções) — ou desmontar manualmente. Em CI isso não acontece pois cada job parte de máquina limpa.
- ⚠️ **`icon.icns` antigo no repo era PNG renomeado** (`file` mostrava `PNG image data`). electron-builder aceita por sorte, mas o ideal é `.icns` real gerado por `iconutil`.
- 📚 **electron-builder respeita `identity: null`** explicitamente para pular signing macOS sem warnings ruidosos. Apenas `CSC_IDENTITY_AUTO_DISCOVERY=false` não é suficiente quando há `.p12` no keychain.
- 📚 **`compression: maximum`** reduziu o EXE de ~110MB esperados para 81MB (custou ~30s a mais no build).
- ⚠️ **`identity: null` causa erro "danificado" no macOS** (pior que "desenvolvedor não identificado"). Apps sem qualquer assinatura baixados da internet recebem o atributo `com.apple.quarantine` e o macOS exibe "está danificado" sem oferecer bypass via GUI. A solução correta é `identity: "-"` (ad-hoc signing) + `hardenedRuntime: false` + `gatekeeperAssess: false` — isso muda o erro para "desenvolvedor não identificado" que tem bypass via Configurações do Sistema > Privacidade e Segurança > Abrir Mesmo Assim.
- ⚠️ **`CSC_IDENTITY_AUTO_DISCOVERY: false` interfere com ad-hoc signing** — remover esse env var do CI quando usar `identity: "-"`.
- 📚 **Workaround imediato para usuário com DMG já baixado:** `xattr -cr "/Applications/InteraZap Desktop.app"` remove o atributo de quarentena e o app abre sem erro.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Carregar UI a partir de URL hospedada (`https://app.interazap.com`) | Lenta no primeiro load, exige conectividade, expõe app a MITM em ambiente hostil. URL local é mais rápida e segura. |
| Build do `.exe` em GitHub Actions (`windows-latest`) | Mais lento para iteração inicial (push + espera CI). Wine portátil do electron-builder no macOS resolveu localmente. Workflow CI fica como próximo passo para releases automáticos. |
| Instalar `wine-stable` via Homebrew | Desnecessário — electron-builder baixa Wine 4.0.1 portátil sob demanda. |
| Manter `electron/` com npm isolado | Duplicaria `node_modules`, divergiria de versões com `app/`. Workspace pnpm é a convenção do projeto. |
| Code signing imediato (Apple Developer + EV cert Windows) | Custo (~$99/ano Apple + ~$300/ano EV) e tempo de provisão. Adiado para fase de distribuição pública. |
| ImageMagick / `magick convert` para ícones | Não estava instalado. `sips`+`iconutil` (macOS nativo) + `png-to-ico`/`sharp-cli` via npx resolveram sem instalar nada permanente. |

---

## Consequências

### Positivas
- Pipeline reproduzível: `pnpm --filter interazap-desktop electron:build:mac` / `:win` regenera tudo do zero (build Angular → copia renderer → compila main → empacota).
- Identidade visual consistente (raio teal `#14b8a6` da landing está agora no app desktop).
- Build cross-platform sem sair do macOS (gera `.dmg` + `.exe` numa única máquina).
- Tamanhos saudáveis: 81–92 MB cada artefato.

### Negativas / Trade-offs
- **Sem signing**: usuário precisa autorizar manualmente na primeira execução (macOS: botão direito → Abrir; Windows: SmartScreen → Mais informações → Executar mesmo assim).
- **Auto-updater configurado para repo `interazap/interazap-desktop` que pode não existir** — primeira release manual exigirá criar o repo ou ajustar `publish.provider`.
- **Build x64 macOS feito por emulação** (host é arm64 do M-series) — pode ter pequena penalidade de performance vs build nativo Intel; aceitável.
- **Renderer Angular acoplado ao pacote**: cada mudança de UI exige rebuild + reempacotamento. Aceitável dado o requisito de URL local.

---

## Próximos Passos (não executados ainda)

- [ ] Criar workflow `.github/workflows/desktop-release.yml` (matrix `macos-latest` + `windows-latest`) para releases reprodutíveis.
- [ ] Criar repo `interazap/interazap-desktop` (ou ajustar `publish` em `electron-builder.yml`) para auto-updater funcionar.
- [ ] Avaliar code signing quando for distribuir publicamente.
- [ ] Adicionar ícone Linux (`build/agentflix.desktop` ainda referencia nome antigo).

---

## Referências
- `electron/electron-builder.yml`
- `electron/package.json`
- `electron/build/icon.{png,icns,ico}` + `electron/build/icons/`
- `pnpm-workspace.yaml`
- `electron/AUTO-UPDATE.md`
- Artefatos: `electron/release/InteraZap Desktop-1.0.0-{arm64,x64}.dmg` + `InteraZap Desktop Setup 1.0.0.exe`
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-04-26.md`
