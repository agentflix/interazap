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
import type {
  UserPreferences,
  NotificationPreferencesResponse,
} from '@shared/models/preferences.model';

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

describe('PreferencesComponent', () => {
  let component: PreferencesComponent;
  let fixture: ComponentFixture<PreferencesComponent>;
  let store: PreferencesStore;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ReactiveFormsModule, PreferencesComponent],
      providers: [
        { provide: PreferencesService, useClass: MockPreferencesService },
        { provide: NotificationPreferencesService, useClass: MockNotificationPreferencesService },
        PreferencesStore,
        ThemeService,
        ToastService,
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PreferencesComponent);
    component = fixture.componentInstance;
    store = TestBed.inject(PreferencesStore);
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
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
});
