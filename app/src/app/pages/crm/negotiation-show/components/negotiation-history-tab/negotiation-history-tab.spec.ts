import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { of } from 'rxjs';
import { NegotiationAnnotationService } from 'src/app/core/services/negotiation-annotation.service';
import { NegotiationHistoryTabComponent } from './negotiation-history-tab';

describe('NegotiationHistoryTabComponent', () => {
  let fixture: ComponentFixture<NegotiationHistoryTabComponent>;
  const serviceMock = {
    list: vi.fn().mockReturnValue(of({ data: { annotations: [] } })),
    create: vi.fn().mockReturnValue(of({ data: {} })),
    update: vi.fn().mockReturnValue(of({ data: {} })),
    pin: vi.fn().mockReturnValue(of({ data: {} })),
    unpin: vi.fn().mockReturnValue(of({ data: {} })),
    delete: vi.fn().mockReturnValue(of({ data: {} })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationHistoryTabComponent],
      providers: [
        provideZonelessChangeDetection(),
        { provide: NegotiationAnnotationService, useValue: serviceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationHistoryTabComponent);
    fixture.componentRef.setInput('negotiationId', '1');
    fixture.componentRef.setInput('currentUserId', '1');
    fixture.detectChanges();
  });

  it('creates component and loads annotations', () => {
    expect(fixture.componentInstance).toBeTruthy();
    expect(serviceMock.list).toHaveBeenCalledWith('1');
  });
});
