import { Test, TestingModule } from '@nestjs/testing';
import { BillingController } from './billing.controller';
import { AsaasClient } from '../providers/asaas/asaas.client';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { ConfigService } from '@nestjs/config';

describe('BillingController', () => {
  let controller: BillingController;
  let client: {
    createCustomer: jest.Mock;
    createPayment: jest.Mock;
    getPixQRCode: jest.Mock;
    getPaymentStatus: jest.Mock;
  };

  beforeEach(async () => {
    client = {
      createCustomer: jest.fn(),
      createPayment: jest.fn(),
      getPixQRCode: jest.fn(),
      getPaymentStatus: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [BillingController],
      providers: [
        {
          provide: AsaasClient,
          useValue: client,
        },
        InternalApiKeyGuard,
        {
          provide: ConfigService,
          useValue: { get: jest.fn().mockReturnValue('test-key') },
        },
      ],
    }).compile();

    controller = module.get<BillingController>(BillingController);
  });

  it('creates customer via AsaasClient', async () => {
    client.createCustomer.mockResolvedValue({ id: 'cus_1' });

    const result = await controller.createCustomer({
      name: 'Tenant',
      cpfCnpj: '12345678901',
      email: 'tenant@example.com',
      externalReference: 'tenant-1',
    });

    expect(result).toEqual({ id: 'cus_1' });
    expect(client.createCustomer).toHaveBeenCalled();
  });

  it('creates payment via AsaasClient', async () => {
    client.createPayment.mockResolvedValue({ id: 'pay_1' });

    const result = await controller.createPayment({
      customer: 'cus_1',
      billingType: 'PIX',
      value: 10,
      dueDate: '2026-01-10',
      description: 'Invoice',
      externalReference: 'inv-1',
    });

    expect(result).toEqual({ id: 'pay_1' });
  });

  it('gets pix QR code via AsaasClient', async () => {
    client.getPixQRCode.mockResolvedValue({ payload: 'pix' });

    const result = await controller.getPixQRCode('pay_1');

    expect(result).toEqual({ payload: 'pix' });
    expect(client.getPixQRCode).toHaveBeenCalledWith('pay_1');
  });

  it('gets payment status via AsaasClient', async () => {
    client.getPaymentStatus.mockResolvedValue({ status: 'CONFIRMED' });

    const result = await controller.getPaymentStatus('pay_2');

    expect(result).toEqual({ status: 'CONFIRMED' });
    expect(client.getPaymentStatus).toHaveBeenCalledWith('pay_2');
  });
});
