import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { environment } from '@env/environment';
import {
  ChatRoutingQueueService,
  type ChatRoutingQueue,
  type ChatRoutingQueueAgent,
} from './chat-routing-queue.service';

const globalBaseUrl = `${environment.apiUrl}/chat/routing-queue/global`;
const channelBaseUrl = `${environment.apiUrl}/chat/channels`;

const sampleAgent: ChatRoutingQueueAgent = {
  id: 'agent-1',
  queue_id: 'queue-1',
  user_id: 'user-1',
  position: 1,
  last_assigned_at: null,
  is_active: true,
  created_at: '2026-05-01T00:00:00Z',
  updated_at: '2026-05-01T00:00:00Z',
};

const sampleQueue: ChatRoutingQueue = {
  id: 'queue-1',
  tenant_id: 'tenant-1',
  instance_id: null,
  name: 'Fila Global',
  is_enabled: true,
  strategy: 'round_robin',
  max_open_tickets_per_agent: 5,
  agents: [sampleAgent],
  created_at: '2026-05-01T00:00:00Z',
  updated_at: '2026-05-01T00:00:00Z',
};

describe('ChatRoutingQueueService', () => {
  let service: ChatRoutingQueueService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    service = TestBed.inject(ChatRoutingQueueService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  describe('loadGlobal', () => {
    it('faz GET no endpoint global e atualiza queue e agents', () => {
      expect(service.queue()).toBeNull();
      expect(service.agents()).toEqual([]);

      service.loadGlobal();
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      expect(req.request.method).toBe('GET');
      req.flush({ data: sampleQueue });

      expect(service.queue()).toEqual(sampleQueue);
      expect(service.agents()).toEqual([sampleAgent]);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('não seta error em caso de 404 (fila ainda não existe)', () => {
      service.loadGlobal();
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      req.flush('Not Found', { status: 404, statusText: 'Not Found' });

      expect(service.queue()).toBeNull();
      expect(service.agents()).toEqual([]);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('atualiza error em caso de falha', () => {
      service.loadGlobal();
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.queue()).toBeNull();
      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao carregar fila global de roteamento.');
    });
  });

  describe('loadForChannel', () => {
    it('faz GET no endpoint do canal e atualiza queue e agents', () => {
      service.loadForChannel('channel-1');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${channelBaseUrl}/channel-1/routing-queue`);
      expect(req.request.method).toBe('GET');
      req.flush({ data: sampleQueue });

      expect(service.queue()).toEqual(sampleQueue);
      expect(service.agents()).toEqual([sampleAgent]);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('não seta error em caso de 404 (fila ainda não existe)', () => {
      service.loadForChannel('channel-1');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${channelBaseUrl}/channel-1/routing-queue`);
      req.flush('Not Found', { status: 404, statusText: 'Not Found' });

      expect(service.queue()).toBeNull();
      expect(service.agents()).toEqual([]);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('atualiza error em caso de falha', () => {
      service.loadForChannel('channel-1');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${channelBaseUrl}/channel-1/routing-queue`);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.queue()).toBeNull();
      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao carregar fila de roteamento do canal.');
    });
  });

  describe('save', () => {
    it('faz POST quando queue é null', () => {
      expect(service.queue()).toBeNull();

      service.save('global', { name: 'Nova Fila' });
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({ name: 'Nova Fila' });
      req.flush({ data: sampleQueue });

      expect(service.queue()).toEqual(sampleQueue);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('faz PUT quando queue já existe', () => {
      service.queue.set(sampleQueue);

      service.save('global', { name: 'Fila Atualizada' });
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual({ name: 'Fila Atualizada' });
      req.flush({ data: { ...sampleQueue, name: 'Fila Atualizada' } });

      expect(service.queue()?.name).toBe('Fila Atualizada');
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('usa URL de canal quando scope é channel', () => {
      service.save('channel', { name: 'Fila Canal' }, 'channel-1');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${channelBaseUrl}/channel-1/routing-queue`);
      expect(req.request.method).toBe('POST');
      req.flush({ data: sampleQueue });

      expect(service.queue()).toEqual(sampleQueue);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('atualiza error em caso de falha no POST', () => {
      service.save('global', { name: 'Nova Fila' });
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao salvar fila de roteamento.');
    });

    it('atualiza error em caso de falha no PUT', () => {
      service.queue.set(sampleQueue);

      service.save('global', { name: 'Fila Atualizada' });
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(globalBaseUrl);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao salvar fila de roteamento.');
    });
  });

  describe('addAgent', () => {
    it('faz POST em /agents e adiciona ao signal agents', () => {
      service.queue.set(sampleQueue);
      service.agents.set([sampleAgent]);

      const newAgent: ChatRoutingQueueAgent = {
        ...sampleAgent,
        id: 'agent-2',
        user_id: 'user-2',
        position: 2,
      };

      service.addAgent('global', 'user-2', 2);
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents`);
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({ user_id: 'user-2', position: 2 });
      req.flush({ data: newAgent });

      expect(service.agents()).toHaveLength(2);
      expect(service.agents()[1]).toEqual(newAgent);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('aceita chamada sem position', () => {
      service.queue.set(sampleQueue);
      service.agents.set([sampleAgent]);

      service.addAgent('global', 'user-3');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents`);
      expect(req.request.body).toEqual({ user_id: 'user-3' });
      req.flush({ data: { ...sampleAgent, id: 'agent-3', user_id: 'user-3' } });

      expect(service.agents()).toHaveLength(2);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('atualiza error em caso de falha', () => {
      service.queue.set(sampleQueue);
      service.agents.set([sampleAgent]);

      service.addAgent('global', 'user-2');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents`);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao adicionar agente à fila.');
    });
  });

  describe('removeAgent', () => {
    it('faz DELETE em /agents/{userId} e remove do signal agents', () => {
      service.queue.set(sampleQueue);
      service.agents.set([sampleAgent]);

      service.removeAgent('global', 'user-1');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents/user-1`);
      expect(req.request.method).toBe('DELETE');
      req.flush(null);

      expect(service.agents()).toHaveLength(0);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('atualiza error em caso de falha', () => {
      service.queue.set(sampleQueue);
      service.agents.set([sampleAgent]);

      service.removeAgent('global', 'user-1');
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents/user-1`);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao remover agente da fila.');
    });
  });

  describe('reorder', () => {
    it('faz PUT em /agents/reorder e atualiza signal agents', () => {
      service.queue.set(sampleQueue);
      service.agents.set([
        { ...sampleAgent, user_id: 'user-a', position: 1 },
        { ...sampleAgent, user_id: 'user-b', position: 2 },
      ]);

      const reordered = [
        { ...sampleAgent, user_id: 'user-b', position: 1 },
        { ...sampleAgent, user_id: 'user-a', position: 2 },
      ];

      service.reorder('global', [
        { user_id: 'user-b', position: 1 },
        { user_id: 'user-a', position: 2 },
      ]);
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents/reorder`);
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual({
        agents: [
          { user_id: 'user-b', position: 1 },
          { user_id: 'user-a', position: 2 },
        ],
      });
      req.flush({ data: reordered });

      expect(service.agents()).toEqual(reordered);
      expect(service.loading()).toBe(false);
      expect(service.error()).toBeNull();
    });

    it('atualiza error em caso de falha', () => {
      service.queue.set(sampleQueue);
      service.agents.set([
        { ...sampleAgent, user_id: 'user-a', position: 1 },
        { ...sampleAgent, user_id: 'user-b', position: 2 },
      ]);

      service.reorder('global', [
        { user_id: 'user-b', position: 1 },
        { user_id: 'user-a', position: 2 },
      ]);
      expect(service.loading()).toBe(true);

      const req = httpMock.expectOne(`${globalBaseUrl}/agents/reorder`);
      req.flush('Erro', { status: 500, statusText: 'Internal Server Error' });

      expect(service.loading()).toBe(false);
      expect(service.error()).toBe('Erro ao reordenar agentes da fila.');
    });
  });
});
