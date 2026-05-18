# InteraZap Desktop

Aplicativo desktop multiplataforma do InteraZap, construído com **Electron 33 + Angular 20**.

O renderer Angular é empacotado estaticamente junto ao executável (modo `file://`) — sem dependência de servidor, carregamento instantâneo e funciona offline.

---

## Pré-requisitos

| Ferramenta | Versão mínima |
|------------|--------------|
| Node.js    | 20+          |
| pnpm       | 9+           |
| macOS (para build `.dmg`) | 10.15+ |
| Windows (para testar o `.exe`) | 10/11 x64 |

> O build do `.exe` pode ser feito **direto no macOS** — o electron-builder baixa automaticamente o Wine 4.0.1 portátil quando necessário. Não é preciso instalar o Wine no sistema.

---

## Instalação

Na raiz do monorepo (instala todos os workspaces, incluindo `electron/`):

```bash
pnpm install
```

---

## Desenvolvimento

```bash
# Terminal 1 — inicia o servidor Angular (hot reload)
cd app
pnpm start

# Terminal 2 — compila o main process e abre a janela Electron
cd electron
pnpm electron:dev
```

A janela carrega `http://localhost:4200` em modo `development`.

---

## Build dos Executáveis

O pipeline `prebuild` é executado automaticamente antes de qualquer build:

```
build:angular → copy:renderer → build:main
```

Ou seja: compila o Angular em produção → copia `app/dist/app-new/browser/` para `electron/app/browser/` → compila o TypeScript do main process.

### macOS — `.dmg`

```bash
# Apple Silicon (arm64)
pnpm --filter interazap-desktop electron:build:mac -- --arm64

# Intel (x64)
pnpm --filter interazap-desktop electron:build:mac -- --x64

# Ambos (arm64 + x64) — recomendado para release
pnpm --filter interazap-desktop electron:build:mac
```

**Artefatos gerados em `electron/release/`:**

```
InteraZap Desktop-1.0.0-arm64.dmg   (~88 MB)
InteraZap Desktop-1.0.0-x64.dmg     (~92 MB)
```

> ⚠️ Sem code signing — na primeira execução, clique com botão direito no `.app` → **Abrir** para contornar o Gatekeeper.

---

### Windows — `.exe` (NSIS installer)

```bash
# Pode ser rodado no macOS ou em máquina Windows
pnpm --filter interazap-desktop electron:build:win
```

**Artefato gerado:**

```
InteraZap Desktop Setup 1.0.0.exe   (~81 MB)
```

O instalador NSIS suporta:
- Escolha de diretório de instalação
- Atalho na área de trabalho
- Atalho no Menu Iniciar

> ⚠️ Sem code signing — o SmartScreen exibirá aviso. Clique em **Mais informações → Executar mesmo assim**.

---

### Linux — `.AppImage` / `.deb` / `.rpm`

```bash
pnpm --filter interazap-desktop electron:build:linux
```

---

### Plataforma atual (atalho)

```bash
pnpm --filter interazap-desktop electron:build
```

---

## Limpeza

```bash
# Remove dist/, app/ (renderer copiado) e release/
pnpm --filter interazap-desktop clean
```

---

## Pipeline de Build (detalhado)

```
pnpm electron:build:mac
  └─ prebuild
       ├─ build:angular   → cd ../app && ng build --configuration production
       ├─ copy:renderer   → copia app/dist/app-new/browser/ → electron/app/browser/
       └─ build:main      → tsc -p tsconfig.electron.json
  └─ electron-builder --mac
       ├─ empacota main process (dist/) + renderer (app/) em ASAR
       ├─ assina (skip — identity: null)
       └─ gera DMG com atalho para Applications
```

---

## Estrutura do Projeto

```
electron/
├── main.ts               # Processo principal — cria BrowserWindow, tray, shortcuts
├── preload.ts            # Context bridge (API segura para o renderer)
├── tsconfig.electron.json
│
├── ipc/                  # Handlers IPC (main process ↔ renderer)
│   ├── window.ts         # Minimizar, maximizar, fechar, título
│   ├── system.ts         # Info de SO, recursos do sistema
│   ├── notifications.ts  # Notificações nativas
│   ├── file-system.ts    # Leitura/escrita de arquivos
│   └── screen-capture.ts # Captura de tela
│
├── services/
│   └── updater.ts        # Auto-update via electron-updater (GitHub Releases)
│
├── tray.ts               # Ícone e menu na bandeja do sistema
├── shortcuts.ts          # Atalhos globais de teclado
│
├── build/                # Assets do build
│   ├── icon.png          # PNG 1024×1024 (raio InteraZap, emerald #3ecf8e)
│   ├── icon.icns         # macOS multi-resolução
│   ├── icon.ico          # Windows multi-resolução (16→256)
│   └── icons/            # Linux (16×16 → 1024×1024)
│
├── dist/                 # TypeScript compilado (gerado automaticamente)
├── app/                  # Renderer Angular copiado (gerado automaticamente)
├── release/              # Artefatos de distribuição (gerado automaticamente)
│
├── electron-builder.yml  # Configuração de build
├── package.json
│
├── AUTO-UPDATE.md        # Como configurar e testar auto-update
├── BUILD-MACOS.md        # Detalhes de build e signing macOS
├── BUILD-WINDOWS.md      # Detalhes de build e signing Windows
├── BUILD-LINUX.md        # Detalhes de build Linux
└── PERFORMANCE.md        # Notas de performance
```

---

## Features

- Custom title bar com controles de janela (macOS: `titleBarStyle: hidden`)
- System tray com menu contextual
- Atalhos globais de teclado
- Captura de tela nativa
- Notificações nativas do OS
- Acesso ao sistema de arquivos
- Auto-update via GitHub Releases (`electron-updater`)
- Modo offline (renderer estático embarcado)

---

## Auto-Update

O auto-update está configurado para o repositório `interazap/interazap-desktop` no GitHub. Em produção (`NODE_ENV=production`), o app verifica atualizações automaticamente ao iniciar.

Para publicar uma nova versão:

```bash
# 1. Atualizar a versão em electron/package.json
# 2. Build para todas as plataformas
pnpm --filter interazap-desktop electron:build:mac
pnpm --filter interazap-desktop electron:build:win

# 3. Criar tag e push
git tag v1.0.0
git push origin v1.0.0

# 4. Criar release no GitHub e anexar os arquivos de electron/release/
```

> Veja [AUTO-UPDATE.md](AUTO-UPDATE.md) para instruções detalhadas.

---

## Próximos Passos

### 🔐 Code Signing (distribuição pública)

Sem assinatura, usuários veem avisos de segurança na primeira execução. Para eliminar:

**macOS:**
```bash
# Requer Apple Developer Program (~$99/ano)
export ELECTRON_MAC_SIGNING_IDENTITY="Developer ID Application: InteraZap (TEAMID)"
export ELECTRON_MAC_NOTARIZATION_APPLE_ID="dev@interazap.com"
export ELECTRON_MAC_NOTARIZATION_APPLE_PASSWORD="app-specific-password"
pnpm --filter interazap-desktop electron:build:mac
```

**Windows:**
```bash
# Requer certificado EV Code Signing (~$300/ano)
export CSC_LINK="path/to/certificate.p12"
export CSC_KEY_PASSWORD="senha-do-cert"
pnpm --filter interazap-desktop electron:build:win
```

---

### 🤖 CI/CD com GitHub Actions

Criar `.github/workflows/desktop-release.yml` com matrix `macos-latest` + `windows-latest` para builds automáticos a cada tag:

```yaml
# Estrutura sugerida (criar o arquivo completo)
on:
  push:
    tags: ['v*']
jobs:
  build:
    strategy:
      matrix:
        os: [macos-latest, windows-latest]
    runs-on: ${{ matrix.os }}
    steps:
      - uses: actions/checkout@v4
      - uses: pnpm/action-setup@v4
      - run: pnpm install
      - run: pnpm --filter interazap-desktop electron:build
      - uses: actions/upload-artifact@v4
        with:
          path: electron/release/*.{dmg,exe}
```

---

### 📦 Criar repositório `interazap/interazap-desktop`

O auto-updater (`electron-builder.yml → publish`) aponta para `github.com/interazap/interazap-desktop`. Esse repositório precisa existir para que o auto-update funcione. Enquanto não existir, o app inicia normalmente mas loga um erro de "update check failed".

---

### 🖥️ Linux — testar e validar

O build Linux (`.AppImage`, `.deb`, `.rpm`) está configurado mas ainda não foi validado. Requer máquina ou container Linux para teste.

---

### 🔔 Deep Links

Suporte a `interazap://` para abrir o app a partir do browser (ex.: links de notificação). Requer registro de protocolo customizado no `main.ts`:

```typescript
app.setAsDefaultProtocolClient('interazap');
```

---

### 🌐 Internacionalização do Installer

Os textos do instalador NSIS (Windows) estão em inglês padrão. Para traduzir para português:

```yaml
# electron-builder.yml
nsis:
  installerLanguages: ["Portuguese"]
  language: 1046  # pt-BR
```

---

## Licença

MIT
