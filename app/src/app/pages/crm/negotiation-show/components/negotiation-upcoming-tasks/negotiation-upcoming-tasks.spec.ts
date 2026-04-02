import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationUpcomingTasksComponent } from './negotiation-upcoming-tasks';

describe('NegotiationUpcomingTasksComponent', () => {
  let fixture: ComponentFixture<NegotiationUpcomingTasksComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationUpcomingTasksComponent],
      providers: [provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationUpcomingTasksComponent);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
