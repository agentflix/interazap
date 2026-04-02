import { AsaasNormalizer } from './asaas.normalizer';

describe('AsaasNormalizer', () => {
  let normalizer: AsaasNormalizer;

  beforeEach(() => {
    normalizer = new AsaasNormalizer();
  });

  describe('normalize', () => {
    it('should normalize PAYMENT_RECEIVED event', () => {
      const rawPayload = {
        event: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay_123456',
          customer: 'cus_789',
          value: 99.9,
          netValue: 95.0,
          description: 'Monthly subscription',
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-01-22',
          paymentDate: '2026-01-22',
          invoiceUrl: 'https://asaas.com/invoice/123',
        },
      };

      const result = normalizer.normalize('tenant-123', rawPayload);

      expect(result.tenantId).toBe('tenant-123');
      expect(result.provider).toBe('asaas');
      expect(result.eventType).toBe('PAYMENT_RECEIVED');
      expect(result.payment.id).toBe('pay_123456');
      expect(result.payment.customer).toBe('cus_789');
      expect(result.payment.value).toBe(99.9);
      expect(result.payment.billingType).toBe('PIX');
      expect(result.payment.status).toBe('RECEIVED');
      expect(result.idempotencyKey).toBeDefined();
      expect(result.receivedAt).toBeInstanceOf(Date);
      expect(result.rawPayload).toEqual(rawPayload);
    });

    it('should normalize PAYMENT_OVERDUE event', () => {
      const rawPayload = {
        event: 'PAYMENT_OVERDUE',
        payment: {
          id: 'pay_overdue_001',
          customer: 'cus_456',
          value: 150.0,
          billingType: 'BOLETO',
          status: 'OVERDUE',
          dueDate: '2026-01-15',
        },
      };

      const result = normalizer.normalize('tenant-456', rawPayload);

      expect(result.eventType).toBe('PAYMENT_OVERDUE');
      expect(result.payment.billingType).toBe('BOLETO');
      expect(result.payment.status).toBe('OVERDUE');
    });

    it('should normalize PAYMENT_CONFIRMED with credit card', () => {
      const rawPayload = {
        event: 'PAYMENT_CONFIRMED',
        payment: {
          id: 'pay_cc_001',
          customer: 'cus_cc',
          value: 299.0,
          billingType: 'CREDIT_CARD',
          status: 'CONFIRMED',
          dueDate: '2026-01-22',
        },
      };

      const result = normalizer.normalize('tenant-789', rawPayload);

      expect(result.eventType).toBe('PAYMENT_CONFIRMED');
      expect(result.payment.billingType).toBe('CREDIT_CARD');
      expect(result.payment.status).toBe('CONFIRMED');
    });

    it('should handle missing payment data', () => {
      const rawPayload = {
        event: 'PAYMENT_CREATED',
      };

      const result = normalizer.normalize('tenant-empty', rawPayload);

      expect(result.eventType).toBe('PAYMENT_CREATED');
      expect(result.payment.id).toBe('unknown');
      expect(result.payment.customer).toBe('unknown');
      expect(result.payment.value).toBe(0);
      expect(result.payment.billingType).toBe('UNDEFINED');
      expect(result.payment.status).toBe('PENDING');
    });

    it('should handle unknown event type', () => {
      const rawPayload = {
        event: 'SOME_UNKNOWN_EVENT',
        payment: {
          id: 'pay_unknown',
          customer: 'cus_unknown',
          value: 50.0,
          billingType: 'UNKNOWN_TYPE',
          status: 'UNKNOWN_STATUS',
          dueDate: '2026-01-22',
        },
      };

      const result = normalizer.normalize('tenant-unknown', rawPayload);

      expect(result.eventType).toBe('UNKNOWN');
      expect(result.payment.billingType).toBe('UNDEFINED');
      expect(result.payment.status).toBe('PENDING');
    });

    it('should handle refund events', () => {
      const rawPayload = {
        event: 'PAYMENT_REFUNDED',
        payment: {
          id: 'pay_refund_001',
          customer: 'cus_refund',
          value: 100.0,
          billingType: 'PIX',
          status: 'REFUNDED',
          dueDate: '2026-01-01',
          paymentDate: '2026-01-01',
        },
      };

      const result = normalizer.normalize('tenant-refund', rawPayload);

      expect(result.eventType).toBe('PAYMENT_REFUNDED');
      expect(result.payment.status).toBe('REFUNDED');
    });

    it('should generate consistent idempotency key', () => {
      const rawPayload = {
        event: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay_same_id',
          customer: 'cus_1',
          value: 100.0,
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-01-22',
        },
      };

      const result1 = normalizer.normalize('tenant-1', rawPayload);
      const result2 = normalizer.normalize('tenant-1', rawPayload);

      expect(result1.idempotencyKey).toBe(result2.idempotencyKey);
    });

    it('should generate different keys for different payments', () => {
      const rawPayload1 = {
        event: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay_001',
          customer: 'cus_1',
          value: 100.0,
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-01-22',
        },
      };

      const rawPayload2 = {
        event: 'PAYMENT_RECEIVED',
        payment: {
          id: 'pay_002',
          customer: 'cus_1',
          value: 100.0,
          billingType: 'PIX',
          status: 'RECEIVED',
          dueDate: '2026-01-22',
        },
      };

      const result1 = normalizer.normalize('tenant-1', rawPayload1);
      const result2 = normalizer.normalize('tenant-1', rawPayload2);

      expect(result1.idempotencyKey).not.toBe(result2.idempotencyKey);
    });

    it('should handle chargeback events', () => {
      const rawPayload = {
        event: 'PAYMENT_CHARGEBACK_REQUESTED',
        payment: {
          id: 'pay_chargeback',
          customer: 'cus_cb',
          value: 500.0,
          billingType: 'CREDIT_CARD',
          status: 'CHARGEBACK_REQUESTED',
          dueDate: '2026-01-10',
        },
      };

      const result = normalizer.normalize('tenant-cb', rawPayload);

      expect(result.eventType).toBe('PAYMENT_CHARGEBACK_REQUESTED');
      expect(result.payment.status).toBe('CHARGEBACK_REQUESTED');
    });
  });
});
