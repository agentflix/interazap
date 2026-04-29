import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { environment } from '@env/environment';
import { type ChatMessageTemplate } from '@core/models/chat-message-template.model';
import { ChatMessageTemplateService } from './chat-message-template.service';

const baseUrl = `${environment.apiUrl}/chat/message-templates`;

const sampleTemplate: ChatMessageTemplate = {
  id: 'tpl-1',
  name: 'boas_vindas',
  shortcut: '/boas-vindas',
  content: null,
  category: 'UTILITY',
  is_active: true,
  provider: 'meta',
  chat_instance_id: 'ci-1',
  external_id: 'ext-1',
  language: 'pt_BR',
  status: 'approved',
  rejected_reason: null,
  components: [{ type: 'BODY', text: 'Olá {{1}}' }],
  last_synced_at: '2026-04-29T10:00:00Z',
};

describe('ChatMessageTemplateService', () => {
  let service: ChatMessageTemplateService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(ChatMessageTemplateService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  describe('list', () => {
    it('faz GET no endpoint sem filtros', () => {
      service.list().subscribe();
      const req = httpMock.expectOne(baseUrl);
      expect(req.request.method).toBe('GET');
      req.flush({
        data: [sampleTemplate],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
      });
    });

    it('aplica filtros em query params', () => {
      service
        .list({
          search: 'boas',
          status: 'pending',
          chat_instance_id: 'ci-1',
          page: 2,
          per_page: 30,
        })
        .subscribe();

      const req = httpMock.expectOne(
        (r) =>
          r.url === baseUrl &&
          r.params.get('search') === 'boas' &&
          r.params.get('status') === 'pending' &&
          r.params.get('chat_instance_id') === 'ci-1' &&
          r.params.get('page') === '2' &&
          r.params.get('per_page') === '30',
      );
      req.flush({ data: [], meta: { current_page: 2, last_page: 2, per_page: 30, total: 0 } });
    });
  });

  describe('get', () => {
    it('mapeia response.data para o template', () => {
      let received: ChatMessageTemplate | null = null;
      service.get('tpl-1').subscribe((t) => (received = t));

      const req = httpMock.expectOne(`${baseUrl}/tpl-1`);
      expect(req.request.method).toBe('GET');
      req.flush({ data: sampleTemplate });

      expect(received).toEqual(sampleTemplate);
    });
  });

  describe('create', () => {
    it('faz POST com payload e mapeia data', () => {
      let received: ChatMessageTemplate | null = null;
      service
        .create({ name: 'novo', language: 'pt_BR', category: 'UTILITY' })
        .subscribe((t) => (received = t));

      const req = httpMock.expectOne(baseUrl);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({ name: 'novo', language: 'pt_BR', category: 'UTILITY' });
      req.flush({ data: sampleTemplate });

      expect(received).toEqual(sampleTemplate);
    });
  });

  describe('update', () => {
    it('faz PUT no recurso e mapeia data', () => {
      let received: ChatMessageTemplate | null = null;
      service.update('tpl-1', { is_active: false }).subscribe((t) => (received = t));

      const req = httpMock.expectOne(`${baseUrl}/tpl-1`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual({ is_active: false });
      req.flush({ data: { ...sampleTemplate, is_active: false } });

      expect(received).toMatchObject({ id: 'tpl-1', is_active: false });
    });
  });

  describe('delete', () => {
    it('faz DELETE no recurso', () => {
      let completed = false;
      service.delete('tpl-1').subscribe({ complete: () => (completed = true) });

      const req = httpMock.expectOne(`${baseUrl}/tpl-1`);
      expect(req.request.method).toBe('DELETE');
      req.flush(null);

      expect(completed).toBe(true);
    });
  });

  describe('sync', () => {
    it('faz POST em /sync com chat_instance_id e mapeia count', () => {
      let received: { count: number } | null = null;
      service.sync('ci-1').subscribe((r) => (received = r));

      const req = httpMock.expectOne(`${baseUrl}/sync`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({ chat_instance_id: 'ci-1' });
      req.flush({ data: { count: 7 } });

      expect(received).toEqual({ count: 7 });
    });
  });
});
