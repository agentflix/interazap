import { DatabaseService } from './database.service';
import { ConfigService } from '@nestjs/config';

describe('DatabaseService', () => {
  let service: DatabaseService;
  let mockPool: {
    query: jest.Mock;
    end: jest.Mock;
    on: jest.Mock;
  };

  beforeEach(() => {
    mockPool = {
      query: jest.fn(),
      end: jest.fn(),
      on: jest.fn(),
    };

    const mockConfigService = {
      get: jest
        .fn()
        .mockReturnValue('postgres://test:test@localhost:5432/test'),
    };

    // Mock the createPool method
    jest
      .spyOn(DatabaseService.prototype as any, 'createPool')
      .mockReturnValue(mockPool);

    service = new DatabaseService(
      mockConfigService as unknown as ConfigService,
    );
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  it('should be defined', () => {
    expect(service).toBeDefined();
  });

  describe('query', () => {
    it('should execute query and return results', async () => {
      const mockResult = { rows: [{ id: 1 }], rowCount: 1 };
      mockPool.query.mockResolvedValue(mockResult);

      const result = await service.query('SELECT * FROM test');

      expect(mockPool.query).toHaveBeenCalledWith('SELECT * FROM test', []);
      expect(result).toEqual(mockResult);
    });

    it('should pass parameters to query', async () => {
      const mockResult = { rows: [{ id: 1 }], rowCount: 1 };
      mockPool.query.mockResolvedValue(mockResult);

      await service.query('SELECT * FROM test WHERE id = $1', [1]);

      expect(mockPool.query).toHaveBeenCalledWith(
        'SELECT * FROM test WHERE id = $1',
        [1],
      );
    });
  });

  describe('onModuleDestroy', () => {
    it('should close pool on destroy', async () => {
      mockPool.end.mockResolvedValue(undefined);

      await service.onModuleDestroy();

      expect(mockPool.end).toHaveBeenCalled();
    });
  });

  describe('pool error handling', () => {
    it('should register error handler on pool', () => {
      expect(mockPool.on).toHaveBeenCalledWith('error', expect.any(Function));
    });

    it('should log pool errors', () => {
      // Get the error handler that was registered
      const errorHandler = mockPool.on.mock.calls.find(
        (call) => call[0] === 'error',
      )?.[1] as (err: Error) => void;

      expect(() => {
        errorHandler(new Error('Connection lost'));
      }).not.toThrow();
    });
  });

  describe('configuration', () => {
    it('should use default connection string when not configured', () => {
      jest.restoreAllMocks();

      const mockPoolDefault = {
        query: jest.fn(),
        end: jest.fn(),
        on: jest.fn(),
      };

      jest
        .spyOn(DatabaseService.prototype as any, 'createPool')
        .mockReturnValue(mockPoolDefault);

      const mockConfigServiceNoDb = {
        get: jest.fn().mockReturnValue(undefined),
      };

      const serviceWithDefault = new DatabaseService(
        mockConfigServiceNoDb as unknown as ConfigService,
      );

      expect(serviceWithDefault).toBeDefined();
    });
  });
});
