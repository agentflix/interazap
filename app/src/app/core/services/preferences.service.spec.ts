import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { environment } from '@env/environment';
import { PreferencesService } from './preferences.service';
import type { UserPreferencesResponse } from '@shared/models/preferences.model';

describe('PreferencesService', () => {
  let service: PreferencesService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), PreferencesService],
    });

    service = TestBed.inject(PreferencesService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  describe('getPreferences', () => {
    it('should call GET /auth/profile/preferences', () => {
      const mockResponse: UserPreferencesResponse = {
        data: {
          appearance: { theme: 'dark', density: 'normal', fontSize: 'medium' },
          behavior: {
            sound: true,
            chatNotify: false,
            quickReply: true,
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
        },
      };

      service.getPreferences().subscribe((response) => {
        expect(response.data.appearance.theme).toBe('dark');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/profile/preferences`);
      expect(req.request.method).toBe('GET');
      req.flush(mockResponse);
      httpMock.verify();
    });
  });

  describe('updatePreferences', () => {
    it('should call PATCH /auth/profile/preferences with partial data', () => {
      const mockResponse: UserPreferencesResponse = {
        data: {
          appearance: { theme: 'dark', density: 'normal', fontSize: 'medium' },
          behavior: {
            sound: true,
            chatNotify: false,
            quickReply: true,
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
        },
      };

      const payload = {
        appearance: {
          theme: 'dark' as const,
          density: 'normal' as const,
          fontSize: 'medium' as const,
        },
        behavior: {
          sound: true,
          chatNotify: true,
          quickReply: false,
          confirmBulk: true,
          ticketOpenMode: 'modal' as const,
        },
        crmDefaults: {
          negotiationType: 'basic' as const,
          taskStatus: 'pending' as const,
          pipelineView: 'kanban' as const,
          negotiationOrder: 'date' as const,
        },
        security: { sessionTimeout: 60 },
        accessibility: { highContrast: false, reducedMotion: false },
      };

      service.updatePreferences(payload).subscribe((response) => {
        expect(response.data.appearance.theme).toBe('dark');
      });

      const req = httpMock.expectOne(`${environment.apiUrl}/auth/profile/preferences`);
      expect(req.request.method).toBe('PATCH');
      expect(req.request.body).toEqual(payload);
      req.flush(mockResponse);
      httpMock.verify();
    });
  });
});
