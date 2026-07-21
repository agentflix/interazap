import { HttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { WindowVerificationService } from './window-verification.service';
import { type WindowStatus, type WindowStatusApiPayload } from 'src/app/core/models/window-status.model';

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
    const apiPayload: WindowStatusApiPayload = {
      canSendFreeText: true,
      lastMessageAt: '2026-04-11T10:00:00Z',
      expiresAt: null,
      windowType: null,
    };
    http.get.mockReturnValue(of({ data: apiPayload }));

    let result: WindowStatus | null = null;
    service.checkStatus('contact-1').subscribe((status) => {
      result = status;
    });

    expect(result).toEqual({
      canSendFreeText: true,
      lastMessageAt: new Date('2026-04-11T10:00:00Z'),
      expiresAt: null,
      windowType: null,
    });
    expect(http.get).toHaveBeenCalledWith(
      'https://api.interazap.com.br/api/chat/contacts/contact-1/window-status',
    );
  });

  it('checkStatus() parses expiresAt and windowType (72h CTWA) from API', () => {
    const apiPayload: WindowStatusApiPayload = {
      canSendFreeText: true,
      lastMessageAt: '2026-04-11T10:00:00Z',
      expiresAt: '2026-04-14T10:00:00Z',
      windowType: '72h',
    };
    http.get.mockReturnValue(of({ data: apiPayload }));

    let result!: WindowStatus;
    service.checkStatus('contact-1').subscribe((status) => {
      result = status;
    });

    expect(result.expiresAt).toEqual(new Date('2026-04-14T10:00:00Z'));
    expect(result.windowType).toBe('72h');
  });

  it('checkStatus() returns cached result within stale time', () => {
    const apiPayload: WindowStatusApiPayload = {
      canSendFreeText: true,
      lastMessageAt: new Date().toISOString(),
      expiresAt: null,
      windowType: null,
    };
    http.get.mockReturnValue(of({ data: apiPayload }));

    service.checkStatus('contact-1').subscribe();
    service.checkStatus('contact-1').subscribe();

    expect(http.get).toHaveBeenCalledTimes(1);
  });

  it('checkStatus() returns fallback (with null window fields) on error', () => {
    http.get.mockReturnValue(throwError(() => new Error('network')));

    let result: WindowStatus | null = null;
    service.checkStatus('contact-1').subscribe((status) => {
      result = status;
    });

    expect(result).toEqual({
      canSendFreeText: false,
      lastMessageAt: null,
      expiresAt: null,
      windowType: null,
    });
  });

  it('invalidateCache() removes cached entry', () => {
    const apiPayload: WindowStatusApiPayload = {
      canSendFreeText: true,
      lastMessageAt: new Date().toISOString(),
      expiresAt: null,
      windowType: null,
    };
    http.get.mockReturnValue(of({ data: apiPayload }));

    service.checkStatus('contact-1').subscribe();
    expect(http.get).toHaveBeenCalledTimes(1);

    service.invalidateCache('contact-1');
    service.checkStatus('contact-1').subscribe();

    expect(http.get).toHaveBeenCalledTimes(2);
  });

  it('clearCache() removes all cached entries', () => {
    const apiPayload: WindowStatusApiPayload = {
      canSendFreeText: true,
      lastMessageAt: new Date().toISOString(),
      expiresAt: null,
      windowType: null,
    };
    http.get.mockReturnValue(of({ data: apiPayload }));

    service.checkStatus('contact-1').subscribe();
    service.checkStatus('contact-2').subscribe();
    expect(http.get).toHaveBeenCalledTimes(2);

    service.clearCache();
    service.checkStatus('contact-1').subscribe();
    service.checkStatus('contact-2').subscribe();

    expect(http.get).toHaveBeenCalledTimes(4);
  });
});
