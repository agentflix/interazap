import { TestBed } from '@angular/core/testing';
import { type Observable, of, Subject, throwError } from 'rxjs';
import { beforeEach, describe, expect, it } from 'vitest';
import { PreferencesStore } from './preferences.store';
import { PreferencesService } from './preferences.service';
import type { UserPreferences } from '@shared/models/preferences.model';

/**
 * Unit tests for PreferencesStore signal-based state management.
 * Observable/HTTP interactions are covered by integration tests.
 */

// Mock service that doesn't use HttpClient
class MockPreferencesService {
  getPreferences(): Observable<{ data: UserPreferences | null }> {
    return of({ data: null });
  }
  updatePreferences(): Observable<{ data: UserPreferences }> {
    return of({ data: null as unknown as UserPreferences });
  }
}

/**
 * Configurable closure-based mock — each test can override the implementations
 * before calling store.load() / store.save(), avoiding TestBed.overrideProvider
 * (which cannot be called after the test module has been instantiated).
 */
let mockGetPreferencesImpl: () => Observable<{ data: UserPreferences | null }>;
let mockUpdatePreferencesImpl: (prefs: UserPreferences) => Observable<{ data: UserPreferences }>;

class ConfigurableMockPreferencesService {
  getPreferences(): Observable<{ data: UserPreferences | null }> {
    return mockGetPreferencesImpl();
  }
  updatePreferences(prefs: UserPreferences): Observable<{ data: UserPreferences }> {
    return mockUpdatePreferencesImpl(prefs);
  }
}

const makePrefs = (theme: 'light' | 'dark' | 'system' = 'dark'): UserPreferences => ({
  appearance: { theme, density: 'normal', fontSize: 'medium' },
  behavior: {
    sound: true,
    chatNotify: true,
    quickReply: false,
    confirmBulk: true,
    ticketOpenMode: 'modal',
  },
  crmDefaults: {
    negotiationType: 'basic',
    taskStatus: 'pending',
    pipelineView: 'kanban',
    negotiationOrder: 'date',
  },
  security: { sessionTimeout: 60 },
  accessibility: { highContrast: false, reducedMotion: false },
});

describe('PreferencesStore', () => {
  let store: PreferencesStore;

  beforeEach(() => {
    // Reset default mock implementations before each test
    mockGetPreferencesImpl = () => of({ data: null });
    mockUpdatePreferencesImpl = () => of({ data: null as unknown as UserPreferences });

    TestBed.configureTestingModule({
      providers: [
        { provide: PreferencesService, useClass: ConfigurableMockPreferencesService },
        PreferencesStore,
      ],
    });
    store = TestBed.inject(PreferencesStore);
  });

  describe('initial state', () => {
    it('should have preferences as null initially', () => {
      expect(store.preferences()).toBeNull();
    });

    it('should have isDirty as false initially', () => {
      expect(store.isDirty()).toBe(false);
    });

    it('should have isSaving as false initially', () => {
      expect(store.isSaving()).toBe(false);
    });

    it('should have isLoading as false initially', () => {
      expect(store.isLoading()).toBe(false);
    });

    it('should have error as null initially', () => {
      expect(store.error()).toBeNull();
    });
  });

  describe('isDirty', () => {
    it('should become true after markDirty()', () => {
      store.markDirty();
      expect(store.isDirty()).toBe(true);
    });

    it('should haveUnsavedChanges return same value as isDirty', () => {
      expect(store.hasUnsavedChanges()).toBe(store.isDirty());
      store.markDirty();
      expect(store.hasUnsavedChanges()).toBe(store.isDirty());
    });
  });

  describe('markDirty()', () => {
    it('should set isDirty to true', () => {
      expect(store.isDirty()).toBe(false);
      store.markDirty();
      expect(store.isDirty()).toBe(true);
    });

    it('should be idempotent', () => {
      store.markDirty();
      store.markDirty();
      expect(store.isDirty()).toBe(true);
    });
  });

  describe('hasUnsavedChanges', () => {
    it('should return false when not dirty', () => {
      expect(store.hasUnsavedChanges()).toBe(false);
    });

    it('should return true when dirty', () => {
      store.markDirty();
      expect(store.hasUnsavedChanges()).toBe(true);
    });
  });

  // ── load() ────────────────────────────────────────────────────────────────

  describe('load()', () => {
    it('should set isLoading to true while the request is in flight', () => {
      const subject = new Subject<{ data: UserPreferences }>();
      // Set mock BEFORE calling load() — closure var is read when subscription fires
      mockGetPreferencesImpl = () => subject.asObservable();
      const freshStore = TestBed.inject(PreferencesStore);

      freshStore.load();
      expect(freshStore.isLoading()).toBe(true);

      // Subject.next() is synchronous — callback runs immediately, no tick() needed
      subject.next({ data: makePrefs() });
      subject.complete();

      expect(freshStore.isLoading()).toBe(false);
    });

    it('should set preferences signal from the API response', () => {
      const prefs = makePrefs('light');
      mockGetPreferencesImpl = () => of({ data: prefs });
      const freshStore = TestBed.inject(PreferencesStore);

      freshStore.load(); // of() emits synchronously

      expect(freshStore.preferences()?.appearance.theme).toBe('light');
    });

    it('should reset isDirty to false after successful load', () => {
      mockGetPreferencesImpl = () => of({ data: makePrefs() });
      const freshStore = TestBed.inject(PreferencesStore);
      freshStore.markDirty();

      freshStore.load();

      expect(freshStore.isDirty()).toBe(false);
    });

    it('should set error and reset isLoading on failure', () => {
      mockGetPreferencesImpl = () => throwError(() => ({ error: { message: 'Falha de rede' } }));
      const freshStore = TestBed.inject(PreferencesStore);

      freshStore.load();

      expect(freshStore.error()).toBe('Falha de rede');
      expect(freshStore.isLoading()).toBe(false);
    });
  });

  // ── save() ────────────────────────────────────────────────────────────────

  describe('save()', () => {
    it('should set isSaving to true while saving and reset to false on success', () => {
      const subject = new Subject<{ data: UserPreferences }>();
      mockUpdatePreferencesImpl = () => subject.asObservable();
      const freshStore = TestBed.inject(PreferencesStore);

      freshStore.save(makePrefs());
      expect(freshStore.isSaving()).toBe(true);

      // Subject.next() is synchronous — callback runs immediately, no tick() needed
      subject.next({ data: makePrefs() });
      subject.complete();

      expect(freshStore.isSaving()).toBe(false);
    });

    it('should reset isDirty to false after successful save', () => {
      mockUpdatePreferencesImpl = () => of({ data: makePrefs() });
      const freshStore = TestBed.inject(PreferencesStore);
      freshStore.markDirty();

      freshStore.save(makePrefs());

      expect(freshStore.isDirty()).toBe(false);
    });

    it('should preserve isDirty as true when save fails', () => {
      mockUpdatePreferencesImpl = () =>
        throwError(() => ({ error: { message: 'Erro ao salvar' } }));
      const freshStore = TestBed.inject(PreferencesStore);
      freshStore.markDirty();

      freshStore.save(makePrefs());

      expect(freshStore.isDirty()).toBe(true);
    });

    it('should set error when save fails', () => {
      mockUpdatePreferencesImpl = () =>
        throwError(() => ({ error: { message: 'Erro ao salvar' } }));
      const freshStore = TestBed.inject(PreferencesStore);

      freshStore.save(makePrefs());

      expect(freshStore.error()).toBe('Erro ao salvar');
      expect(freshStore.isSaving()).toBe(false);
    });

    it('should not save again when isSaving is already true', () => {
      let callCount = 0;
      mockUpdatePreferencesImpl = () => {
        callCount++;
        return of({ data: makePrefs() });
      };
      const freshStore = TestBed.inject(PreferencesStore);
      freshStore.isSaving.set(true);

      freshStore.save(makePrefs());

      expect(callCount).toBe(0);
    });
  });
});
