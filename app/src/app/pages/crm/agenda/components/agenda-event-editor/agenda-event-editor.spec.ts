import { Component, input, output } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';
import { describe, it, expect, beforeEach } from 'vitest';
import { type Event as CRMEvent } from 'src/app/core/services/event.service';
import { AgendaEventEditorComponent } from './agenda-event-editor';
import { ModalComponent } from '@shared/components/modal/modal';
import { AgendaFormComponent } from '../agenda-form/agenda-form';
import { ButtonComponent, LoadingButtonComponent } from '@shared/components/buttons';

@Component({
  selector: 'af-modal',
  standalone: true,
  template: '<ng-content></ng-content>',
})
class StubModalComponent {
  readonly isOpen = input(false);
  readonly title = input('');
  readonly closed = output<void>();
}

@Component({
  selector: 'app-agenda-form',
  standalone: true,
  template: '',
})
class StubAgendaFormComponent {
  readonly event = input<CRMEvent | null>(null);
  readonly initialData = input<Partial<CRMEvent> | null>(null);
  readonly saved = output<CRMEvent>();
  readonly cancelled = output<void>();

  cancel(): void {
    this.cancelled.emit();
  }

  submit(): void {
    this.saved.emit({ id: 'saved-event' } as CRMEvent);
  }

  isSaving(): boolean {
    return false;
  }
}

@Component({
  selector: 'app-button',
  standalone: true,
  template: '<button type="button"><ng-content></ng-content></button>',
})
class StubButtonComponent {
  readonly variant = input('primary');
  readonly size = input('sm');
  readonly dataTest = input('');
  readonly clicked = output<void>();
}

@Component({
  selector: 'app-loading-button',
  standalone: true,
  template: '<button type="button"><ng-content></ng-content></button>',
})
class StubLoadingButtonComponent {
  readonly variant = input('primary');
  readonly size = input('sm');
  readonly type = input('button');
  readonly loading = input(false);
  readonly loadingText = input('');
  readonly dataTest = input('');
  readonly clicked = output<void>();
}

describe('AgendaEventEditorComponent', () => {
  let component: AgendaEventEditorComponent;
  let fixture: ComponentFixture<AgendaEventEditorComponent>;

  const mockEvent = {
    id: 'event-1',
    title: 'Reunião',
    starts_at: '2026-03-29T10:00:00Z',
  } as CRMEvent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AgendaEventEditorComponent],
    })
      .overrideComponent(AgendaEventEditorComponent, {
        remove: {
          imports: [ModalComponent, AgendaFormComponent, ButtonComponent, LoadingButtonComponent],
        },
        add: {
          imports: [
            StubModalComponent,
            StubAgendaFormComponent,
            StubButtonComponent,
            StubLoadingButtonComponent,
          ],
        },
      })
      .compileComponents();

    fixture = TestBed.createComponent(AgendaEventEditorComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('isOpen', true);
    fixture.detectChanges();
  });

  it('deve renderizar o editor com título de novo evento por padrão', () => {
    const modalDebug = fixture.debugElement.query(By.directive(StubModalComponent));
    const modal = modalDebug.componentInstance as StubModalComponent;

    expect(component).toBeTruthy();
    expect(modal.title()).toBe('Novo evento');
  });

  it('deve usar título de edição quando recebe evento', () => {
    fixture.componentRef.setInput('event', mockEvent);
    fixture.detectChanges();

    const modalDebug = fixture.debugElement.query(By.directive(StubModalComponent));
    const modal = modalDebug.componentInstance as StubModalComponent;

    expect(modal.title()).toBe('Editar evento');
  });

  it('deve repassar evento saved emitido pelo formulário', () => {
    const received: CRMEvent[] = [];
    const formDebug = fixture.debugElement.query(By.directive(StubAgendaFormComponent));
    const form = formDebug.componentInstance as StubAgendaFormComponent;

    component.saved.subscribe((event) => received.push(event));
    form.saved.emit(mockEvent);

    expect(received).toEqual([mockEvent]);
  });

  it('deve repassar evento closed emitido pelo modal e pelo cancelamento do formulário', () => {
    let closeCount = 0;
    const modalDebug = fixture.debugElement.query(By.directive(StubModalComponent));
    const modal = modalDebug.componentInstance as StubModalComponent;
    const formDebug = fixture.debugElement.query(By.directive(StubAgendaFormComponent));
    const form = formDebug.componentInstance as StubAgendaFormComponent;

    component.closed.subscribe(() => {
      closeCount += 1;
    });

    modal.closed.emit();
    form.cancelled.emit();

    expect(closeCount).toBe(2);
  });
});
