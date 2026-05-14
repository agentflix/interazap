import { describe, it, expect, beforeEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { provideRouter, ActivatedRoute, convertToParamMap } from '@angular/router';
import { of } from 'rxjs';
import { TemplateFormPageComponent } from './template-form-page';
import { ChatMessageTemplateService } from '../../services/chat-message-template.service';
import { IntegrationService } from '@core/services/integration.service';
import type { ChatMessageTemplate } from '@core/models/chat-message-template.model';

vi.mock('ngx-sonner', () => ({
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    warning: vi.fn(),
    info: vi.fn(),
  },
}));

const makeTpl = (over: Partial<ChatMessageTemplate> = {}): ChatMessageTemplate => ({
  id: 't1',
  name: 'foo_template',
  shortcut: '/foo',
  content: 'Olá {{1}}',
  category: 'UTILITY',
  is_active: true,
  provider: 'meta',
  chat_instance_id: 'i1',
  external_id: 'meta_id',
  language: 'pt_BR',
  status: 'approved',
  rejected_reason: null,
  components: [{ type: 'BODY', text: 'Olá {{1}}' }],
  last_synced_at: null,
  ...over,
});

function setup(routeParams: Record<string, string> = {}, currentTpl?: ChatMessageTemplate) {
  const templateService = {
    list: vi.fn(),
    get: vi.fn().mockReturnValue(of(currentTpl ?? makeTpl())),
    create: vi.fn().mockReturnValue(of(makeTpl())),
    update: vi.fn().mockReturnValue(of(makeTpl())),
    delete: vi.fn(),
    sync: vi.fn(),
  };
  const integrationService = {
    list: vi
      .fn()
      .mockReturnValue(
        of({ data: [{ id: 'i1', name: 'Meta', type: 'whatsapp', status: 'connected' }] }),
      ),
  };
  TestBed.configureTestingModule({
    imports: [TemplateFormPageComponent],
    providers: [
      provideRouter([]),
      { provide: ChatMessageTemplateService, useValue: templateService },
      { provide: IntegrationService, useValue: integrationService },
      {
        provide: ActivatedRoute,
        useValue: { snapshot: { paramMap: convertToParamMap(routeParams) } },
      },
    ],
  });
  const fixture = TestBed.createComponent(TemplateFormPageComponent);
  fixture.detectChanges();
  return { fixture, cmp: fixture.componentInstance, templateService, integrationService };
}

describe('TemplateFormPageComponent', () => {
  beforeEach(() => vi.clearAllMocks());

  it('inicializa em modo "create" quando não há :id', () => {
    const { cmp } = setup();
    expect(cmp.isEditMode()).toBe(false);
    expect(cmp.isMetaLocked()).toBe(false);
  });

  it('carrega template em modo edit e bloqueia campos quando provider=meta', () => {
    const { cmp, templateService } = setup({ id: 't1' }, makeTpl({ provider: 'meta' }));
    expect(templateService.get).toHaveBeenCalledWith('t1');
    expect(cmp.isEditMode()).toBe(true);
    expect(cmp.isMetaLocked()).toBe(true);
    expect(cmp.form.controls.name.disabled).toBe(true);
    expect(cmp.form.controls.body_text.disabled).toBe(true);
    expect(cmp.form.controls.shortcut.disabled).toBe(false);
    expect(cmp.form.controls.is_active.disabled).toBe(false);
  });

  it('NÃO submete create se form inválido', () => {
    const { cmp, templateService } = setup();
    cmp.submit();
    expect(templateService.create).not.toHaveBeenCalled();
    expect(cmp.form.controls.name.touched).toBe(true);
  });

  it('valida nome com pattern snake_case', () => {
    const { cmp } = setup();
    cmp.form.controls.name.setValue('Nome Inválido!');
    expect(cmp.form.controls.name.invalid).toBe(true);
    cmp.form.controls.name.setValue('nome_valido_123');
    expect(cmp.form.controls.name.valid).toBe(true);
  });

  it('submete create com payload completo', () => {
    const { cmp, templateService } = setup();
    cmp.form.patchValue({
      chat_instance_id: 'i1',
      name: 'meu_template',
      language: 'pt_BR',
      category: 'UTILITY',
      body_text: 'Olá {{1}}',
    });
    cmp.submit();
    expect(templateService.create).toHaveBeenCalledTimes(1);
    const arg = templateService.create.mock.calls[0][0];
    expect(arg.name).toBe('meu_template');
    expect(arg.provider).toBe('meta');
    expect(arg.components.find((c: { type: string }) => c.type === 'BODY')).toBeDefined();
  });

  it('em modo Meta-locked envia apenas shortcut + is_active', () => {
    const { cmp, templateService } = setup({ id: 't1' }, makeTpl({ provider: 'meta' }));
    cmp.form.controls.shortcut.setValue('/novo');
    cmp.form.controls.is_active.setValue(false);
    cmp.submit();
    expect(templateService.update).toHaveBeenCalledWith('t1', {
      shortcut: '/novo',
      is_active: false,
    });
  });
});
