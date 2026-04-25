# 2026-04-22 — Chat media type fallback (PDF)

## Context

Bug report: uploaded attachments (including PDF) were being treated as image in outbound media type resolution.

## Root cause

`SendChatMessageAction::resolveMediaType()` and `ChatMessageGatewayDispatcher::resolveMediaType()` defaulted to `image` when message `type` was not explicit and `file_url` was empty, even when `mime_type` indicated document-like media.

## Fix

- Normalize MIME with lowercase.
- Classify `application/*` and `text/*` as `document` before fallback.
- Keep existing explicit media-type precedence (`image`, `video`, `audio`, `document`, `sticker`).

## Verification

Regression test added:

- `tests/Unit/Chat/SendMessageActionTest.php`
- `it_resolves_pdf_mime_as_document_even_without_file_url`

Test command executed:

- `cd /Users/rafael.silva/Documents/interazap/api && ./vendor/bin/pest tests/Unit/Chat/SendMessageActionTest.php --filter='it_resolves_pdf_mime_as_document_even_without_file_url'`
- Result: passed (exit 0)
