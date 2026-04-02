import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { type Funnel, type FunnelStep } from 'src/app/core/services/funnel.service';
import { type Negotiation } from 'src/app/core/services/negotiation.service';
import { type NegotiationTask } from 'src/app/core/services/negotiation-task.service';
import {
  NegotiationActionsComponent,
  type TaskFieldChangeEvent,
} from './negotiation-actions.component';

describe('NegotiationActionsComponent', () => {
  let component: NegotiationActionsComponent;
  let fixture: ComponentFixture<NegotiationActionsComponent>;

  const negotiation = {
    id: 'neg-1',
    title: 'Plano enterprise',
    status: 'open',
    value: 3200,
    expected_close_date: '2026-04-15T12:00:00Z',
    crm_company: { name: 'AgentFlix' },
    user: { name: 'Rafael' },
  } as Negotiation;

  const tasks = [
    {
      id: 'task-1',
      title: 'Ligar para cliente',
      action_type: 'call',
      due_date: '2026-04-02T12:00:00Z',
      is_completed: false,
    },
  ] as NegotiationTask[];

  const funnels = [
    { id: 'funnel-1', name: 'Outbound' },
    { id: 'funnel-2', name: 'Inbound' },
  ] as Funnel[];
  const detailSteps = [{ id: 'step-1', name: 'Proposta' }] as FunnelStep[];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationActionsComponent],
      providers: [provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationActionsComponent);
    component = fixture.componentInstance;

    fixture.componentRef.setInput('selectedNegotiation', negotiation);
    fixture.componentRef.setInput('detailForm', {
      funnelId: 'funnel-1',
      stepId: 'step-1',
      expectedClose: '2026-04-15',
    });
    fixture.componentRef.setInput('funnels', funnels);
    fixture.componentRef.setInput('detailSteps', detailSteps);
    fixture.componentRef.setInput('canSaveDetails', true);
    fixture.componentRef.setInput('tasks', tasks);
    fixture.componentRef.setInput('taskForm', {
      title: 'Follow-up',
      actionType: 'call',
      dueDate: '2026-04-02',
      startTime: '09:00',
      endTime: '',
      notifyChannel: 'whatsapp',
    });
    fixture.componentRef.setInput('taskFormValid', true);
    fixture.detectChanges();
  });

  it('emits detailFieldChange and saveDetails from the detail section', () => {
    const detailFieldChangeSpy = vi.fn<(value: { field: string; value: string }) => void>();
    const saveDetailsSpy = vi.fn<() => void>();

    component.detailFieldChange.subscribe(detailFieldChangeSpy);
    component.saveDetails.subscribe(saveDetailsSpy);

    const funnelSelect = fixture.nativeElement.querySelector(
      '#detail_funnel',
    ) as HTMLSelectElement | null;
    const expectedCloseInput = fixture.nativeElement.querySelector(
      '#detail_expected',
    ) as HTMLInputElement | null;
    const saveButton = fixture.nativeElement.querySelector(
      'button.btn.btn-sm',
    ) as HTMLButtonElement | null;

    if (!funnelSelect || !expectedCloseInput || !saveButton) {
      throw new Error('Expected detail controls to exist.');
    }

    funnelSelect.value = 'funnel-2';
    funnelSelect.dispatchEvent(new Event('change'));

    expectedCloseInput.value = '2026-04-20';
    expectedCloseInput.dispatchEvent(new Event('change'));
    saveButton.click();

    expect(detailFieldChangeSpy).toHaveBeenNthCalledWith(1, {
      field: 'funnelId',
      value: 'funnel-2',
    });
    expect(detailFieldChangeSpy).toHaveBeenNthCalledWith(2, {
      field: 'expectedClose',
      value: '2026-04-20',
    });
    expect(saveDetailsSpy).toHaveBeenCalledTimes(1);
  });

  it('emits taskFieldChange and createTask from the task form', () => {
    const taskFieldChangeSpy = vi.fn<(value: TaskFieldChangeEvent) => void>();
    const createTaskSpy = vi.fn<() => void>();

    component.taskFieldChange.subscribe(taskFieldChangeSpy);
    component.createTask.subscribe(createTaskSpy);

    const taskTitleInput = fixture.nativeElement.querySelector(
      'input[placeholder="Título da tarefa"]',
    ) as HTMLInputElement | null;
    const actionTypeSelect =
      (Array.from(fixture.nativeElement.querySelectorAll('select')) as HTMLSelectElement[])[2] ??
      null;
    const actionButtons = Array.from(
      fixture.nativeElement.querySelectorAll('button[type="button"]'),
    ) as HTMLButtonElement[];
    const createTaskButton = actionButtons.find((button) =>
      button.textContent?.includes('Criar tarefa'),
    );

    if (!taskTitleInput || !actionTypeSelect || !createTaskButton) {
      throw new Error('Expected task form controls to exist.');
    }

    taskTitleInput.value = 'Enviar proposta';
    taskTitleInput.dispatchEvent(new Event('input'));

    actionTypeSelect.value = 'email';
    actionTypeSelect.dispatchEvent(new Event('change'));
    createTaskButton.click();

    expect(taskFieldChangeSpy).toHaveBeenNthCalledWith(1, {
      field: 'title',
      value: 'Enviar proposta',
    });
    expect(taskFieldChangeSpy).toHaveBeenNthCalledWith(2, {
      field: 'actionType',
      value: 'email',
    });
    expect(createTaskSpy).toHaveBeenCalledTimes(1);
  });

  it('normalizes invalid notifyChannel values to none before emitting', () => {
    const taskFieldChangeSpy = vi.fn<(value: TaskFieldChangeEvent) => void>();
    component.taskFieldChange.subscribe(taskFieldChangeSpy);

    const notifySelect = document.createElement('select');
    notifySelect.append(new Option('SMS', 'sms'));
    notifySelect.value = 'sms';

    const notifyChangeEvent = new Event('change');
    Object.defineProperty(notifyChangeEvent, 'target', { value: notifySelect });

    component.onTaskSelectChange('notifyChannel', notifyChangeEvent);

    expect(taskFieldChangeSpy).toHaveBeenCalledWith({
      field: 'notifyChannel',
      value: 'none',
    });
  });

  it('emits toggleTask when a task action is clicked', () => {
    const toggleTaskSpy = vi.fn<(value: NegotiationTask) => void>();
    component.toggleTask.subscribe(toggleTaskSpy);

    const toggleButtons = Array.from(
      fixture.nativeElement.querySelectorAll('button[type="button"]'),
    ) as HTMLButtonElement[];
    const toggleTaskButton = toggleButtons.find((button) =>
      button.textContent?.includes('Concluir'),
    );

    toggleTaskButton?.click();

    expect(toggleTaskSpy).toHaveBeenCalledWith(tasks[0]);
  });
});
