import { describe, it, expect, beforeEach, vi } from 'vitest';
import { provideZonelessChangeDetection } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { of } from 'rxjs';
import { ProposalFormComponent } from './proposal-form';
import { CRMProposalService } from '../../services/crm-proposal.service';

describe('ProposalFormComponent', () => {
  let component: ProposalFormComponent;
  let fixture: ComponentFixture<ProposalFormComponent>;
  let proposalService: CRMProposalService;

  const mockProposal = {
    id: 'proposal-1',
    title: 'Test Proposal',
    number: '001',
    valid_until: '2024-12-31',
    notes: 'Test notes',
    items: [{ name: 'Item 1', quantity: 2, unit_price: 100, discount: 0, position: 1 }],
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ProposalFormComponent],
      providers: [
        provideZonelessChangeDetection(),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: CRMProposalService,
          useValue: {
            create: vi.fn().mockReturnValue(of({ data: { proposal: mockProposal } })),
            update: vi.fn().mockReturnValue(of({ data: { proposal: mockProposal } })),
          },
        },
      ],
    }).compileComponents();

    proposalService = TestBed.inject(CRMProposalService);
    fixture = TestBed.createComponent(ProposalFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should have form controls', () => {
    expect(component.form.get('title')).toBeTruthy();
    expect(component.form.get('number')).toBeTruthy();
    expect(component.form.get('valid_until')).toBeTruthy();
    expect(component.form.get('notes')).toBeTruthy();
    expect(component.form.get('items')).toBeTruthy();
  });

  it('should add item', () => {
    const initialLength = component.items.length;
    component.addItem();
    expect(component.items.length).toBe(initialLength + 1);
  });

  it('should have total signal', () => {
    expect(component.total).toBeDefined();
    expect(typeof component.total()).toBe('number');
  });

  it('should emit cancelled when cancel is called', () => {
    const cancelledSpy = vi.fn();
    component.cancelled.subscribe(cancelledSpy);
    component.cancel();
    expect(cancelledSpy).toHaveBeenCalled();
  });

  it('should not submit if form is invalid', () => {
    component.form.patchValue({ title: '' });
    component.submit();
    expect(proposalService.create).not.toHaveBeenCalled();
  });

  it('should track item by index', () => {
    expect(component.trackItem(5)).toBe(5);
  });

  it('should have isSaving signal', () => {
    expect(component.isSaving()).toBe(false);
  });

  it('should have errorMessage signal', () => {
    expect(component.errorMessage()).toBe(null);
  });

  it('should include unit_price and discount controls for proposal item', () => {
    component.items.clear();
    component.addItem();

    const firstItem = component.items.at(0);
    expect(firstItem.get('unit_price')).toBeTruthy();
    expect(firstItem.get('discount')).toBeTruthy();
  });
});
