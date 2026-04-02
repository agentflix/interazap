import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationHeaderComponent } from './negotiation-header';

describe('NegotiationHeaderComponent', () => {
  let fixture: ComponentFixture<NegotiationHeaderComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationHeaderComponent],
      providers: [provideRouter([]), provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationHeaderComponent);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
