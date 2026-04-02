import {
  BadRequestException,
  Body,
  Controller,
  Headers,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { UazapiClient } from '../providers/uazapi/uazapi.client';
import { SendTextDto } from '../dto/send-text.dto';
import { SendFileDto } from '../dto/send-file.dto';
import { SendPresenceDto } from '../dto/send-presence.dto';
import { DownloadMediaDto } from '../dto/download-media.dto';

/**
 * UazapiMessagesController
 *
 * Gerencia envio de mensagens de texto, mídia e arquivos
 * via provider Uazapi, incluindo normalização de payload.
 */
@Controller({ path: 'send', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiMessagesController {
  /**
   * Initializes the messages controller with the Uazapi client.
   *
   * @param client - Uazapi HTTP client for message sending operations
   */
  constructor(private readonly client: UazapiClient) {}

  /**
   * Sends a text message via Uazapi.
   *
   * @param token - Instance token
   * @param body - Text message payload
   * @returns Send result
   */
  @Post('text')
  sendText(@Headers('token') token: string, @Body() body: SendTextDto) {
    return this.client.sendText(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Sends a media message via Uazapi.
   *
   * @param token - Instance token
   * @param body - Media message payload
   * @returns Send result
   */
  @Post('media')
  sendMedia(@Headers('token') token: string, @Body() body: SendFileDto) {
    return this.forwardSendFile(token, body);
  }

  /**
   * Sends a file message via Uazapi.
   *
   * @param token - Instance token
   * @param body - File message payload
   * @returns Send result
   */
  @Post('file')
  sendFile(@Headers('token') token: string, @Body() body: SendFileDto) {
    return this.forwardSendFile(token, body);
  }

  /**
   * Forwards file payload to Uazapi with normalized field names.
   * Remaps API field names (url, caption, fileName) to Uazapi expectations
   * (file, text, docName) and enforces the file requirement.
   *
   * @param token - Instance token
   * @param body - File payload
   * @returns Normalized send result
   */
  private forwardSendFile(token: string, body: SendFileDto) {
    // Uazapi expects 'file' field (URL or base64)
    const file = body.file ?? body.url;

    if (!file) {
      throw new BadRequestException('url or file is required');
    }

    const payload: Record<string, unknown> = {
      ...body,
      file,
    };

    // Remove 'url' alias — Uazapi uses 'file'
    delete payload.url;

    // Uazapi uses 'text' for caption, not 'caption'
    if (payload.caption && !payload.text) {
      payload.text = payload.caption;
    }
    delete payload.caption;

    // Normalize document filename: API sends 'fileName', Uazapi expects 'docName'
    if (payload.fileName && !payload.docName) {
      payload.docName = payload.fileName;
    }
    delete payload.fileName;

    this.normalizeUnsupportedImagePayload(payload, file);

    return this.client.sendFile(token, payload);
  }

  /**
   * Normalizes unsupported image MIME types to document type.
   * WebP, SVG, HEIC, HEIF, and AVIF images are sent as documents
   * because Uazapi does not support them natively.
   *
   * @param payload - Normalized payload to adjust
   * @param file - File URL or base64 string
   */
  private normalizeUnsupportedImagePayload(
    payload: Record<string, unknown>,
    file: string,
  ): void {
    const type =
      typeof payload.type === 'string' ? payload.type.toLowerCase() : null;

    if (type !== 'image') {
      return;
    }

    const mimeType = this.resolveMimeType(payload, file);
    if (!mimeType) {
      return;
    }

    const supportedImageMimeTypes = new Set([
      'image/jpeg',
      'image/jpg',
      'image/png',
      'image/gif',
    ]);

    if (supportedImageMimeTypes.has(mimeType)) {
      return;
    }

    payload.type = 'document';
  }

  /**
   * Resolves the MIME type from the payload mimetype field,
   * from a data URI prefix, or by inferring from the file URL extension.
   *
   * @param payload - Payload with optional mimetype field
   * @param file - File URL or base64 string
   * @returns Resolved MIME type or null if not determinable
   */
  private resolveMimeType(
    payload: Record<string, unknown>,
    file: string,
  ): string | null {
    if (
      typeof payload.mimetype === 'string' &&
      payload.mimetype.trim() !== ''
    ) {
      return payload.mimetype.trim().toLowerCase();
    }

    if (file.startsWith('data:')) {
      const match = /^data:([^;]+);base64,/i.exec(file);
      if (match && match[1]) {
        return match[1].trim().toLowerCase();
      }
    }

    return this.inferMimeTypeFromUrl(file);
  }

  /**
   * Infers the MIME type from the file URL extension.
   * Only operates on HTTP/HTTPS URLs; returns null for other schemes.
   *
   * @param file - File URL
   * @returns Inferred MIME type or null if extension is not recognized
   */
  private inferMimeTypeFromUrl(file: string): string | null {
    if (file.startsWith('http://') || file.startsWith('https://')) {
      const normalized = file.split('?')[0].toLowerCase();

      if (normalized.endsWith('.jpg') || normalized.endsWith('.jpeg')) {
        return 'image/jpeg';
      }

      if (normalized.endsWith('.png')) {
        return 'image/png';
      }

      if (normalized.endsWith('.gif')) {
        return 'image/gif';
      }

      if (normalized.endsWith('.webp')) {
        return 'image/webp';
      }

      if (normalized.endsWith('.svg')) {
        return 'image/svg+xml';
      }

      if (normalized.endsWith('.heic')) {
        return 'image/heic';
      }

      if (normalized.endsWith('.heif')) {
        return 'image/heif';
      }

      if (normalized.endsWith('.avif')) {
        return 'image/avif';
      }
    }

    return null;
  }
}

/**
 * UazapiPresenceController
 *
 * Gerencia envio de presence e download de mídia
 * via provider Uazapi.
 */
@Controller({ path: 'message', version: '1' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class UazapiPresenceController {
  constructor(private readonly client: UazapiClient) {}

  /**
   * Sends presence update via Uazapi.
   *
   * @param token - Instance token
   * @param body - Presence update payload
   * @returns Send result
   */
  @Post('presence')
  sendPresence(@Headers('token') token: string, @Body() body: SendPresenceDto) {
    return this.client.sendPresence(
      token,
      body as unknown as Record<string, unknown>,
    );
  }

  /**
   * Downloads media from Uazapi.
   *
   * @param token - Instance token
   * @param body - Download media payload
   * @returns Download result
   */
  @Post('download')
  downloadMedia(
    @Headers('token') token: string,
    @Body() body: DownloadMediaDto,
  ) {
    return this.client.downloadMedia(
      token,
      body as unknown as Record<string, unknown>,
    );
  }
}
