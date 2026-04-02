import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter, ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { NegotiationShow } from './negotiation-show';
import { type Negotiation, NegotiationService } from 'src/app/core/services/negotiation.service';
import { FunnelService } from 'src/app/core/services/funnel.service';
import { ReasonLossService } from 'src/app/core/services/reason-loss.service';
import { ContactService } from 'src/app/core/services/contact.service';
import { CRMCompanyService } from 'src/app/core/services/crm-company.service';
import { NegotiationTaskService } from 'src/app/core/services/negotiation-task.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';

describe('NegotiationShow', () => {
  let fixture: ComponentFixture<NegotiationShow>;
  let component: NegotiationShow;

  const mockNegotiation: Negotiation = {
    id: '1',
    title: 'Deal 1',
    status: 'open',
    funnel_id: 'f1',
    step_id: 's1',
    crm_company_id: 'c1',
    value: 1000,
    created_at: '2023-01-01T00:00:00Z',
    expected_close_date: '2023-12-31',
  } as Negotiation;

  const negotiationServiceMock = {
    get: vi.fn().mockReturnValue(of({ data: { negotiation: mockNegotiation } })),
    markAsWon: vi.fn().mockReturnValue(of({ data: { negotiation: mockNegotiation } })),
    markAsLost: vi.fn().mockReturnValue(of({ data: { negotiation: mockNegotiation } })),
    reopen: vi.fn().mockReturnValue(of({ data: { negotiation: mockNegotiation } })),
    update: vi.fn().mockReturnValue(of({ data: { negotiation: mockNegotiation } })),
  };

  const funnelServiceMock = {
    all: vi.fn().mockReturnValue(of({ data: { funnels: [] } })),
    listSteps: vi.fn().mockReturnValue(of({ data: { steps: [] } })),
  };

  const reasonLossServiceMock = {
    all: vi.fn().mockReturnValue(of({ data: [] })),
  };

  const contactServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: [] })),
  };

  const companyServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: [] })),
  };

  const taskServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: { tasks: [] } })),
  };

  const authStoreMock = {
    user: vi.fn().mockReturnValue({ id: 'u1' }),
  };

  const realtimeServiceMock = {
    connect: vi.fn(),
    on: vi.fn().mockReturnValue(of({})),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationShow],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        provideZonelessChangeDetection(),
        { provide: ActivatedRoute, useValue: { paramMap: of({ get: () => '1' }) } },
        { provide: NegotiationService, useValue: negotiationServiceMock },
        { provide: FunnelService, useValue: funnelServiceMock },
        { provide: ReasonLossService, useValue: reasonLossServiceMock },
        { provide: ContactService, useValue: contactServiceMock },
        { provide: CRMCompanyService, useValue: companyServiceMock },
        { provide: NegotiationTaskService, useValue: taskServiceMock },
        { provide: AuthStoreService, useValue: authStoreMock },
        { provide: RealtimeService, useValue: realtimeServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationShow);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('creates and loads negotiation', () => {
    expect(component).toBeTruthy();
    expect(negotiationServiceMock.get).toHaveBeenCalledWith('1');
    expect(component.negotiation()).toEqual(expect.objectContaining({ id: '1', title: 'Deal 1' }));
  });

  it('has tabs configured', () => {
    expect(component.tabs.length).toBeGreaterThanOrEqual(5);
    expect(component.activeTab()).toBe('history');
  });

  it('marks negotiation as won using current negotiation id', () => {
    component.markAsWon();

    expect(negotiationServiceMock.markAsWon).toHaveBeenCalledWith('1');
    expect(component.negotiation()).toEqual(expect.objectContaining({ id: '1' }));
  });
});
