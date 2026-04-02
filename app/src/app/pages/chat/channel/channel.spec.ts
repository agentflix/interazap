import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { HttpErrorResponse, provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { throwError } from 'rxjs';
import { toast } from 'ngx-sonner';
import { vi } from 'vitest';
import { ChannelPage } from './channel';
import { type Integration } from 'src/app/core/services/integration.service';
import { type IntegrationConnectionEvent } from 'src/app/core/services/chat-realtime.service';
import { IntegrationService } from '@core/services/integration.service';

interface ChannelPageRealtimeHarness {
  applyRealtimeConnectionUpdate(event: IntegrationConnectionEvent): void;
}

describe('Integrations', () => {
  let component: ChannelPage;
  let fixture: ComponentFixture<ChannelPage>;
  let integrationService: IntegrationService;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChannelPage],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(ChannelPage);
    component = fixture.componentInstance;
    integrationService = TestBed.inject(IntegrationService);
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should have initial state', () => {
    expect(component.integrations()).toEqual([]);
    expect(component.meta().current_page).toBe(1);
  });

  it('should open create, edit and delete modals', () => {
    const integration = { id: 'int-1', name: 'WhatsApp' } as Integration;

    component.openCreate();
    expect(component.showFormModal()).toBe(true);

    component.openEdit(integration);
    expect(component.selectedIntegration()).toEqual(integration);

    component.openDelete(integration);
    expect(component.showDeleteModal()).toBe(true);
    expect(component.integrationToDelete()).toEqual(integration);
  });

  it('should handle QR modal lifecycle', () => {
    component.showQrModal.set(true);
    expect(component.showQrModal()).toBe(true);

    component.closeQrModal();
    expect(component.showQrModal()).toBe(false);
  });

  it('should only allow connect when token is configured', () => {
    const disconnectedWithToken = {
      id: 'int-1',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'disconnected',
      has_token: true,
    } as Integration;

    const disconnectedWithoutToken = {
      id: 'int-2',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'disconnected',
      has_token: false,
    } as Integration;

    const connectedWithToken = {
      id: 'int-3',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: true,
      connection_status: 'connected',
      has_token: true,
    } as Integration;

    const unsupportedProviderWithToken = {
      id: 'int-4',
      name: 'WhatsApp Evolution',
      provider: 'evolution',
      is_connected: false,
      connection_status: 'disconnected',
      has_token: true,
    } as Integration;

    expect(component.canConnect(disconnectedWithToken)).toBe(true);
    expect(component.canConnect(disconnectedWithoutToken)).toBe(false);
    expect(component.canConnect(connectedWithToken)).toBe(false);
    expect(component.canConnect(unsupportedProviderWithToken)).toBe(false);
  });

  it('should prioritize is_connected over textual status for connection UI decisions', () => {
    const connectedWithNonCanonicalStatus = {
      id: 'int-5',
      name: 'WhatsApp UaZapi',
      provider: 'uazapi',
      is_connected: true,
      connection_status: 'authorized',
      has_token: true,
    } as Integration;

    const staleTextualConnectedStatus = {
      id: 'int-6',
      name: 'WhatsApp UaZapi',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'connected',
      has_token: true,
    } as Integration;

    expect(component.connectionState(connectedWithNonCanonicalStatus)).toBe('connected');
    expect(component.connectionLabel(connectedWithNonCanonicalStatus)).toBe('Conectado');
    expect(component.canConnect(connectedWithNonCanonicalStatus)).toBe(false);

    expect(component.connectionState(staleTextualConnectedStatus)).toBe('disconnected');
    expect(component.canConnect(staleTextualConnectedStatus)).toBe(true);
  });

  it('should reconcile realtime aliases as connected using the shared status helper', () => {
    const existingIntegration = {
      id: 'int-7',
      name: 'WhatsApp UaZapi',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'connecting',
      has_token: true,
      settings: {
        channel_provider_id: 5,
        cellphone: '+5511987654321',
        token: 'instance-token',
      },
    } as Integration;

    component.integrations.set([existingIntegration]);

    (component as unknown as ChannelPageRealtimeHarness).applyRealtimeConnectionUpdate({
      instance_id: 'int-7',
      status: 'authorized',
      connected: false,
    });

    const [updatedIntegration] = component.integrations();

    expect(updatedIntegration.connection_status).toBe('connected');
    expect(updatedIntegration.is_connected).toBe(true);
    expect(component.connectionState(updatedIntegration)).toBe('connected');
    expect(component.canConnect(updatedIntegration)).toBe(false);
  });

  it('should format brazilian phone when event carries +55 edge case', () => {
    expect(component.formatPhone('+55 (11) 98765-4321')).toBe('(11) 98765-4321');
  });

  it('should preserve readable international formatting for non-brazilian numbers', () => {
    expect(component.formatPhone('+14155552671')).toBe('+1 415 555 2671');
  });

  it('should not open delete modal for connected integration and should show toast', () => {
    const connectedIntegration = {
      id: 'int-8',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: true,
      connection_status: 'connected',
      has_token: true,
    } as Integration;

    const toastErrorSpy = vi.spyOn(toast, 'error').mockImplementation(() => '' as never);

    component.showDeleteModal.set(false);
    component.integrationToDelete.set(null);
    component.openDelete(connectedIntegration);

    expect(component.showDeleteModal()).toBe(false);
    expect(component.integrationToDelete()).toBeNull();
    expect(toastErrorSpy).toHaveBeenCalledWith(
      'Não é possível excluir uma integração conectada. Desconecte primeiro.',
    );
  });

  it('should open delete modal for disconnected integration', () => {
    const disconnectedIntegration = {
      id: 'int-9',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'disconnected',
      has_token: true,
    } as Integration;

    component.showDeleteModal.set(false);
    component.integrationToDelete.set(null);
    component.openDelete(disconnectedIntegration);

    expect(component.showDeleteModal()).toBe(true);
    expect(component.integrationToDelete()).toEqual(disconnectedIntegration);
  });

  it('should show specific toast and close modal when delete returns 409', () => {
    const integration = {
      id: 'int-10',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'disconnected',
      has_token: true,
    } as Integration;

    vi.spyOn(integrationService, 'delete').mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 409,
            statusText: 'Conflict',
          }),
      ),
    );

    const toastErrorSpy = vi.spyOn(toast, 'error').mockImplementation(() => '' as never);
    const loadIntegrationsSpy = vi
      .spyOn(component, 'loadIntegrations')
      .mockImplementation(() => {});

    component.integrationToDelete.set(integration);
    component.showDeleteModal.set(true);
    component.meta.set({ current_page: 3, last_page: 3, per_page: 15, total: 45 });

    component.handleDeleteConfirmed();

    expect(toastErrorSpy).toHaveBeenCalledWith(
      'Não é possível excluir uma integração conectada. Desconecte primeiro.',
    );
    expect(component.showDeleteModal()).toBe(false);
    expect(loadIntegrationsSpy).toHaveBeenCalledWith(3);
  });

  it('should keep modal open when delete fails with non-409 error', () => {
    const integration = {
      id: 'int-11',
      name: 'WhatsApp',
      provider: 'uazapi',
      is_connected: false,
      connection_status: 'disconnected',
      has_token: true,
    } as Integration;

    vi.spyOn(integrationService, 'delete').mockReturnValue(
      throwError(
        () =>
          new HttpErrorResponse({
            status: 500,
            statusText: 'Server Error',
          }),
      ),
    );

    const toastErrorSpy = vi.spyOn(toast, 'error').mockImplementation(() => '' as never);
    const loadIntegrationsSpy = vi
      .spyOn(component, 'loadIntegrations')
      .mockImplementation(() => {});

    component.integrationToDelete.set(integration);
    component.showDeleteModal.set(true);
    component.handleDeleteConfirmed();

    expect(toastErrorSpy).toHaveBeenCalledWith('Erro ao remover integração');
    expect(component.showDeleteModal()).toBe(true);
    expect(loadIntegrationsSpy).not.toHaveBeenCalled();
  });
});
