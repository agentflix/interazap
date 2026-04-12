import { HttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { WindowVerificationService } from './window-verification.service';
import { type WindowStatus } from 'src/app/core/models/window-status.model';

class HttpClientStub {
  get = vi.fn();
}

describe('WindowVerificationService', () => {
  let service: WindowVerificationService;
  let http: HttpClientStub;

  beforeEach(async () => {
    http = new HttpClientStub();

    await TestBed.configureTestingModule({
      providers: [
        WindowVerificationService,
        { provide: HttpClient, useValue: http },
      ],
    }).compileComponents();

    service = TestBed.inject(WindowVerificationService);
  });

  afterEach(() => {
    vi.restoreAllMocks();
    service.clearCache();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  it('checkStatus() returns WindowStatus from API', () => {
    const mockStatus: WindowStatus = {
      canSendFreeText: true,
      lastMessageAt: new Date('2026-04-11T10:00:00Z'),
    };
    http.get.mockReturnValue(of({ data: mockStatus }));

    let result: WindowStatus | null = null;
    service.checkStatus('contact-1').subscribe((status) => {
      result = status;
    });

    expect(result).toEqual(mockStatus);
    expect(http.get).toHaveBeenCalledWith('/api/chat/contacts/contact-1/window-status');
  });

  it('checkStatus() returns cached result within stale time', () => {
    const mockStatus: WindowStatus = {
      canSendFreeText: true,
      lastMessageAt: new Date(),
    };
    http.get.mockReturnValue(of({ data: mockStatus }));

    service.checkStatus('contact-1').subscribe();
    service.checkStatus('contact-1').subscribe();

    expect(http.get).toHaveBeenCalledTimes(1);
  });

  it('checkStatus() returns fallback on error', () => {
    http.get.mockReturnValue(throwError(() => new Error('network')));

    let result: WindowStatus | null = null;
    service.checkStatus('contact-1').subscribe((status) => {
      result = status;
    });

    expect(result).toEqual({
      canSendFreeText: false,
      lastMessageAt: null,
    });
  });

  it('invalidateCache() removes cached entry', () => {
    const mockStatus: WindowStatus = {
      canSendFreeText: true,
      lastMessageAt: new Date(),
    };
    http.get.mockReturnValue(of({ data: mockStatus }));

    service.checkStatus('contact-1').subscribe();
    expect(http.get).toHaveBeenCalledTimes(1);

    service.invalidateCache('contact-1');
    service.checkStatus('contact-1').subscribe();

    expect(http.get).toHaveBeenCalledTimes(2);
  });

  it('clearCache() removes all cached entries', () => {
    const mockStatus: WindowStatus = {
      canSendFreeText: true,
      lastMessageAt: new Date(),
    };
    http.get.mockReturnValue(of({ data: mockStatus }));

    service.checkStatus('contact-1').subscribe();
    service.checkStatus('contact-2').subscribe();
    expect(http.get).toHaveBeenCalledTimes(1);

    service.clearCache();
    service.checkStatus('contact-1').subscribe();
    service.checkStatus('contact-2').subscribe();

    expect(http.get).toHaveBeenCalledTimes(3);
  });
});
