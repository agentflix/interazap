import { ChatWebhookFileLoggerService } from './chat-webhook-file-logger.service';
import { ConfigService } from '@nestjs/config';
import { mkdir, readdir, rm, utimes } from 'node:fs/promises';
import { join } from 'node:path';

describe('ChatWebhookFileLoggerService', () => {
  const logsDirectory = join(process.cwd(), 'logs', 'webhooks');

  beforeEach(async () => {
    await rm(logsDirectory, { recursive: true, force: true });
    await mkdir(logsDirectory, { recursive: true });
  });

  it('should append webhook logs asynchronously to daily file', async () => {
    const service = new ChatWebhookFileLoggerService({
      get: jest.fn((key: string) => {
        if (key === 'WEBHOOK_LOG_DIRECTORY') {
          return logsDirectory;
        }

        if (key === 'WEBHOOK_LOG_RETENTION_DAYS') {
          return '15';
        }

        return undefined;
      }),
    } as unknown as ConfigService);
    await service.onModuleInit();

    service.logWebhook({ tenant_id: 'tenant-1', event_type: 'messages' });

    await new Promise((resolve) => setTimeout(resolve, 30));

    const today = new Date().toISOString().slice(0, 10);
    const content = await service.readLogForTesting(today);

    expect(content).toContain('tenant-1');
    expect(content).toContain('messages');

    await service.onModuleDestroy();
  });

  it('should purge webhook logs older than 15 days', async () => {
    const service = new ChatWebhookFileLoggerService({
      get: jest.fn((key: string) => {
        if (key === 'WEBHOOK_LOG_DIRECTORY') {
          return logsDirectory;
        }

        if (key === 'WEBHOOK_LOG_RETENTION_DAYS') {
          return '15';
        }

        return undefined;
      }),
    } as unknown as ConfigService);
    await service.onModuleInit();

    const oldDate = '1999-01-01';
    await service.seedLogForTesting(oldDate, '{"legacy":true}\n');

    const oldFile = join(logsDirectory, `webhook-${oldDate}.log`);
    const oldMtime = new Date(Date.now() - 20 * 24 * 60 * 60 * 1000);
    await utimes(oldFile, oldMtime, oldMtime);

    await (
      service as unknown as { purgeExpiredFiles: () => Promise<void> }
    ).purgeExpiredFiles();

    const files = await readdir(logsDirectory);
    expect(files).not.toContain(`webhook-${oldDate}.log`);

    await service.onModuleDestroy();
  });
});
