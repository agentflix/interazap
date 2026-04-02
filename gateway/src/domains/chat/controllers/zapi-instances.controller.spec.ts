import { ZapiInstancesController } from './zapi-instances.controller';
import { ZapiAdapter } from '../providers/zapi/zapi.adapter';

describe('ZapiInstancesController', () => {
  let controller: ZapiInstancesController;
  let adapter: jest.Mocked<ZapiAdapter>;

  beforeEach(() => {
    adapter = {
      getQrCode: jest.fn().mockResolvedValue('base64-qr'),
      getStatus: jest.fn().mockResolvedValue({ connected: true }),
      disconnect: jest.fn().mockResolvedValue(undefined),
    } as unknown as jest.Mocked<ZapiAdapter>;

    controller = new ZapiInstancesController(adapter);
  });

  it('should return qr connection payload', async () => {
    const result = await controller.connect('instance-1:token-1');

    expect(adapter.getQrCode).toHaveBeenCalledWith('instance-1:token-1');
    expect(result).toEqual(
      expect.objectContaining({
        provider: 'zapi',
        mode: 'qr',
        qr_code: 'base64-qr',
      }),
    );
  });

  it('should return status', async () => {
    const result = await controller.status('instance-1:token-1');

    expect(adapter.getStatus).toHaveBeenCalledWith('instance-1:token-1');
    expect(result).toEqual({ connected: true });
  });

  it('should disconnect instance', async () => {
    const result = await controller.disconnect('instance-1:token-1');

    expect(adapter.disconnect).toHaveBeenCalledWith('instance-1:token-1');
    expect(result).toEqual({ success: true });
  });

  it('should return webhook configuration hint', () => {
    const result = controller.configureWebhook('instance-1:token-1');

    expect(result).toEqual(
      expect.objectContaining({
        success: false,
        provider: 'zapi',
        token: 'instance-1:token-1',
      }),
    );
  });
});
