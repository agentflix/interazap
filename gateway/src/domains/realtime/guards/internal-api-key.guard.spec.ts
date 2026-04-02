import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import { ExecutionContext, UnauthorizedException } from '@nestjs/common';
import { InternalApiKeyGuard } from './internal-api-key.guard';

describe('InternalApiKeyGuard', () => {
  let guard: InternalApiKeyGuard;

  const mockConfigService = {
    get: jest.fn(),
  };

  beforeEach(async () => {
    const module: TestingModule = await Test.createTestingModule({
      providers: [
        InternalApiKeyGuard,
        {
          provide: ConfigService,
          useValue: mockConfigService,
        },
      ],
    }).compile();

    guard = module.get<InternalApiKeyGuard>(InternalApiKeyGuard);
  });

  afterEach(() => {
    jest.clearAllMocks();
  });

  const createMockContext = (apiKey?: string): ExecutionContext =>
    ({
      switchToHttp: () => ({
        getRequest: () => ({
          headers: apiKey ? { 'x-api-key': apiKey } : {},
          ip: '127.0.0.1',
          path: '/internal/broadcast/event',
        }),
      }),
    }) as ExecutionContext;

  it('should allow access with valid API key', () => {
    mockConfigService.get.mockReturnValue('valid-api-key');
    const context = createMockContext('valid-api-key');

    expect(guard.canActivate(context)).toBe(true);
  });

  it('should deny access with invalid API key', () => {
    mockConfigService.get.mockReturnValue('valid-api-key');
    const context = createMockContext('wrong-key');

    expect(() => guard.canActivate(context)).toThrow(UnauthorizedException);
  });

  it('should deny access with missing API key', () => {
    mockConfigService.get.mockReturnValue('valid-api-key');
    const context = createMockContext();

    expect(() => guard.canActivate(context)).toThrow(UnauthorizedException);
  });

  it('should deny access when INTERNAL_API_KEY not configured', () => {
    mockConfigService.get.mockReturnValue('');
    const context = createMockContext('any-key');

    expect(() => guard.canActivate(context)).toThrow(UnauthorizedException);
  });
});
