import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationLossModalComponent } from './negotiation-loss-modal';

describe('NegotiationLossModalComponent', () => {
  let fixture: ComponentFixture<NegotiationLossModalComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationLossModalComponent],
      providers: [provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationLossModalComponent);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
