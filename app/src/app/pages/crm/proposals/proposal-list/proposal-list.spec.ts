import { describe, it, expect, beforeEach, vi } from 'vitest';
import { provideZonelessChangeDetection } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { of } from 'rxjs';
import { ProposalListComponent } from './proposal-list';
import { CRMProposalService } from '../../services/crm-proposal.service';

describe('ProposalListComponent', () => {
  let component: ProposalListComponent;
  let fixture: ComponentFixture<ProposalListComponent>;
  let proposalService: CRMProposalService;

  const mockProposal = {
    id: 'proposal-1',
    title: 'Test Proposal',
    status: 'draft',
    public_token: 'abc123',
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProposalListComponent],
      providers: [
        provideZonelessChangeDetection(),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: CRMProposalService,
          useValue: {
            listByNegotiation: vi.fn().mockReturnValue(of({ data: [mockProposal] })),
            delete: vi.fn().mockReturnValue(of({})),
            send: vi.fn().mockReturnValue(of({})),
            duplicate: vi.fn().mockReturnValue(of({})),
          },
        },
      ],
    }).compileComponents();

    proposalService = TestBed.inject(CRMProposalService);
    fixture = TestBed.createComponent(ProposalListComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('negotiationId', 'neg-1');
    fixture.detectChanges();
  });

  it('should load proposals on init', () => {
    expect(proposalService.listByNegotiation).toHaveBeenCalledWith('neg-1');
    expect(component.proposals().length).toBe(1);
  });

  it('should open and close create form', async () => {
    expect(component.isFormOpen()).toBe(false);

    component.openCreate();

    await Promise.resolve();

    expect(component.selectedProposal()).toBeNull();

    component.closeForm();

    expect(component.isFormOpen()).toBe(false);
  });

  it('should open edit form', () => {
    component.openEdit(mockProposal as never);
    expect(component.isFormOpen()).toBe(true);
    expect(component.selectedProposal()).toEqual(mockProposal);
  });

  it('should confirm delete', () => {
    component.confirmDelete(mockProposal as never);
    expect(component.isDeleteOpen()).toBe(true);
    expect(component.selectedProposal()).toEqual(mockProposal);
  });

  it('should cancel delete', () => {
    component.confirmDelete(mockProposal as never);
    component.cancelDelete();
    expect(component.isDeleteOpen()).toBe(false);
  });

  it('should get status class', () => {
    expect(component.statusClass('draft')).toContain('bg-neutral-100');
    expect(component.statusClass('sent')).toContain('bg-info');
    expect(component.statusClass('accepted')).toContain('bg-success');
    expect(component.statusClass('rejected')).toContain('bg-danger');
    expect(component.statusClass('unknown')).toContain('bg-neutral-100');
  });

  it('should track by id', () => {
    expect(component.trackById(0, mockProposal as never)).toBe('proposal-1');
  });

  it('should reload on save', () => {
    component.onSaved();
    expect(component.isFormOpen()).toBe(false);
    expect(proposalService.listByNegotiation).toHaveBeenCalled();
  });

  it('should have isLoading signal', () => {
    expect(typeof component.isLoading()).toBe('boolean');
  });

  it('should have errorMessage signal', () => {
    expect(component.errorMessage()).toBeNull();
  });
});
