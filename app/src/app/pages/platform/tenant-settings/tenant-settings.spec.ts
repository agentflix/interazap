import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { TenantSettingsComponent } from './tenant-settings';
import { TenantSettingsService } from '@core/services/tenant-settings.service';
import { AuthStoreService, type AuthUser } from '@core/services/auth-store.service';
import { ToastService } from '@core/services/toast.service';
import type { TenantSettingsResponse } from '@shared/models/tenant-settings.model';

const mockSettings: TenantSettingsResponse = {
  data: {
    settings_localization: {
      timezone: 'America/Sao_Paulo',
      dateFormat: 'DD/MM/YYYY',
      timeFormat: '24h',
      currencyFormat: 'BRL',
    },
    settings_privacy: {
      presence: 'team',
      readReceipt: true,
      notificationPreview: false,
    },
  },
};

describe('TenantSettingsComponent', () => {
  let component: TenantSettingsComponent;
  let fixture: ComponentFixture<TenantSettingsComponent>;
  let settingsService: {
    getSettings: ReturnType<typeof vi.fn>;
    updateSettings: ReturnType<typeof vi.fn>;
  };
  let authStore: { user: ReturnType<typeof vi.fn> };
  let toastService: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    settingsService = {
      getSettings: vi.fn().mockReturnValue(of(mockSettings)),
      updateSettings: vi.fn().mockReturnValue(of(mockSettings)),
    };

    authStore = {
      user: vi.fn().mockReturnValue({ tenant_id: 'tenant-uuid-123' } as Partial<AuthUser>),
    };

    toastService = {
      success: vi.fn(),
      error: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [TenantSettingsComponent],
      providers: [
        { provide: TenantSettingsService, useValue: settingsService },
        { provide: AuthStoreService, useValue: authStore },
        { provide: ToastService, useValue: toastService },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(TenantSettingsComponent);
    component = fixture.componentInstance;
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  describe('loadSettings()', () => {
    it('should call getSettings with the tenant id from authStore', () => {
      fixture.detectChanges(); // triggers ngOnInit → loadSettings(); of() emits synchronously
      expect(settingsService.getSettings).toHaveBeenCalledWith('tenant-uuid-123');
    });

    it('should seed localization form with data from service', () => {
      fixture.detectChanges();
      expect(component.localizationForm.value.timezone).toBe('America/Sao_Paulo');
      expect(component.localizationForm.value.dateFormat).toBe('DD/MM/YYYY');
      expect(component.localizationForm.value.timeFormat).toBe('24h');
      expect(component.localizationForm.value.currencyFormat).toBe('BRL');
    });

    it('should seed privacy form with data from service', () => {
      fixture.detectChanges();
      expect(component.privacyForm.value.presence).toBe('team');
      expect(component.privacyForm.value.readReceipt).toBe(true);
      expect(component.privacyForm.value.notificationPreview).toBe(false);
    });

    it('should reset isLoading to false after successful load', () => {
      fixture.detectChanges();
      expect(component.isLoading()).toBe(false);
    });

    it('should set error and reset isLoading on service failure', () => {
      settingsService.getSettings.mockReturnValue(
        throwError(() => ({ error: { message: 'Erro de rede' } })),
      );
      fixture.detectChanges();
      expect(component.error()).toBe('Erro de rede');
      expect(component.isLoading()).toBe(false);
    });

    it('should set error when tenant_id is missing', () => {
      authStore.user.mockReturnValue(null);
      component.loadSettings();
      expect(component.error()).toBe('Não foi possível identificar o inquilino.');
      expect(settingsService.getSettings).not.toHaveBeenCalled();
    });
  });

  describe('save()', () => {
    beforeEach(() => {
      fixture.detectChanges(); // load settings synchronously via of() mock
    });

    it('should call updateSettings with the tenant id and form values', () => {
      component.save();
      expect(settingsService.updateSettings).toHaveBeenCalledWith(
        'tenant-uuid-123',
        expect.objectContaining({
          settings_localization: expect.objectContaining({ timezone: 'America/Sao_Paulo' }),
          settings_privacy: expect.objectContaining({ presence: 'team' }),
        }),
      );
    });

    it('should show success toast on save', () => {
      component.save();
      expect(toastService.success).toHaveBeenCalledWith('Configurações salvas com sucesso!');
    });

    it('should reset isSaving to false after successful save', () => {
      component.save();
      expect(component.isSaving()).toBe(false);
    });

    it('should set error and reset isSaving on save failure', () => {
      settingsService.updateSettings.mockReturnValue(
        throwError(() => ({ error: { message: 'Falha ao salvar' } })),
      );
      component.save();
      expect(component.error()).toBe('Falha ao salvar');
      expect(component.isSaving()).toBe(false);
    });

    it('should not call updateSettings when isSaving is already true', () => {
      component.isSaving.set(true);
      component.save();
      expect(settingsService.updateSettings).not.toHaveBeenCalled();
    });
  });
});
