import { Test, TestingModule } from '@nestjs/testing';
import { ConfigService } from '@nestjs/config';
import axios from 'axios';
import { AsaasClient } from './asaas.client';

jest.mock('axios');
const mockedAxios = axios as jest.Mocked<typeof axios>;

describe('AsaasClient', () => {
  let client: AsaasClient;
  let mockAxiosInstance: {
    post: jest.Mock;
    get: jest.Mock;
  };

  beforeEach(async () => {
    mockAxiosInstance = {
      post: jest.fn(),
      get: jest.fn(),
    };

    mockedAxios.create.mockReturnValue(
      mockAxiosInstance as unknown as ReturnType<typeof axios.create>,
    );

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        AsaasClient,
        {
          provide: ConfigService,
          useValue: {
            get: jest.fn().mockReturnValue({
              baseUrl: 'https://sandbox.asaas.com/api/v3',
              apiKey: 'asaas-token',
              webhookSecret: 'wh',
            }),
          },
        },
      ],
    }).compile();

    client = module.get<AsaasClient>(AsaasClient);
  });

  it('creates customer', async () => {
    mockAxiosInstance.post.mockResolvedValue({ data: { id: 'cus_123' } });

    const result = await client.createCustomer({
      name: 'Tenant',
      cpfCnpj: '12345678901',
      email: 'tenant@example.com',
      externalReference: 'tenant-1',
    });

    expect(result).toEqual({ id: 'cus_123' });
    expect(mockAxiosInstance.post).toHaveBeenCalledWith('/customers', {
      name: 'Tenant',
      cpfCnpj: '12345678901',
      email: 'tenant@example.com',
      externalReference: 'tenant-1',
    });
  });

  it('creates payment', async () => {
    mockAxiosInstance.post.mockResolvedValue({
      data: {
        id: 'pay_1',
        invoiceUrl: 'http://asaas.test/i/1',
        status: 'PENDING',
      },
    });

    const result = await client.createPayment({
      customer: 'cus_1',
      billingType: 'PIX',
      value: 99.9,
      dueDate: '2026-01-20',
      description: 'Invoice',
      externalReference: 'inv-1',
    });

    expect(result).toEqual({
      id: 'pay_1',
      invoiceUrl: 'http://asaas.test/i/1',
      status: 'PENDING',
    });
  });

  it('gets pix QR code', async () => {
    mockAxiosInstance.get.mockResolvedValue({
      data: {
        payload: 'pix',
        encodedImage: 'img',
        expirationDate: '2026-02-01',
      },
    });

    const result = await client.getPixQRCode('pay_1');

    expect(result).toEqual({
      payload: 'pix',
      encodedImage: 'img',
      expirationDate: '2026-02-01',
    });
    expect(mockAxiosInstance.get).toHaveBeenCalledWith(
      '/payments/pay_1/pixQrCode',
    );
  });

  it('gets payment status', async () => {
    mockAxiosInstance.get.mockResolvedValue({
      data: { status: 'CONFIRMED', value: 120.5, confirmedDate: '2026-01-26' },
    });

    const result = await client.getPaymentStatus('pay_2');

    expect(result).toEqual({
      status: 'CONFIRMED',
      value: 120.5,
      confirmedDate: '2026-01-26',
    });
  });

  it('creates product', async () => {
    mockAxiosInstance.post.mockResolvedValue({ data: { id: 'prod_1' } });

    const result = await client.createProduct({
      name: 'Starter',
      description: 'Plano Starter',
      value: 19.9,
      externalReference: 'plan-1',
    });

    expect(result).toEqual({ id: 'prod_1' });
  });

  it('updates product', async () => {
    mockAxiosInstance.post.mockResolvedValue({ data: {} });

    await expect(
      client.updateProduct('prod_2', {
        name: 'Pro',
        description: 'Plano Pro',
        value: 39.9,
      }),
    ).resolves.toBeUndefined();

    expect(mockAxiosInstance.post).toHaveBeenCalledWith('/products/prod_2', {
      name: 'Pro',
      description: 'Plano Pro',
      value: 39.9,
    });
  });

  describe('error handling', () => {
    it('should handle Axios error in createCustomer', async () => {
      const axiosError = {
        message: 'Request failed',
        isAxiosError: true,
        response: { data: { error: 'Invalid CPF' } },
      };
      mockAxiosInstance.post.mockRejectedValue(axiosError);

      await expect(
        client.createCustomer({
          name: 'Test',
          cpfCnpj: 'invalid',
          email: 'test@test.com',
          externalReference: 'ref-1',
        }),
      ).rejects.toBeDefined();
    });

    it('should handle Axios error in createPayment', async () => {
      const axiosError = {
        message: 'Payment failed',
        isAxiosError: true,
        response: { data: { error: 'Insufficient funds' } },
      };
      mockAxiosInstance.post.mockRejectedValue(axiosError);

      await expect(
        client.createPayment({
          customer: 'cus_1',
          billingType: 'PIX',
          value: 100,
          dueDate: '2026-01-20',
          description: 'Test',
          externalReference: 'ref-1',
        }),
      ).rejects.toBeDefined();
    });

    it('should handle Axios error in getPixQRCode', async () => {
      const axiosError = {
        message: 'QR Code generation failed',
        isAxiosError: true,
        response: { data: { error: 'Payment not found' } },
      };
      mockAxiosInstance.get.mockRejectedValue(axiosError);

      await expect(client.getPixQRCode('invalid-id')).rejects.toBeDefined();
    });

    it('should handle Axios error in getPaymentStatus', async () => {
      const axiosError = {
        message: 'Status check failed',
        isAxiosError: true,
        response: { data: { error: 'Not found' } },
      };
      mockAxiosInstance.get.mockRejectedValue(axiosError);

      await expect(client.getPaymentStatus('invalid-id')).rejects.toBeDefined();
    });

    it('should handle Axios error in createProduct', async () => {
      const axiosError = {
        message: 'Product creation failed',
        isAxiosError: true,
        response: { data: { error: 'Duplicate product' } },
      };
      mockAxiosInstance.post.mockRejectedValue(axiosError);

      await expect(
        client.createProduct({
          name: 'Test',
          description: 'Test product',
          value: 10,
          externalReference: 'ref-1',
        }),
      ).rejects.toBeDefined();
    });

    it('should handle Axios error in updateProduct', async () => {
      const axiosError = {
        message: 'Update failed',
        isAxiosError: true,
        response: { data: { error: 'Product not found' } },
      };
      mockAxiosInstance.post.mockRejectedValue(axiosError);

      await expect(
        client.updateProduct('invalid-id', {
          name: 'Updated',
          description: 'Updated desc',
          value: 20,
          externalReference: 'ref-1',
        }),
      ).rejects.toBeDefined();
    });

    it('should handle non-Axios error', async () => {
      const genericError = new Error('Network error');
      mockAxiosInstance.post.mockRejectedValue(genericError);

      await expect(
        client.createCustomer({
          name: 'Test',
          cpfCnpj: '12345678901',
          email: 'test@test.com',
          externalReference: 'ref-1',
        }),
      ).rejects.toThrow('Network error');
    });
  });
});
