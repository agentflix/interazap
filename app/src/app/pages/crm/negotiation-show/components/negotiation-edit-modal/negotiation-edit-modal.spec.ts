import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { of } from 'rxjs';
import { NegotiationEditModalComponent } from './negotiation-edit-modal';
import { NegotiationService } from 'src/app/core/services/negotiation.service';
import { FunnelService } from 'src/app/core/services/funnel.service';

describe('NegotiationEditModalComponent', () => {
  let fixture: ComponentFixture<NegotiationEditModalComponent>;

  const negotiationServiceMock = {
    update: vi.fn().mockReturnValue(of({ data: {} })),
  };

  const funnelServiceMock = {
    listSteps: vi.fn().mockReturnValue(of({ data: { steps: [] } })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationEditModalComponent],
      providers: [
        provideZonelessChangeDetection(),
        { provide: NegotiationService, useValue: negotiationServiceMock },
        { provide: FunnelService, useValue: funnelServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationEditModalComponent);
    fixture.componentRef.setInput('isOpen', false);
    fixture.detectChanges();
  });

  it('creates component', () => {
    expect(fixture.componentInstance).toBeTruthy();
  });
});
