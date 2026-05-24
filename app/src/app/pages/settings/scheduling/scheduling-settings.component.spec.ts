import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SchedulingSettingsComponent } from './scheduling-settings.component';
import { SchedulingSettingsService } from '@core/services/configuration/scheduling-setting.service';
import { ToastService } from '@core/services/toast.service';
import type { SchedulingSettingsResponse } from '@core/models/configuration/scheduling-setting.model';

const mockSettings: SchedulingSettingsResponse = {
  success: true,
  message: 'Success',
  data: {
    id: 'test-id',
    tenant_id: 'test-tenant',
    event_confirmation_advance_minutes: 1440,
    event_confirmation_enabled: true,
    event_confirmation_notify_ui: true,
    event_confirmation_notify_push: true,
    created_at: '2026-05-18T10:00:00Z',
    updated_at: '2026-05-18T10:00:00Z',
  },
};

describe('SchedulingSettingsComponent', () => {
  let component: SchedulingSettingsComponent;
  let fixture: ComponentFixture<SchedulingSettingsComponent>;
  let settingsService: {
    getSettings: ReturnType<typeof vi.fn>;
    updateSettings: ReturnType<typeof vi.fn>;
  };
  let toastService: { success: ReturnType<typeof vi.fn>; error: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    settingsService = {
      getSettings: vi.fn().mockReturnValue(of(mockSettings)),
      updateSettings: vi.fn().mockReturnValue(of(mockSettings)),
    };

    toastService = {
      success: vi.fn(),
      error: vi.fn(),
    };

    await TestBed.configureTestingModule({
      imports: [SchedulingSettingsComponent],
      providers: [
        { provide: SchedulingSettingsService, useValue: settingsService },
        { provide: ToastService, useValue: toastService },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(SchedulingSettingsComponent);
    component = fixture.componentInstance;
  });

  describe('loadSettings()', () => {
    it('should load current settings on init', () => {
      fixture.detectChanges();
      expect(settingsService.getSettings).toHaveBeenCalled();
      expect(component.form.controls.event_confirmation_advance_minutes.value).toBe(1440);
      expect(component.form.controls.event_confirmation_enabled.value).toBe(true);
      expect(component.isLoading()).toBe(false);
    });

    it('should set error on failure', () => {
      settingsService.getSettings.mockReturnValue(
        throwError(() => ({ error: { message: 'Erro de rede' } })),
      );
      fixture.detectChanges();
      expect(component.error()).toBe('Erro de rede');
      expect(component.isLoading()).toBe(false);
    });
  });

  describe('save()', () => {
    beforeEach(() => {
      fixture.detectChanges();
    });

    it('should call updateSettings with form values', () => {
      component.save();
      expect(settingsService.updateSettings).toHaveBeenCalledWith({
        event_confirmation_advance_minutes: 1440,
        event_confirmation_enabled: true,
        event_confirmation_notify_ui: true,
        event_confirmation_notify_push: true,
      });
    });

    it('should show success toast on save', () => {
      component.save();
      expect(toastService.success).toHaveBeenCalledWith('Configurações de agendamento salvas com sucesso!');
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

  describe('isEnabled derived signal', () => {
    it('should be true when enabled via API', () => {
      fixture.detectChanges();
      expect(component.isEnabled()).toBe(true);
    });

    it('should react to form toggle changes', () => {
      fixture.detectChanges();
      component.form.controls.event_confirmation_enabled.setValue(false);
      fixture.detectChanges();
      expect(component.isEnabled()).toBe(false);
    });
  });
});
