import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule } from '@angular/forms';
import { type Observable, of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { PreferencesComponent } from './preferences';
import { PreferencesStore } from '@core/services/preferences.store';
import { PreferencesService } from '@core/services/preferences.service';
import { NotificationPreferencesService } from '@core/services/notification-preferences.service';
import { ThemeService } from '@core/services/theme.service';
import { ToastService } from '@core/services/toast.service';
import { TenantSettingsService } from '@core/services/tenant-settings.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import type {
  UserPreferences,
  NotificationPreferencesResponse,
} from '@shared/models/preferences.model';
import type { TenantSettingsResponse } from '@shared/models/tenant-settings.model';

// Mock service that doesn't use HttpClient
class MockPreferencesService {
  getPreferences(): Observable<{ data: UserPreferences | null }> {
    return of({ data: null });
  }
  updatePreferences(): Observable<{ data: UserPreferences }> {
    return of({ data: null as unknown as UserPreferences });
  }
}

// Mock for notification preferences — avoids HttpClient dependency in tests
class MockNotificationPreferencesService {
  getPreferences(): Observable<NotificationPreferencesResponse> {
    return of({ data: [], types: {}, channels: {} });
  }
  updateAllPreferences(): Observable<{ data: [] }> {
    return of({ data: [] });
  }
}

// Mock for tenant settings service
class MockTenantSettingsService {
  getSettings(_tenantId: string): Observable<TenantSettingsResponse> {
    return of({
      data: {
        settings_localization: {
          timezone: 'America/Sao_Paulo',
          dateFormat: 'DD/MM/YYYY',
          timeFormat: '24h',
          currencyFormat: 'BRL',
        },
        settings_privacy: {
          presence: 'all',
          readReceipt: true,
          notificationPreview: true,
        },
        settings_chat: {
          auto_close_inactivity_enabled: false,
          auto_close_inactivity_minutes: 30,
          auto_close_inactivity_target: 'both',
          auto_close_inactivity_message: 'Este atendimento foi encerrado automaticamente por inatividade.',
        },
      },
    });
  }
  updateSettings(_tenantId: string, _data: Partial<TenantSettingsResponse['data']>): Observable<TenantSettingsResponse> {
    return of({
      data: {
        settings_localization: { timezone: 'America/Sao_Paulo', dateFormat: 'DD/MM/YYYY', timeFormat: '24h', currencyFormat: 'BRL' },
        settings_privacy: { presence: 'all', readReceipt: true, notificationPreview: true },
        settings_chat: { auto_close_inactivity_enabled: false, auto_close_inactivity_minutes: 30, auto_close_inactivity_target: 'both', auto_close_inactivity_message: '' },
      },
    });
  }
}

/**
 * Create a mock AuthStoreService class with controllable permission.
 * Returns a class (not instance) so TestBed can instantiate it.
 */
function createMockAuthStoreClass(hasPermission: boolean) {
  return class {
    hasPermission(_permission: string): boolean { return hasPermission; }
    user() { return { tenant_id: 'test-tenant-uuid', permissions: [] }; }
  };
}

// ── Tests without tenant permission ──────────────────────────────────────

describe('PreferencesComponent — sem permissão de tenant', () => {
  let component: PreferencesComponent;
  let fixture: ComponentFixture<PreferencesComponent>;
  let store: PreferencesStore;
  let mockTenantSettings: MockTenantSettingsService;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReactiveFormsModule, PreferencesComponent],
      providers: [
        { provide: PreferencesService, useClass: MockPreferencesService },
        { provide: NotificationPreferencesService, useClass: MockNotificationPreferencesService },
        PreferencesStore,
        ThemeService,
        ToastService,
        { provide: TenantSettingsService, useClass: MockTenantSettingsService },
        { provide: AuthStoreService, useClass: createMockAuthStoreClass(false) },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PreferencesComponent);
    component = fixture.componentInstance;
    store = TestBed.inject(PreferencesStore);
    mockTenantSettings = TestBed.inject(TenantSettingsService) as unknown as MockTenantSettingsService;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('hasTenantPermission deve ser false', () => {
    expect(component.hasTenantPermission()).toBe(false);
  });

  it('should have all notification type options', () => {
    expect(component.notificationTypes.length).toBeGreaterThan(0);
  });

  it('should have all notification channel options', () => {
    expect(component.notificationChannels.length).toBeGreaterThan(0);
  });

  it('should have notificationControls for each type × channel combination', () => {
    const expectedCount =
      component.notificationTypes.length * component.notificationChannels.length;
    expect(Object.keys(component.notificationControls).length).toBe(expectedCount);
  });

  it('should mark store dirty when appearance theme changes', () => {
    component.appearanceForm.get('theme')?.setValue('light');
    expect(store.isDirty()).toBe(true);
  });

  it('should mark store dirty when behavior sound changes', () => {
    component.behaviorForm.get('sound')?.setValue(false);
    expect(store.isDirty()).toBe(true);
  });

  it('should mark store dirty when crm pipeline view changes', () => {
    component.crmDefaultsForm.get('pipelineView')?.setValue('list');
    expect(store.isDirty()).toBe(true);
  });

  it('should mark store dirty when accessibility reducedMotion changes', () => {
    component.accessibilityForm.get('reducedMotion')?.setValue(true);
    expect(store.isDirty()).toBe(true);
  });

  it('save() não deve chamar tenantSettingsService.updateSettings()', () => {
    const spy = vi.spyOn(mockTenantSettings, 'updateSettings');
    component.save();
    expect(spy).not.toHaveBeenCalled();
  });
});

// ── Tests with tenant permission ─────────────────────────────────────────

describe('PreferencesComponent — com permissão de tenant', () => {
  let component: PreferencesComponent;
  let fixture: ComponentFixture<PreferencesComponent>;
  let store: PreferencesStore;
  let mockTenantSettings: MockTenantSettingsService;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReactiveFormsModule, PreferencesComponent],
      providers: [
        { provide: PreferencesService, useClass: MockPreferencesService },
        { provide: NotificationPreferencesService, useClass: MockNotificationPreferencesService },
        PreferencesStore,
        ThemeService,
        ToastService,
        { provide: TenantSettingsService, useClass: MockTenantSettingsService },
        { provide: AuthStoreService, useClass: createMockAuthStoreClass(true) },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PreferencesComponent);
    component = fixture.componentInstance;
    store = TestBed.inject(PreferencesStore);
    mockTenantSettings = TestBed.inject(TenantSettingsService) as unknown as MockTenantSettingsService;
    fixture.detectChanges();
  });

  it('hasTenantPermission deve ser true', () => {
    expect(component.hasTenantPermission()).toBe(true);
  });

  it('localizationForm deve ser populado após load', () => {
    expect(component.localizationForm.value.timezone).toBe('America/Sao_Paulo');
    expect(component.localizationForm.value.dateFormat).toBe('DD/MM/YYYY');
    expect(component.localizationForm.value.timeFormat).toBe('24h');
    expect(component.localizationForm.value.currencyFormat).toBe('BRL');
  });

  it('privacyForm deve ser populado após load', () => {
    expect(component.privacyForm.value.presence).toBe('all');
    expect(component.privacyForm.value.readReceipt).toBe(true);
    expect(component.privacyForm.value.notificationPreview).toBe(true);
  });

  it('chatForm deve ser populado após load', () => {
    expect(component.chatForm.value.auto_close_inactivity_enabled).toBe(false);
    expect(component.chatForm.value.auto_close_inactivity_minutes).toBe(30);
  });

  it('save() com permissão deve chamar tenantSettingsService.updateSettings()', () => {
    const spy = vi.spyOn(mockTenantSettings, 'updateSettings');
    component.save();
    expect(spy).toHaveBeenCalled();
  });

  it('forms de tenant devem marcar store como dirty', () => {
    component.localizationForm.get('timezone')?.setValue('UTC');
    expect(store.isDirty()).toBe(true);
  });

  it('reset() com permissão deve recarregar tenant settings', () => {
    // Change a value
    component.localizationForm.get('timezone')?.setValue('UTC');
    expect(component.localizationForm.value.timezone).toBe('UTC');
    // Reset should reload
    component.reset();
    expect(component.localizationForm.value.timezone).toBe('America/Sao_Paulo');
  });
});
