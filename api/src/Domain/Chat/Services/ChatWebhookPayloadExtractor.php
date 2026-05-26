<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

/**
 * Normaliza payloads de webhook para o formato utilizado pelo fluxo de ingestão.
 *
 * Abstrai as diferenças de estrutura entre provedores (Uazapi, Telegram, Meta)
 * e expõe métodos normalizados de extração de dados de mensagem, status,
 * contato e localização.
 *
 * @category Services
 */
final class ChatWebhookPayloadExtractor
{
    /**
     * Extrai dados de atualização de status de mensagem do payload.
     *
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return array{external_id: string|null, status_message: array<string, mixed>|null}
     */
    public function extractStatusMessage(array $payload): array
    {
        $statusPayload = is_array($payload['status'] ?? null) ? $payload['status'] : null;
        $statusMessageId = is_array($statusPayload)
            ? ($statusPayload['messageId'] ?? $statusPayload['message_id'] ?? null)
            : null;
        $statusValue = is_array($statusPayload)
            ? ($statusPayload['status'] ?? null)
            : null;

        if (! $statusMessageId || ! $statusValue) {
            return ['external_id' => null, 'status_message' => null];
        }

        return [
            'external_id' => (string) $statusMessageId,
            'status_message' => [
                'id' => $statusMessageId,
                'status' => $statusValue,
            ],
        ];
    }

    /**
     * Extrai e normaliza os dados principais da mensagem do payload.
     *
     * Resolve campos como external_id, remote_jid, body, tipo, direção,
     * URL de mídia, metadados de contato e localização a partir de diferentes
     * estruturas de payload entre provedores.
     *
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return array<string, mixed> Dados normalizados da mensagem.
     */
    public function extractMessageData(array $payload): array
    {
        $message = is_array($payload['message'] ?? null) ? $payload['message'] : [];
        $externalId = $message['id'] ?? $message['messageid'] ?? null;
        $remoteJid = $message['chatid'] ?? $message['from'] ?? $payload['remote_jid'] ?? null;
        $body = $message['body'] ?? $message['text'] ?? ($message['content']['text'] ?? ($message['caption'] ?? ''));
        $type = $message['type'] ?? $message['mediaType'] ?? $message['messageType'] ?? 'text';
        $direction = $payload['direction'] ?? ((isset($message['fromMe']) && $message['fromMe']) ? 'outgoing' : 'incoming');

        $content = is_array($message['content'] ?? null)
            ? $message['content']
            : (is_array($message['media'] ?? null) ? $message['media'] : []);
        $rawMessage = is_array($payload['raw']['message'] ?? null) ? $payload['raw']['message'] : [];
        $rawContent = is_array($rawMessage['content'] ?? null) ? $rawMessage['content'] : [];
        $fileUrl = $message['mediaUrl']
            ?? $message['fileUrl']
            ?? $message['url']
            ?? ($content['URL'] ?? null)
            ?? ($content['url'] ?? null);

        $mimeType = $message['mimetype'] ?? $message['mimeType'] ?? ($content['mimetype'] ?? null);
        $fileName = $message['fileName'] ?? $message['filename'] ?? ($content['fileName'] ?? ($content['filename'] ?? null));
        $fileSize = $message['fileSize'] ?? $message['size'] ?? ($content['fileLength'] ?? null);

        if (! $fileName && $mimeType) {
            $ext = $this->guessExtensionFromMimeType((string) $mimeType);
            $fileName = ($type === 'audio' || $type === 'ptt') ? "audio{$ext}" : "file{$ext}";
        }

        $messageId = $message['messageid'] ?? $externalId ?? null;
        $instanceToken = $payload['instance_webhook_token'] ?? $payload['token'] ?? null;

        $normalizedType = $this->normalizeType((string) $type);
        $contactMeta = $this->shouldTreatAsContact($message, $content, $rawContent, $rawMessage, $normalizedType)
            ? $this->extractContactMetadata([
                ...$message,
                'rawMessage' => $rawMessage,
            ])
            : null;
        if ($contactMeta !== null) {
            $normalizedType = 'contact';
        }

        $locationMeta = $normalizedType === 'location'
            ? $this->extractLocationMetadata($message)
            : null;

        return [
            'external_id' => $externalId,
            'remote_jid' => $remoteJid,
            'body' => $body,
            'type' => $normalizedType,
            'direction' => $direction,
            'file_url' => $fileUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'file_size' => $fileSize ? (int) $fileSize : null,
            'message_id' => $messageId,
            'instance_token' => $instanceToken,
            'needs_async_download' => (bool) ($messageId && $instanceToken && in_array($normalizedType, ['file', 'audio'], true)),
            'contact_meta' => $contactMeta,
            'location_meta' => $locationMeta,
        ];
    }

    /**
     * Normaliza o tipo de mensagem para o vocabulário interno da plataforma.
     *
     * Exemplos: 'ptt' e 'voice' → 'audio'; 'image' e 'picture' → 'file'.
     */
    private function normalizeType(string $type): string
    {
        if (in_array($type, ['ptt', 'audioMessage', 'voice'], true)) {
            return 'audio';
        }

        if (in_array(strtolower($type), ['video', 'document', 'doc', 'sticker', 'image', 'picture', 'media'], true)) {
            return 'file';
        }

        if (str_contains(strtolower($type), 'location')) {
            return 'location';
        }

        return $type;
    }

    /**
     * Determina se a mensagem deve ser tratada como contato (vCard).
     *
     * Verifica o tipo declarado e a presença de conteúdo de contato em
     * diferentes chaves do payload (content, media, raw).
     *
     * @param  array<string, mixed>  $message  Dados da mensagem.
     * @param  array<string, mixed>  $content  Conteúdo da mensagem.
     * @param  array<string, mixed>  $rawContent  Conteúdo bruto aninhado.
     * @param  array<string, mixed>  $rawMessage  Mensagem bruta do provedor.
     * @param  string  $type  Tipo normalizado da mensagem.
     */
    private function shouldTreatAsContact(array $message, array $content, array $rawContent, array $rawMessage, string $type): bool
    {
        $mediaContent = is_array($message['media'] ?? null) ? $message['media'] : [];
        $hasContactContent = is_array($content['contacts'] ?? null) && ! empty($content['contacts'])
            || is_array($rawContent['contacts'] ?? null) && ! empty($rawContent['contacts'])
            || is_array($mediaContent['contacts'] ?? null) && ! empty($mediaContent['contacts']);
        $rawMsgType = strtolower((string) ($message['type'] ?? ''));
        $rawMediaType = strtolower((string) ($message['mediaType'] ?? ''));
        $rawMessageType = strtolower((string) ($message['messageType'] ?? ''));
        $rawSourceType = strtolower((string) ($rawMessage['type'] ?? ''));
        $rawSourceMediaType = strtolower((string) ($rawMessage['mediaType'] ?? ($rawMessage['media_type'] ?? '')));
        $rawSourceMessageType = strtolower((string) ($rawMessage['messageType'] ?? ($rawMessage['message_type'] ?? '')));
        $contactTypeTokens = ['contactmessage', 'contact', 'contacts', 'contact_array', 'contactsarraymessage'];

        return $type === 'contact'
            || in_array($rawMsgType, $contactTypeTokens, true)
            || in_array($rawMediaType, $contactTypeTokens, true)
            || in_array($rawMessageType, $contactTypeTokens, true)
            || in_array($rawSourceType, $contactTypeTokens, true)
            || in_array($rawSourceMediaType, $contactTypeTokens, true)
            || in_array($rawSourceMessageType, $contactTypeTokens, true)
            || $hasContactContent;
    }

    /**
     * Extrai metadados estruturados de uma mensagem de contato (vCard).
     *
     * Suporta três formatos: vcardJson (array estruturado), contacts (array Meta)
     * e vcard (string vCard raw). Retorna null se nenhum dado de contato for encontrado.
     *
     * @param  array<string, mixed>  $message  Dados da mensagem com chave rawMessage incluída.
     * @return array<string, mixed>|null Metadados normalizados do contato.
     */
    private function extractContactMetadata(array $message): ?array
    {
        $rawMessage = is_array($message['rawMessage'] ?? null) ? $message['rawMessage'] : [];
        $content = is_array($message['content'] ?? null)
            ? $message['content']
            : (is_array($message['media'] ?? null)
                ? $message['media']
                : (is_array($rawMessage['content'] ?? null) ? $rawMessage['content'] : []));

        $vcardJson = $message['vcardJson'] ?? $content['vcardJson'] ?? null;
        if (is_array($vcardJson)) {
            $first = is_array($vcardJson[0] ?? null) ? $vcardJson[0] : $vcardJson;
            $phones = [];
            foreach (($first['phones'] ?? []) as $phone) {
                if (is_array($phone)) {
                    $phones[] = $phone['value'] ?? $phone['number'] ?? '';
                } elseif (is_string($phone)) {
                    $phones[] = $phone;
                }
            }

            return array_filter([
                'fullName' => $first['displayName'] ?? $first['fullName'] ?? $first['fn'] ?? null,
                'phoneNumber' => $phones[0] ?? null,
                'organization' => $first['org'] ?? $first['organization'] ?? null,
                'email' => is_array($first['emails'] ?? null) ? ($first['emails'][0]['value'] ?? $first['emails'][0] ?? null) : ($first['email'] ?? null),
            ]);
        }

        $contacts = $content['contacts'] ?? $message['contacts'] ?? ($rawMessage['contacts'] ?? null);
        if (is_array($contacts) && count($contacts) > 0) {
            $contacts = array_values($contacts);
            $allContacts = [];
            foreach ($contacts as $contact) {
                if (! is_array($contact)) {
                    continue;
                }

                $phones = [];
                if (! empty($contact['phones']) && is_array($contact['phones'])) {
                    foreach ($contact['phones'] as $phone) {
                        if (is_array($phone)) {
                            $phones[] = ['number' => $phone['phone'] ?? $phone['value'] ?? $phone['number'] ?? '', 'type' => $phone['type'] ?? null];
                        }
                    }
                } elseif (isset($contact['vcard']) && is_string($contact['vcard'])) {
                    preg_match_all('/(?:item(\d+)\.)?TEL[^:]*:([^\r\n]+)/i', $contact['vcard'], $telMatches, PREG_SET_ORDER);
                    preg_match_all('/item(\d+)\.X-ABLabel:([^\r\n]+)/i', $contact['vcard'], $labelMatches, PREG_SET_ORDER);
                    $labelMap = [];
                    foreach ($labelMatches as $labelMatch) {
                        $labelMap[(int) $labelMatch[1]] = trim($labelMatch[2]);
                    }
                    foreach ($telMatches as $telMatch) {
                        $itemIndex = (int) $telMatch[1];
                        $phones[] = [
                            'number' => trim($telMatch[2]),
                            'type' => $labelMap[$itemIndex] ?? null,
                        ];
                    }
                }

                $fullName = trim((string) ($contact['name']['formattedName'] ?? $contact['name']['name'] ?? $contact['displayName'] ?? '')) ?: null;

                $allContacts[] = array_filter([
                    'fullName' => $fullName,
                    'phoneNumber' => $phones[0]['number'] ?? null,
                    'phones' => ! empty($phones) ? $phones : null,
                    'organization' => $contact['org']['company'] ?? $contact['organization'] ?? null,
                    'email' => is_array($contact['emails'] ?? null) ? ($contact['emails'][0]['email'] ?? null) : null,
                ]);
            }

            if (count($allContacts) === 1) {
                return $allContacts[0];
            }

            return ['contacts' => $allContacts, ...$allContacts[0]];
        }

        $vcard = $content['vcard'] ?? $message['vcard'] ?? ($rawMessage['vcard'] ?? null);
        if (is_string($vcard) && str_contains($vcard, 'VCARD')) {
            return $this->parseVcard($vcard);
        }

        return null;
    }

    /**
     * Extrai metadados de localização da mensagem.
     *
     * Retorna latitude, longitude, nome e endereço quando disponíveis.
     * Retorna null se as coordenadas não forem encontradas.
     *
     * @param  array<string, mixed>  $message  Dados da mensagem.
     * @return array<string, mixed>|null Metadados de localização ou null.
     */
    private function extractLocationMetadata(array $message): ?array
    {
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];

        $lat = $content['degreesLatitude'] ?? $content['latitude'] ?? $message['latitude'] ?? null;
        $lng = $content['degreesLongitude'] ?? $content['longitude'] ?? $message['longitude'] ?? null;

        if ($lat === null && $lng === null) {
            return null;
        }

        return array_filter([
            'latitude' => is_numeric($lat) ? (float) $lat : null,
            'longitude' => is_numeric($lng) ? (float) $lng : null,
            'name' => $content['name'] ?? $message['locationName'] ?? null,
            'address' => $content['address'] ?? $message['address'] ?? null,
        ]);
    }

    /**
     * Faz parse de uma string vCard para extração de nome, telefone, organização e email.
     *
     * Retorna null se não houver ao menos nome ou telefone.
     *
     * @param  string  $vcard  String vCard no formato padrão.
     * @return array<string, mixed>|null Dados extraídos do vCard.
     */
    private function parseVcard(string $vcard): ?array
    {
        $name = null;
        $phone = null;
        $org = null;
        $email = null;

        if (preg_match('/FN[;:](.+)/i', $vcard, $match)) {
            $name = trim($match[1]);
        }
        if (preg_match('/TEL[^:]*:(.+)/i', $vcard, $match)) {
            $phone = trim($match[1]);
        }
        if (preg_match('/ORG[;:](.+)/i', $vcard, $match)) {
            $org = trim($match[1]);
        }
        if (preg_match('/EMAIL[^:]*:(.+)/i', $vcard, $match)) {
            $email = trim($match[1]);
        }

        if (! $name && ! $phone) {
            return null;
        }

        return array_filter([
            'fullName' => $name,
            'phoneNumber' => $phone,
            'organization' => $org,
            'email' => $email,
        ]);
    }

    /**
     * Infere a extensão de arquivo a partir do MIME type.
     *
     * Retorna string vazia se o MIME type não for reconhecido.
     *
     * @param  string  $mimeType  MIME type do arquivo.
     * @return string Extensão com ponto (ex.: '.jpg') ou string vazia.
     */
    private function guessExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'video/mp4' => '.mp4',
            'video/3gpp' => '.3gp',
            'audio/ogg' => '.ogg',
            'audio/mpeg' => '.mp3',
            'audio/mp4' => '.m4a',
            'audio/opus' => '.opus',
            'audio/webm' => '.webm',
            'application/pdf' => '.pdf',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'text/plain' => '.txt',
        ];

        return $map[$mimeType] ?? '';
    }
}
