import { describe, it, expect, beforeEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { provideRouter, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { TemplatesPageComponent } from './templates-page';
import { ChatMessageTemplateService } from '../../services/chat-message-template.service';
import { IntegrationService } from '@core/services/integration.service';

vi.mock('ngx-sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  },
}));

describe('TemplatesPageComponent', () => {
  let templateService: {
    list: ReturnType<typeof vi.fn>;
    sync: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
  };
  let integrationService: { list: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    templateService = {
      list: vi
        .fn()
        .mockReturnValue(
          of({ data: [], meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 } }),
        ),
      sync: vi.fn().mockReturnValue(of({ count: 3 })),
      delete: vi.fn().mockReturnValue(of(void 0)),
    };
    integrationService = {
      list: vi
        .fn()
        .mockReturnValue(
          of({ data: [{ id: 'i1', name: 'Canal Meta', type: 'whatsapp', status: 'connected' }] }),
        ),
    };

    TestBed.configureTestingModule({
      imports: [TemplatesPageComponent],
      providers: [
        provideRouter([]),
        { provide: ChatMessageTemplateService, useValue: templateService },
        { provide: IntegrationService, useValue: integrationService },
      ],
    });
  });

  it('cria o componente e carrega integrações + templates', () => {
    const fixture = TestBed.createComponent(TemplatesPageComponent);
    fixture.detectChanges();
    expect(integrationService.list).toHaveBeenCalled();
    expect(templateService.list).toHaveBeenCalled();
    expect(fixture.componentInstance.isLoading()).toBe(false);
  });

  it('aciona sync quando canal está selecionado', () => {
    const fixture = TestBed.createComponent(TemplatesPageComponent);
    fixture.detectChanges();
    const cmp = fixture.componentInstance;
    cmp.chatInstanceFilter.set('i1');
    cmp.syncNow();
    expect(templateService.sync).toHaveBeenCalledWith('i1');
  });

  it('NÃO chama sync quando nenhum canal selecionado', () => {
    const fixture = TestBed.createComponent(TemplatesPageComponent);
    fixture.detectChanges();
    const cmp = fixture.componentInstance;
    cmp.chatInstanceFilter.set('');
    cmp.syncNow();
    expect(templateService.sync).not.toHaveBeenCalled();
  });

  it('confirma exclusão e recarrega lista', () => {
    const fixture = TestBed.createComponent(TemplatesPageComponent);
    fixture.detectChanges();
    const cmp = fixture.componentInstance;
    cmp.askDelete({
      id: 't1',
      name: 'foo',
      shortcut: null,
      content: 'x',
      category: 'UTILITY',
      is_active: true,
      provider: 'meta',
      chat_instance_id: 'i1',
      external_id: null,
      language: 'pt_BR',
      status: 'approved',
      rejected_reason: null,
      components: [],
      last_synced_at: null,
    });
    cmp.confirmDelete();
    expect(templateService.delete).toHaveBeenCalledWith('t1');
  });

  it('lida com erro de carregamento', () => {
    templateService.list.mockReturnValueOnce(throwError(() => new Error('boom')));
    const fixture = TestBed.createComponent(TemplatesPageComponent);
    fixture.detectChanges();
    expect(fixture.componentInstance.hasError()).toBe(true);
  });

  it('navega para /chat/templates/new ao criar', () => {
    const fixture = TestBed.createComponent(TemplatesPageComponent);
    fixture.detectChanges();
    const router = TestBed.inject(Router);
    const spy = vi.spyOn(router, 'navigate').mockResolvedValue(true);
    fixture.componentInstance.openCreate();
    expect(spy).toHaveBeenCalledWith(['/chat/templates/new']);
  });
});
