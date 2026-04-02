<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $notification->title }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5; margin: 0; padding: 24px;">
<div style="max-width: 640px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; padding: 24px;">
    <h2 style="margin-top: 0; color: #111827;">{{ $notification->title }}</h2>
    <p style="margin-bottom: 16px;">{{ $notification->body }}</p>

    @if(is_array($notification->data) && isset($notification->data['link']) && is_string($notification->data['link']))
        <p style="margin-bottom: 0;">
            <a href="{{ $notification->data['link'] }}" style="color: #2563eb;">Abrir notificação</a>
        </p>
    @endif
</div>
</body>
</html>
