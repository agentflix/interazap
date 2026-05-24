import { HttpClient } from '@angular/common/http';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { ReactiveFormsModule, FormControl } from '@angular/forms';
import { of, throwError } from 'rxjs';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { environment } from '@env/environment';
import { TemplateSelectorComponent, type MetaTemplate } from './template-selector';

class HttpClientStub {
  get = vi.fn();
}

const makeTemplate = (overrides: Partial<MetaTemplate> = {}): MetaTemplate =>
  ({
    name: 'hello_world',
    category: 'MARKETING',
    language: 'pt_BR',
    status: 'APPROVED',
    components: [],
    ...overrides,
  }) as MetaTemplate;

describe('TemplateSelectorComponent', () => {
  let component: TemplateSelectorComponent;
  let fixture: ComponentFixture<TemplateSelectorComponent>;
  let http: HttpClientStub;

  beforeEach(async () => {
    http = new HttpClientStub();

    await TestBed.configureTestingModule({
      imports: [TemplateSelectorComponent, ReactiveFormsModule],
      providers: [{ provide: HttpClient, useValue: http }],
    }).compileComponents();

    fixture = TestBed.createComponent(TemplateSelectorComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('channelId', 'channel-1');
    http.get.mockReturnValue(
      of({
        data: [
          makeTemplate({ name: 'template_1', category: 'MARKETING' }),
          makeTemplate({ name: 'template_2', category: 'UTILITY' }),
        ],
      }),
    );
    fixture.detectChanges();
  });

  it('loads templates on init', () => {
    expect(http.get).toHaveBeenCalled();
  });

  it('loads templates through the Laravel API', () => {
    expect(http.get).toHaveBeenCalledWith(
      `${environment.apiUrl}/chat/message-templates?chat_instance_id=channel-1&status=APPROVED`,
    );
  });

  it('filters only APPROVED templates', () => {
    http.get.mockReturnValue(
      of({
        data: [
          makeTemplate({ name: 'approved_1', status: 'APPROVED' }),
          makeTemplate({ name: 'pending_1', status: 'PENDING' }),
          makeTemplate({ name: 'approved_2', status: 'APPROVED' }),
        ],
      }),
    );

    const newFixture = TestBed.createComponent(TemplateSelectorComponent);
    const newComponent = newFixture.componentInstance;
    newFixture.componentRef.setInput('channelId', 'channel-1');
    newFixture.detectChanges();

    expect(newComponent.templates().length).toBe(2);
    expect(newComponent.templates().every((t) => t.status === 'APPROVED')).toBe(true);
  });

  it('sets loadError when API fails', () => {
    http.get.mockReturnValue(throwError(() => new Error('network')));

    const newFixture = TestBed.createComponent(TemplateSelectorComponent);
    const newComponent = newFixture.componentInstance;
    newFixture.componentRef.setInput('channelId', 'channel-1');
    newFixture.detectChanges();

    expect(newComponent.loadError()).toBeTruthy();
  });

  it('updates templateOptions when templates load', () => {
    expect(component.templateOptions().length).toBe(2);
    expect(component.templateOptions()[0].value).toBe('template_1');
  });

  it('canSubmit is false when no template selected', () => {
    expect(component.canSubmit()).toBe(false);
  });

  it('canSubmit is true when template selected without required params', () => {
    component.templateControl.setValue('template_1');
    expect(component.canSubmit()).toBe(true);
  });

  it('hasRequiredParameters is false for template without body params', () => {
    component.templateControl.setValue('template_1');
    expect(component.hasRequiredParameters()).toBe(false);
  });

  it('hasRequiredParameters is true for template with body params', () => {
    http.get.mockReturnValue(
      of({
        data: [
          makeTemplate({
            name: 'param_template',
            components: [{ type: 'BODY', parameters: [{ type: 'text' }, { type: 'text' }] }],
          }),
        ],
      }),
    );

    const newFixture = TestBed.createComponent(TemplateSelectorComponent);
    const newComponent = newFixture.componentInstance;
    newFixture.componentRef.setInput('channelId', 'channel-1');
    newFixture.detectChanges();

    newComponent.templateControl.setValue('param_template');
    expect(newComponent.hasRequiredParameters()).toBe(true);
    expect(newComponent.requiredParamCount()).toBe(1);
  });

  it('canSubmit is false when required params not filled', () => {
    http.get.mockReturnValue(
      of({
        data: [
          makeTemplate({
            name: 'param_template',
            components: [{ type: 'BODY', parameters: [{ type: 'text' }] }],
          }),
        ],
      }),
    );

    const newFixture = TestBed.createComponent(TemplateSelectorComponent);
    const newComponent = newFixture.componentInstance;
    newFixture.componentRef.setInput('channelId', 'channel-1');
    newFixture.detectChanges();

    newComponent.templateControl.setValue('param_template');
    expect(newComponent.canSubmit()).toBe(false);
  });

  it('emits templateSelected on submit with valid template', () => {
    const emitted: { templateName: string; parameters: Record<string, string> }[] = [];
    component.templateSelected.subscribe((e) => emitted.push(e));

    component.templateControl.setValue('template_1');
    component.submit();

    expect(emitted.length).toBe(1);
    expect(emitted[0].templateName).toBe('template_1');
  });

  it('does not emit when no template selected', () => {
    const emitted: { templateName: string; parameters: Record<string, string> }[] = [];
    component.templateSelected.subscribe((e) => emitted.push(e));

    component.submit();

    expect(emitted.length).toBe(0);
    expect(component.validationError()).toBe('Selecione um template.');
  });
});
