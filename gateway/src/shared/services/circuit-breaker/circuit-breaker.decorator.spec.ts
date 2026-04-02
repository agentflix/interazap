import { Injectable } from '@nestjs/common';
import {
  CircuitBreaker,
  whatsAppCircuitOptions,
  openAICircuitOptions,
  paymentCircuitOptions,
} from './circuit-breaker.decorator';
import {
  CircuitBreakerService,
  CircuitState,
  CircuitOpenException,
} from './circuit-breaker.service';

describe('CircuitBreaker Decorator', () => {
  let circuitBreakerService: CircuitBreakerService;

  /**
   * Test service with decorated methods
   */
  @Injectable()
  class TestService {
    public callCount = 0;
    public fallbackCallCount = 0;
    public circuitBreaker: CircuitBreakerService;

    constructor(circuitBreaker: CircuitBreakerService) {
      this.circuitBreaker = circuitBreaker;
    }

    @CircuitBreaker('test-service', { failureThreshold: 2, resetTimeout: 50 })
    async decoratedMethod(): Promise<string> {
      this.callCount++;
      return await Promise.resolve('success');
    }

    @CircuitBreaker('failing-service', {
      failureThreshold: 2,
      resetTimeout: 50,
    })
    async failingMethod(): Promise<string> {
      this.callCount++;
      return await Promise.reject(new Error('Service unavailable'));
    }

    @CircuitBreaker('fallback-service', {
      failureThreshold: 1,
      resetTimeout: 50,
      fallbackMethod: 'methodWithFallbackFallback',
    })
    async methodWithFallback(): Promise<string> {
      this.callCount++;
      return await Promise.reject(new Error('Main method failed'));
    }

    async methodWithFallbackFallback(): Promise<string> {
      this.fallbackCallCount++;
      return await Promise.resolve('fallback-response');
    }

    @CircuitBreaker('auto-fallback-service', {
      failureThreshold: 1,
      resetTimeout: 50,
    })
    async autoFallbackMethod(): Promise<string> {
      this.callCount++;
      return await Promise.reject(new Error('Auto fallback test'));
    }

    // Auto-named fallback (methodName + 'Fallback')
    async autoFallbackMethodFallback(): Promise<string> {
      this.fallbackCallCount++;
      return await Promise.resolve('auto-fallback-response');
    }
  }

  beforeEach(() => {
    circuitBreakerService = new CircuitBreakerService();
  });

  describe('basic decoration', () => {
    it('should call the original method when circuit is closed', async () => {
      const service = new TestService(circuitBreakerService);

      const result = await service.decoratedMethod();

      expect(result).toBe('success');
      expect(service.callCount).toBe(1);
    });

    it('should pass through multiple successful calls', async () => {
      const service = new TestService(circuitBreakerService);

      await service.decoratedMethod();
      await service.decoratedMethod();
      await service.decoratedMethod();

      expect(service.callCount).toBe(3);
      expect(circuitBreakerService.getState('test-service')).toBe(
        CircuitState.CLOSED,
      );
    });
  });

  describe('circuit opening', () => {
    it('should open circuit after threshold failures', async () => {
      const service = new TestService(circuitBreakerService);

      // First failure
      await expect(service.failingMethod()).rejects.toThrow(
        'Service unavailable',
      );
      expect(circuitBreakerService.getState('failing-service')).toBe(
        CircuitState.CLOSED,
      );

      // Second failure - should open
      await expect(service.failingMethod()).rejects.toThrow(
        'Service unavailable',
      );
      expect(circuitBreakerService.getState('failing-service')).toBe(
        CircuitState.OPEN,
      );
    });

    it('should fail fast when circuit is open', async () => {
      const service = new TestService(circuitBreakerService);

      // Open the circuit
      await expect(service.failingMethod()).rejects.toThrow();
      await expect(service.failingMethod()).rejects.toThrow();

      // Reset call count to verify no calls are made
      service.callCount = 0;

      // Should fail fast without calling the method
      await expect(service.failingMethod()).rejects.toThrow(
        CircuitOpenException,
      );
      expect(service.callCount).toBe(0);
    });
  });

  describe('fallback methods', () => {
    it('should call explicit fallback method when circuit is open', async () => {
      const service = new TestService(circuitBreakerService);

      // First call fails and opens circuit
      await expect(service.methodWithFallback()).rejects.toThrow(
        'Main method failed',
      );

      // Circuit is now open, should call fallback
      const result = await service.methodWithFallback();

      expect(result).toBe('fallback-response');
      expect(service.fallbackCallCount).toBe(1);
    });

    it('should call auto-named fallback method when circuit is open', async () => {
      const service = new TestService(circuitBreakerService);

      // First call fails and opens circuit
      await expect(service.autoFallbackMethod()).rejects.toThrow(
        'Auto fallback test',
      );

      // Circuit is now open, should call auto-fallback
      const result = await service.autoFallbackMethod();

      expect(result).toBe('auto-fallback-response');
      expect(service.fallbackCallCount).toBe(1);
    });
  });

  describe('circuit recovery', () => {
    it('should transition to half-open after reset timeout', async () => {
      const service = new TestService(circuitBreakerService);

      // Open the circuit
      await expect(service.failingMethod()).rejects.toThrow();
      await expect(service.failingMethod()).rejects.toThrow();
      expect(circuitBreakerService.getState('failing-service')).toBe(
        CircuitState.OPEN,
      );

      // Wait for reset timeout
      await new Promise((r) => setTimeout(r, 60));

      // Next call should transition to half-open
      // Since the method still fails, it will go back to OPEN
      await expect(service.failingMethod()).rejects.toThrow();
    });
  });

  describe('without circuit breaker service', () => {
    it('should call original method when no circuit breaker is injected', async () => {
      @Injectable()
      class ServiceWithoutCB {
        @CircuitBreaker('no-cb-service')
        async method(): Promise<string> {
          return await Promise.resolve('no-cb-result');
        }
      }

      const service = new ServiceWithoutCB();
      const result = await service.method();

      expect(result).toBe('no-cb-result');
    });
  });
});

describe('Circuit Options Helpers', () => {
  describe('whatsAppCircuitOptions', () => {
    it('should return correct options for WhatsApp', () => {
      const options = whatsAppCircuitOptions();

      expect(options).toEqual({
        failureThreshold: 5,
        resetTimeout: 30000,
        successThreshold: 2,
        name: 'whatsapp',
      });
    });
  });

  describe('openAICircuitOptions', () => {
    it('should return correct options for OpenAI', () => {
      const options = openAICircuitOptions();

      expect(options).toEqual({
        failureThreshold: 3,
        resetTimeout: 60000,
        successThreshold: 1,
        name: 'openai',
      });
    });
  });

  describe('paymentCircuitOptions', () => {
    it('should return correct options for payments', () => {
      const options = paymentCircuitOptions();

      expect(options).toEqual({
        failureThreshold: 3,
        resetTimeout: 60000,
        successThreshold: 2,
        name: 'payment',
      });
    });
  });
});
