# Screenshots — Android

Pasta destinada aos screenshots do InteraZap para a Google Play Store.

## Formatos

| Formato               | Resolução mínima    | Status      |
| --------------------- | ------------------- | ----------- |
| Phone (portrait)      | 320×568 a 3840×2160 | ⏳ Pendente |
| 7" Tablet — opcional  | 1024×600 mín        | ⏳ Opcional |
| 10" Tablet — opcional | 1280×800 mín        | ⏳ Opcional |

> Google Play aceita qualquer proporção entre 9:16 e 16:9. Recomendado: 1080×1920 (9:16 Full HD).

## Telas a capturar

1. Caixa de entrada (inbox com lista de conversas)
2. Conversa de chat aberta
3. IA Autopilot em ação
4. Painel CRM do contato
5. Notificação push recebida

## Nomenclatura

```
{numero}-{tela}-{resolucao}.png
Ex: 01-inbox-1080x1920.png
```

## Como capturar

```bash
# Via Android emulator (adb)
adb shell screencap -p /sdcard/screenshot.png
adb pull /sdcard/screenshot.png ./01-inbox-1080x1920.png

# Ou via Android Studio → Logcat → Screenshot button
```
