import { convertToParamMap, ActivatedRoute, provideRouter } from '@angular/router';
import { TestBed } from '@angular/core/testing';
import { describe, beforeEach, it, expect, vi } from 'vitest';
import { of } from 'rxjs';
import ProposalViewComponent from './proposal-view';
import { CRMProposalService } from '@crm/services/crm-proposal.service';

describe('ProposalViewComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProposalViewComponent],
      providers: [
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(convertToParamMap({ token: 'token-123' })),
          },
        },
        {
          provide: CRMProposalService,
          useValue: {
            publicView: () =>
              of({
                data: {
                  proposal: {
                    id: '1',
                    crm_negotiation_id: '10',
                    title: 'Proposta Teste',
                    status: 'sent',
                    total: 100,
                    items: [],
                  },
                },
              }),
            publicAccept: () =>
              of({
                data: {
                  proposal: {
                    id: '1',
                    crm_negotiation_id: '10',
                    title: 'Proposta Teste',
                    status: 'accepted',
                    total: 100,
                    items: [],
                  },
                },
              }),
            publicReject: () =>
              of({
                data: {
                  proposal: {
                    id: '1',
                    crm_negotiation_id: '10',
                    title: 'Proposta Teste',
                    status: 'rejected',
                    total: 100,
                    items: [],
                  },
                },
              }),
          },
        },
      ],
    }).compileComponents();
  });

  it('should load proposal from route token', () => {
    const fixture = TestBed.createComponent(ProposalViewComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();

    expect(component.proposal()?.title).toBe('Proposta Teste');
  });
});
