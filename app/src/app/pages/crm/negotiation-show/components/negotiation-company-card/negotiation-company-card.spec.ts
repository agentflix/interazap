import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationCompanyCardComponent } from './negotiation-company-card';

describe('NegotiationCompanyCardComponent', () => {
  let fixture: ComponentFixture<NegotiationCompanyCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationCompanyCardComponent],
      providers: [provideRouter([]), provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationCompanyCardComponent);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
