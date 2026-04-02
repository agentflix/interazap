import { HttpException, HttpStatus } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Test, TestingModule } from '@nestjs/testing';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { AIProviderFactory } from '../providers/ai-provider.factory';
import {
  OpenAIProviderAdapter,
  OpenAIProviderError,
} from '../providers/openai/openai-provider.adapter';
import { AIController } from './ai.controller';

describe('AIController', () => {
  let controller: AIController;
  let providerFactory: {
    getDefaultProvider: jest.Mock;
  };
  let openaiProvider: {
    createEmbeddings: jest.Mock;
  };

  beforeEach(async () => {
    providerFactory = {
      getDefaultProvider: jest.fn(),
    };

    openaiProvider = {
      createEmbeddings: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [AIController],
      providers: [
        {
          provide: AIProviderFactory,
          useValue: providerFactory,
        },
        {
          provide: OpenAIProviderAdapter,
          useValue: openaiProvider,
        },
        InternalApiKeyGuard,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn().mockReturnValue('test-internal-key'),
          },
        },
      ],
    }).compile();

    controller = module.get<AIController>(AIController);
  });

  it('returns successful chat response when provider completes', async () => {
    const complete = jest.fn().mockResolvedValue({
      content: 'hello',
      finishReason: 'stop',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
      model: 'gpt-4o-mini',
    });

    providerFactory.getDefaultProvider.mockReturnValue({
      name: 'openai',
      complete,
    });

    const result = await controller.chat({
      messages: [{ role: 'user', content: 'Hi' }],
      model: 'gpt-4o-mini',
      temperature: 0.2,
      maxTokens: 64,
    });

    expect(result).toEqual({
      success: true,
      content: 'hello',
      finishReason: 'stop',
      promptTokens: 10,
      completionTokens: 5,
      totalTokens: 15,
      model: 'gpt-4o-mini',
    });
    expect(complete).toHaveBeenCalledTimes(1);
  });

  it('throws mapped HttpException when chat provider fails', async () => {
    const complete = jest
      .fn()
      .mockRejectedValue(
        new OpenAIProviderError('PROVIDER_RATE_LIMIT', 'Rate limit exceeded'),
      );

    providerFactory.getDefaultProvider.mockReturnValue({
      name: 'openai',
      complete,
    });

    try {
      await controller.chat({
        messages: [{ role: 'user', content: 'Hello' }],
      });
      throw new Error('Expected chat() to throw HttpException');
    } catch (error) {
      expect(error).toBeInstanceOf(HttpException);
      const exception = error as HttpException;
      expect(exception.getStatus()).toBe(HttpStatus.TOO_MANY_REQUESTS);
      expect(exception.message).toBe('AI provider rate limit reached');
    }
  });
});
