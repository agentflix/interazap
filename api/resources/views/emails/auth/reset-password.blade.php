<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinição de Senha</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f5f9;padding:40px 16px;">
    <tr>
        <td align="center">
            <table width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">

                {{-- Logo --}}
                <tr>
                    <td align="center" style="padding-bottom:24px;">
                        <img src="{{ asset('images/logo-interazap.png') }}"
                             alt="{{ $appName }}"
                             height="36"
                             style="height:36px;width:auto;display:block;" />
                    </td>
                </tr>

                {{-- Card --}}
                <tr>
                    <td style="background-color:#ffffff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,0.08);overflow:hidden;">

                        {{-- Top accent bar — emerald --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background:linear-gradient(90deg,#3ecf8e 0%,#24b47e 100%);height:5px;font-size:0;line-height:0;">&nbsp;</td>
                            </tr>
                        </table>

                        {{-- Body --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding:40px 40px 32px;">

                                    {{-- Icon --}}
                                    <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td align="center" style="background-color:#eefbf5;border-radius:50%;width:56px;height:56px;text-align:center;vertical-align:middle;">
                                                <span style="font-size:28px;line-height:56px;display:block;">🔐</span>
                                            </td>
                                        </tr>
                                    </table>

                                    <h1 style="margin:0 0 8px;font-size:22px;font-weight:700;color:#111827;">
                                        Redefinição de Senha
                                    </h1>
                                    <p style="margin:0 0 24px;font-size:15px;color:#6b7280;line-height:1.6;">
                                        Recebemos uma solicitação para redefinir a senha da sua conta.
                                        Clique no botão abaixo para criar uma nova senha.
                                    </p>

                                    {{-- CTA Button — dark text on emerald (btn-primary pattern) --}}
                                    <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="border-radius:8px;background-color:#3ecf8e;">
                                                <a href="{{ $url }}"
                                                   style="display:inline-block;padding:14px 32px;color:#111827;font-size:15px;font-weight:700;text-decoration:none;border-radius:8px;letter-spacing:0.2px;">
                                                    Redefinir Senha
                                                </a>
                                            </td>
                                        </tr>
                                    </table>

                                    {{-- Expiry notice --}}
                                    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
                                        <tr>
                                            <td style="background-color:#fef9c3;border-left:4px solid #eab308;border-radius:6px;padding:12px 16px;">
                                                <p style="margin:0;font-size:13px;color:#713f12;">
                                                    ⏱ Este link expirará em <strong>{{ $expireMinutes }} minutos</strong>.
                                                </p>
                                            </td>
                                        </tr>
                                    </table>

                                    {{-- Not requested --}}
                                    <p style="margin:0;font-size:13px;color:#9ca3af;line-height:1.6;">
                                        Se você não solicitou a redefinição de senha, ignore este e-mail —
                                        nenhuma alteração será feita na sua conta.
                                    </p>

                                </td>
                            </tr>
                        </table>

                        {{-- Fallback URL --}}
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="background-color:#f8fafc;border-top:1px solid #e5e7eb;padding:16px 40px;">
                                    <p style="margin:0;font-size:11px;color:#9ca3af;line-height:1.6;">
                                        Se o botão não funcionar, copie e cole o link abaixo no seu navegador:
                                    </p>
                                    <p style="margin:4px 0 0;font-size:11px;word-break:break-all;">
                                        <a href="{{ $url }}" style="color:#1a9466;text-decoration:none;">{{ $url }}</a>
                                    </p>
                                </td>
                            </tr>
                        </table>

                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td align="center" style="padding-top:24px;">
                        <p style="margin:0;font-size:12px;color:#9ca3af;">
                            &copy; {{ date('Y') }} {{ $appName }}. Todos os direitos reservados.
                        </p>
                        <p style="margin:4px 0 0;font-size:12px;color:#d1d5db;">
                            Este é um e-mail automático. Por favor, não responda.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
