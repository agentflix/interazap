import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { importProvidersFrom, provideZonelessChangeDetection } from '@angular/core';
import { of } from 'rxjs';
import { LucideAngularModule, icons } from 'lucide-angular';
import { NegotiationTaskService } from 'src/app/core/services/negotiation-task.service';
import { NegotiationTasksTabComponent } from './negotiation-tasks-tab';

describe('NegotiationTasksTabComponent', () => {
  let fixture: ComponentFixture<NegotiationTasksTabComponent>;
  const serviceMock = {
    create: vi.fn().mockReturnValue(of({ data: {} })),
    update: vi.fn().mockReturnValue(of({ data: {} })),
    delete: vi.fn().mockReturnValue(of({ data: {} })),
    toggle: vi.fn().mockReturnValue(of({ data: {} })),
    updateStatus: vi.fn().mockReturnValue(of({ data: {} })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationTasksTabComponent],
      providers: [
        provideRouter([]),
        provideZonelessChangeDetection(),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: NegotiationTaskService, useValue: serviceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationTasksTabComponent);
    fixture.componentRef.setInput('negotiationId', '1');
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
