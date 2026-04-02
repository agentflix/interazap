import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { SendMessageService, OutboundMessage } from './send-message.service';
import { ProviderFactory } from '../providers/provider.factory';
import { RetryPolicy } from './retry-policy';
import { WhatsAppProvider } from '../contracts/provider.interface';
import {
  CircuitBreakerService,
  CircuitState,
} from '../../../shared/services/circuit-breaker';

describe('SendMessageService', () => {
  let service: SendMessageService;
  let mockProviderFactory: jest.Mocked<ProviderFactory>;
  let mockProvider: jest.Mocked<WhatsAppProvider>;
  let circuitBreakerService: CircuitBreakerService;

  const flushAsyncWork = async (): Promise<void> => {
    await Promise.resolve();
    await Promise.resolve();
    await new Promise((resolve) => setTimeout(resolve, 0));
  };

  const getRetryQueue = (): Array<{
    message: OutboundMessage;
    addedAt: number;
    retryCount: number;
  }> => {
    return (
      service as unknown as {
        retryQueue: Array<{
          message: OutboundMessage;
          addedAt: number;
          retryCount: number;
        }>;
      }
    ).retryQueue;
  };

  beforeEach(async () => {
    mockProvider = {
      name: 'uazapi',
      sendText: jest.fn(),
      sendMedia: jest.fn(),
      getStatus: jest.fn(),
      disconnect: jest.fn(),
      getQrCode: jest.fn(),
      normalizeWebhook: jest.fn(),
    } as unknown as jest.Mocked<WhatsAppProvider>;

    mockProviderFactory = {
      getProvider: jest.fn().mockReturnValue(mockProvider),
      hasProvider: jest.fn().mockReturnValue(true),
      getProviderNames: jest.fn().mockReturnValue(['uazapi', 'zapi']),
    } as unknown as jest.Mocked<ProviderFactory>;

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        SendMessageService,
        CircuitBreakerService,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn((key: string) => {
              if (key === 'MESSAGE_RETRY_QUEUE_MAX_SIZE') {
                return '1000';
              }

              return undefined;
            }),
          },
        },
        { provide: ProviderFactory, useValue: mockProviderFactory },
        RetryPolicy,
      ],
    }).compile();

    service = module.get<SendMessageService>(SendMessageService);
    circuitBreakerService = module.get<CircuitBreakerService>(
      CircuitBreakerService,
    );
  });

  describe('send', () => {
    const baseMessage: OutboundMessage = {
      tenantId: 'tenant-123',
      instanceId: 'instance-456',
      provider: 'uazapi',
      instanceToken: 'token-abc',
      type: 'text',
      to: '5511999999999',
      text: 'Hello World',
    };

    it('should send text message successfully', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: true,
        messageId: 'msg-123',
      });

      const result = await service.send(baseMessage);

      expect(result.success).toBe(true);
      expect(result.messageId).toBe('msg-123');
      expect(result.attempts).toBe(1);
      expect(result.processingTimeMs).toBeGreaterThanOrEqual(0);
      expect(mockProviderFactory.getProvider).toHaveBeenCalledWith('uazapi');
      expect(mockProvider.sendText).toHaveBeenCalledWith('token-abc', {
        to: '5511999999999',
        text: 'Hello World',
      });
    });

    it('should send media message successfully', async () => {
      const mediaMessage: OutboundMessage = {
        ...baseMessage,
        type: 'media',
        text: undefined,
        mediaType: 'image',
        mediaUrl: 'https://example.com/image.jpg',
        caption: 'Check this out',
      };

      mockProvider.sendMedia.mockResolvedValue({
        success: true,
        messageId: 'img-456',
      });

      const result = await service.send(mediaMessage);

      expect(result.success).toBe(true);
      expect(result.messageId).toBe('img-456');
      expect(mockProvider.sendMedia).toHaveBeenCalledWith('token-abc', {
        to: '5511999999999',
        type: 'image',
        mediaUrl: 'https://example.com/image.jpg',
        caption: 'Check this out',
        fileName: undefined,
      });
    });

    it('should return error when send fails', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Invalid phone number',
      });

      const result = await service.send(baseMessage);

      expect(result.success).toBe(false);
      expect(result.error).toBe('Invalid phone number');
    });

    it('should include processing time', async () => {
      mockProvider.sendText.mockImplementation(async () => {
        await new Promise((resolve) => setTimeout(resolve, 10));
        return { success: true, messageId: 'msg-123' };
      });

      const result = await service.send(baseMessage);

      expect(result.processingTimeMs).toBeGreaterThanOrEqual(10);
    });

    it('should use correct provider based on message', async () => {
      const zapiMessage: OutboundMessage = {
        ...baseMessage,
        provider: 'zapi',
      };

      mockProvider.sendText.mockResolvedValue({
        success: true,
        messageId: 'msg-789',
      });

      await service.send(zapiMessage);

      expect(mockProviderFactory.getProvider).toHaveBeenCalledWith('zapi');
    });

    it('should handle document type', async () => {
      const docMessage: OutboundMessage = {
        ...baseMessage,
        type: 'media',
        text: undefined,
        mediaType: 'document',
        mediaUrl: 'https://example.com/file.pdf',
        fileName: 'report.pdf',
      };

      mockProvider.sendMedia.mockResolvedValue({
        success: true,
        messageId: 'doc-123',
      });

      const result = await service.send(docMessage);

      expect(result.success).toBe(true);
      expect(mockProvider.sendMedia).toHaveBeenCalledWith('token-abc', {
        to: '5511999999999',
        type: 'document',
        mediaUrl: 'https://example.com/file.pdf',
        caption: undefined,
        fileName: 'report.pdf',
      });
    });
  });

  describe('circuit breaker integration', () => {
    const baseMessage: OutboundMessage = {
      tenantId: 'tenant-123',
      instanceId: 'instance-456',
      provider: 'uazapi',
      instanceToken: 'token-abc',
      type: 'text',
      to: '5511999999999',
      text: 'Hello World',
      correlationId: 'correlation-123',
    };

    it('should open circuit after repeated failures', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      // Make 5 failing calls (threshold is 5)
      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      // Circuit should now be open
      expect(service.getCircuitState('uazapi')).toBe(CircuitState.OPEN);
    });

    it('should queue message when circuit is open', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      // Open the circuit with 5 failures
      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      // Circuit is now open - send one more to trigger queue
      await service.send({
        ...baseMessage,
        correlationId: 'queued-message',
      });

      expect(service.getQueueSize()).toBeGreaterThan(0);
    });

    it('should return queued status when fallback is used', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      // Open the circuit
      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      // Next message should be queued
      const result = await service.send({
        ...baseMessage,
        correlationId: 'new-correlation',
      });

      expect(result.success).toBe(false);
      expect(result.queued).toBe(true);
      expect(result.error).toContain('queued for retry');
    });

    it('should track circuit state per provider', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      // Open circuit for uazapi
      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      // uazapi should be open
      expect(service.getCircuitState('uazapi')).toBe(CircuitState.OPEN);

      // zapi should not exist yet
      expect(service.getCircuitState('zapi')).toBeUndefined();
    });

    it('should not exceed max queue size', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      // Open the circuit
      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      // Queue size should be reasonable
      expect(service.getQueueSize()).toBeLessThanOrEqual(5);
    });

    it('should process retry queue when available', async () => {
      // Setup: open the circuit first
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      // Send more messages to add to queue (circuit is now open)
      for (let i = 0; i < 3; i++) {
        await service.send({
          ...baseMessage,
          correlationId: `queued-${i}`,
        });
      }

      const initialQueueSize = service.getQueueSize();
      expect(initialQueueSize).toBeGreaterThan(0);

      // Now make provider work
      mockProvider.sendText.mockResolvedValue({
        success: true,
        messageId: 'msg-retry',
      });

      // Reset circuit manually for testing
      circuitBreakerService.reset('whatsapp:uazapi');

      // Process the queue
      await service.processRetryQueue();

      // Queue should be empty or smaller
      expect(service.getQueueSize()).toBeLessThan(initialQueueSize);
    });

    it('should trigger retry queue processing automatically when circuit transitions to CLOSED', async () => {
      const processRetryQueueSpy = jest.spyOn(service, 'processRetryQueue');

      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      await service.send({
        ...baseMessage,
        correlationId: 'queued-for-auto-processing',
      });

      expect(service.getQueueSize()).toBe(1);

      mockProvider.sendText.mockResolvedValue({
        success: true,
        messageId: 'msg-auto-retry',
      });

      circuitBreakerService.reset('whatsapp:uazapi');
      await flushAsyncWork();

      expect(processRetryQueueSpy).toHaveBeenCalledTimes(1);
      expect(service.getQueueSize()).toBe(0);
    });

    it('should preserve retryCount when circuit requeues and increment it after a processing failure', async () => {
      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Provider unavailable',
      });

      for (let i = 0; i < 5; i++) {
        await service.send(baseMessage);
      }

      await service.send({
        ...baseMessage,
        correlationId: 'retry-counter',
      });

      const queuedMessage = getRetryQueue()[0];
      queuedMessage.retryCount = 2;

      await service.processRetryQueue();

      expect(getRetryQueue()).toHaveLength(1);
      expect(getRetryQueue()[0]?.retryCount).toBe(2);

      mockProvider.sendText.mockResolvedValue({
        success: false,
        error: 'Still failing after retry',
      });

      circuitBreakerService.reset('whatsapp:uazapi');
      await flushAsyncWork();

      expect(getRetryQueue()).toHaveLength(1);
      expect(getRetryQueue()[0]?.retryCount).toBe(3);
    });
  });
});
