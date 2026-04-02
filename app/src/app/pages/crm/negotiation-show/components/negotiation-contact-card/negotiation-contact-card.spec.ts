import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationContactCardComponent } from './negotiation-contact-card';

describe('NegotiationContactCardComponent', () => {
  let fixture: ComponentFixture<NegotiationContactCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationContactCardComponent],
      providers: [provideRouter([]), provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationContactCardComponent);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
