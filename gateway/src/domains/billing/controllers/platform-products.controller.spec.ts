import { Test, TestingModule } from '@nestjs/testing';
import { PlatformProductsController } from './platform-products.controller';
import { AsaasClient } from '../providers/asaas/asaas.client';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { ConfigService } from '@nestjs/config';

describe('PlatformProductsController', () => {
  let controller: PlatformProductsController;
  let client: {
    createProduct: jest.Mock;
    updateProduct: jest.Mock;
  };

  beforeEach(async () => {
    client = {
      createProduct: jest.fn(),
      updateProduct: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      controllers: [PlatformProductsController],
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

    controller = module.get<PlatformProductsController>(
      PlatformProductsController,
    );
  });

  it('creates product via AsaasClient', async () => {
    client.createProduct.mockResolvedValue({ id: 'prod_1' });

    const result = await controller.createProduct({
      name: 'Starter',
      description: 'Plano Starter',
      value: 19.9,
      externalReference: 'plan-1',
    });

    expect(result).toEqual({ id: 'prod_1' });
    expect(client.createProduct).toHaveBeenCalled();
  });

  it('updates product via AsaasClient', async () => {
    client.updateProduct.mockResolvedValue(undefined);

    await expect(
      controller.updateProduct('prod_2', {
        name: 'Pro',
        description: 'Plano Pro',
        value: 29.9,
      }),
    ).resolves.toBeUndefined();

    expect(client.updateProduct).toHaveBeenCalledWith('prod_2', {
      name: 'Pro',
      description: 'Plano Pro',
      value: 29.9,
    });
  });
});
