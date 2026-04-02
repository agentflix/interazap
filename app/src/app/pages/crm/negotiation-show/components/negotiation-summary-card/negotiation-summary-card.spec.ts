import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationSummaryCardComponent } from './negotiation-summary-card';

describe('NegotiationSummaryCardComponent', () => {
  let fixture: ComponentFixture<NegotiationSummaryCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationSummaryCardComponent],
      providers: [provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationSummaryCardComponent);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
