import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { ChannelFormComponent } from './channel-form';
import { IntegrationService, type Integration } from 'src/app/core/services/integration.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { TenantSettingsService } from '@core/services/tenant-settings.service';

describe('ChannelFormComponent', () => {
  let component: ChannelFormComponent;
  let fixture: ComponentFixture<ChannelFormComponent>;

  const authStoreMock = {
    user: vi.fn().mockReturnValue({ tenant_id: 'test-tenant-id' }),
  };

  const tenantSettingsMock = {
    getSettings: vi.fn().mockReturnValue(
      of({
        data: {
          settings_localization: {
            timezone: 'America/Sao_Paulo',
            dateFormat: 'DD/MM/YYYY' as const,
            timeFormat: '24h' as const,
            currencyFormat: 'BRL' as const,
          },
          settings_privacy: {
            presence: 'all' as const,
            readReceipt: true,
            notificationPreview: true,
          },
        },
      }),
    ),
  };

  const integrationServiceMock = {
    create: vi.fn().mockReturnValue(
      of({
        data: {
          id: 'created-integration',
          name: 'Nova integração',
          provider: 'uazapi',
          is_active: true,
          settings: {
            channel_provider_id: 5,
            cellphone: '+5511987654321',
          },
        } satisfies Integration,
      }),
    ),
    update: vi.fn().mockReturnValue(
      of({
        data: {
          id: 'existing-integration',
          name: 'Integração editada',
          provider: 'uazapi',
          is_active: true,
          settings: {
            channel_provider_id: 5,
            cellphone: '+5511987654321',
          },
        } satisfies Integration,
      }),
    ),
  };

  beforeEach(async () => {
    integrationServiceMock.create.mockClear();
    integrationServiceMock.update.mockClear();
    authStoreMock.user.mockClear();
    tenantSettingsMock.getSettings.mockClear();

    await TestBed.configureTestingModule({
      imports: [ChannelFormComponent],
      providers: [
        { provide: IntegrationService, useValue: integrationServiceMock },
        { provide: AuthStoreService, useValue: authStoreMock },
        { provide: TenantSettingsService, useValue: tenantSettingsMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(ChannelFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('uses uazapi as the default provider', () => {
    expect(component.form.controls.provider.value).toBe('uazapi');
    expect(component.isTokenRequired()).toBe(true);
  });

  it('keeps token required for uazapi editions even when integration already has token', async () => {
    fixture.componentRef.setInput('integration', {
      id: 'integration-1',
      name: 'Integração existente',
      provider: 'uazapi',
      is_active: true,
      is_connected: true,
      has_token: true,
      settings: {
        channel_provider_id: 5,
        cellphone: '+5511987654321',
      },
    } satisfies Integration);
    fixture.detectChanges();
    await fixture.whenStable();

    expect(component.isTokenRequired()).toBe(true);
    expect(component.form.controls.token.hasError('required')).toBe(true);
    expect(component.form.controls.token.disabled).toBe(false);
  });

  it('allows saving a connected uazapi integration after re-entering the token', async () => {
    fixture.componentRef.setInput('integration', {
      id: 'integration-3',
      name: 'Integração conectada',
      provider: 'uazapi',
      is_active: true,
      is_connected: true,
      connection_status: 'open',
      has_token: true,
      settings: {
        channel_provider_id: 5,
        cellphone: '+5511987654321',
      },
    } satisfies Integration);
    fixture.detectChanges();
    await fixture.whenStable();

    component.form.patchValue({
      name: 'Integração conectada ajustada',
      token: 'renewed-token',
    });

    component.submit();

    expect(integrationServiceMock.update).toHaveBeenCalledWith(
      'integration-3',
      expect.objectContaining({
        provider: 'uazapi',
        token: 'renewed-token',
      }),
    );
  });

  it('serializes whatsapp using the selected supported country code', () => {
    component.form.patchValue({
      name: 'Nova integração',
      provider: 'uazapi',
      token: 'secure-token',
      cellphone: '(11) 98765-4321',
      country_code: '+55',
      channel_fallback_message: 'Mensagem fallback integração',
    });

    component.submit();

    expect(integrationServiceMock.create).toHaveBeenCalledWith(
      expect.objectContaining({
        provider: 'uazapi',
        token: 'secure-token',
        settings: expect.objectContaining({
          cellphone: '+5511987654321',
          channel_fallback_message: 'Mensagem fallback integração',
        }),
      }),
    );
  });

  it('loads fallback message from integration settings and keeps it in update payload', async () => {
    fixture.componentRef.setInput('integration', {
      id: 'integration-4',
      name: 'Integração com fallback',
      provider: 'uazapi',
      is_active: true,
      has_token: true,
      settings: {
        channel_provider_id: 5,
        cellphone: '+5511987654321',
        channel_fallback_message: 'Fallback carregado do backend',
      },
    } satisfies Integration);
    fixture.detectChanges();
    await fixture.whenStable();

    expect(component.form.controls.channel_fallback_message.value).toBe(
      'Fallback carregado do backend',
    );

    component.form.patchValue({
      token: 'update-token',
      name: 'Integração com fallback editada',
      channel_fallback_message: 'Fallback editado',
    });

    component.submit();

    expect(integrationServiceMock.update).toHaveBeenCalledWith(
      'integration-4',
      expect.objectContaining({
        settings: expect.objectContaining({
          channel_fallback_message: 'Fallback editado',
        }),
      }),
    );
  });

  it('keeps fallback message undefined in payload when field is empty', () => {
    component.form.patchValue({
      name: 'Nova integração sem fallback',
      provider: 'uazapi',
      token: 'secure-token',
      cellphone: '(11) 98765-4321',
      country_code: '+55',
      channel_fallback_message: '',
    });

    component.submit();

    expect(integrationServiceMock.create).toHaveBeenCalledWith(
      expect.objectContaining({
        settings: expect.objectContaining({
          channel_fallback_message: undefined,
        }),
      }),
    );
  });

  it('preserves the original whatsapp value when the persisted DDI is not recognized safely', async () => {
    const unknownDialCellphone = '+999123456789012';

    fixture.componentRef.setInput('integration', {
      id: 'integration-2',
      name: 'Integração importada',
      provider: 'uazapi',
      is_active: true,
      has_token: true,
      settings: {
        channel_provider_id: 5,
        cellphone: unknownDialCellphone,
      },
    } satisfies Integration);
    fixture.detectChanges();
    await fixture.whenStable();

    component.form.patchValue({
      token: 'replacement-token',
      name: 'Integração importada ajustada',
    });

    component.submit();

    expect(integrationServiceMock.update).toHaveBeenCalledWith(
      'integration-2',
      expect.objectContaining({
        token: 'replacement-token',
        settings: expect.objectContaining({
          cellphone: unknownDialCellphone,
        }),
      }),
    );
  });
});
