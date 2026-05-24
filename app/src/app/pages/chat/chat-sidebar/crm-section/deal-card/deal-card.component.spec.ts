import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { DealCardComponent } from './deal-card.component';
import { CurrencyPipe, DatePipe } from '@angular/common';

describe('DealCardComponent', () => {
  let component: DealCardComponent;
  let fixture: ComponentFixture<DealCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DealCardComponent],
      providers: [CurrencyPipe, DatePipe],
    }).compileComponents();

    fixture = TestBed.createComponent(DealCardComponent);
    component = fixture.componentInstance;

    // Set required input
    const deal = {
      id: '1',
      title: 'Test Deal',
      value: 5000,
      status: 'open',
      step: { id: '1', name: 'Prospecção' },
      funnel: {
        id: 'f1',
        name: 'Funil',
        steps: [
          { id: '0', name: 'Entrada', order: 1 },
          { id: '1', name: 'Prospecção', order: 2 },
          { id: '2', name: 'Fechamento', order: 3 },
        ],
      },
      contact: { id: '1', name: 'John Doe' },
      user: { id: '1', name: 'Agent' },
      expected_close_date: '2025-02-01',
    };
    fixture.componentRef.setInput('deal', deal);
    fixture.detectChanges();
  });

  it('should compute status class correctly', () => {
    expect(component.statusClass()).toBe('border-l-4 border-l-blue-500');

    fixture.componentRef.setInput('deal', {
      ...component.deal(),
      status: 'won',
    });
    fixture.detectChanges();
    expect(component.statusClass()).toBe('border-l-4 border-l-green-500');

    fixture.componentRef.setInput('deal', {
      ...component.deal(),
      status: 'lost',
    });
    fixture.detectChanges();
    expect(component.statusClass()).toBe('border-l-4 border-l-gray-400');
  });

  it('should emit stage move events', () => {
    vi.spyOn(component.stageChanged, 'emit');

    component.moveToPreviousStage();
    expect(component.stageChanged.emit).toHaveBeenCalledWith('previous');

    component.moveToNextStage();
    expect(component.stageChanged.emit).toHaveBeenCalledWith('next');
  });

  it('should disable previous move in first step', () => {
    vi.spyOn(component.stageChanged, 'emit');
    fixture.componentRef.setInput('deal', {
      ...component.deal(),
      step: { id: '0', name: 'Entrada' },
    });
    fixture.detectChanges();

    component.moveToPreviousStage();
    expect(component.stageChanged.emit).not.toHaveBeenCalled();
  });

  it('should disable next move in last step', () => {
    vi.spyOn(component.stageChanged, 'emit');
    fixture.componentRef.setInput('deal', {
      ...component.deal(),
      step: { id: '2', name: 'Fechamento' },
    });
    fixture.detectChanges();

    component.moveToNextStage();
    expect(component.stageChanged.emit).not.toHaveBeenCalled();
  });

  it('should emit modal open events', () => {
    vi.spyOn(component.edit, 'emit');
    vi.spyOn(component.markWon, 'emit');
    vi.spyOn(component.markLost, 'emit');

    component.onEdit();
    expect(component.edit.emit).toHaveBeenCalled();

    component.onMarkWon();
    expect(component.markWon.emit).toHaveBeenCalled();

    component.onMarkLost();
    expect(component.markLost.emit).toHaveBeenCalled();
  });
});
