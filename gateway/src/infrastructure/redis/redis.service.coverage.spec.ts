import { Test, TestingModule } from '@nestjs/testing';
import { RedisService } from './redis.service';
import { ConfigService } from '@nestjs/config';
import { Logger } from '@nestjs/common';

// Mock IORedis
const mockOn = jest.fn();
const mockQuit = jest.fn();
const mockPublish = jest.fn();

// We need to match the instantiation pattern. RedisService does `new Redis(...)`
jest.mock('ioredis', () => {
  return class Redis {
    constructor() {}
    on = mockOn;
    quit = mockQuit;
    publish = mockPublish;
  };
});

describe('RedisService Coverage', () => {
  let service: RedisService;
  let configService: any;

  beforeEach(async () => {
    mockOn.mockClear();
    mockQuit.mockClear();
    mockPublish.mockClear();

    configService = {
      get: jest.fn().mockReturnValue('redis://localhost:6379'),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        RedisService,
        Logger,
        { provide: ConfigService, useValue: configService },
      ],
    }).compile();

    service = module.get<RedisService>(RedisService);
  });

  it('should register event listeners on init (in constructor)', () => {
    // Constructor already ran
    expect(mockOn).toHaveBeenCalledWith('connect', expect.any(Function));
    expect(mockOn).toHaveBeenCalledWith('error', expect.any(Function));
  });

  it('should log on connect event for ALL clients', () => {
    // Trigger the callbacks passed to 'on'
    const connectCalls = mockOn.mock.calls.filter(
      (call): call is [string, () => void] => call[0] === 'connect',
    );
    // There should be 2 clients (command and pubsub)
    expect(connectCalls.length).toBeGreaterThanOrEqual(1);

    // Call ALL of them
    connectCalls.forEach((call) => call[1]());
  });

  it('should log on error event for ALL clients', () => {
    const errorCalls = mockOn.mock.calls.filter(
      (call): call is [string, (err: Error) => void] => call[0] === 'error',
    );
    expect(errorCalls.length).toBeGreaterThanOrEqual(1);

    // Call ALL of them
    errorCalls.forEach((call) => call[1](new Error('Test Error')));
  });

  it('should call quit on module destroy', async () => {
    await service.onModuleDestroy();
    expect(mockQuit).toHaveBeenCalled();
  });
});
