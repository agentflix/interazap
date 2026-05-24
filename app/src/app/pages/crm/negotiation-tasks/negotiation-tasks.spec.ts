import { describe, it, expect, beforeEach, vi } from 'vitest';
import { provideZonelessChangeDetection } from '@angular/core';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { of } from 'rxjs';
import { NegotiationTasks } from './negotiation-tasks';
import { NegotiationTaskService } from 'src/app/core/services/negotiation-task.service';
import { RealtimeService } from 'src/app/core/services/realtime.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';

describe('NegotiationTasks', () => {
  let component: NegotiationTasks;
  let fixture: ComponentFixture<NegotiationTasks>;
  let taskService: NegotiationTaskService;

  const mockTask = {
    id: 'task-1',
    title: 'Test Task',
    status: 'pending',
    negotiation_id: 'neg-1',
    negotiation: { title: 'Test Negotiation' },
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationTasks],
      providers: [
        provideZonelessChangeDetection(),
        provideRouter([]),
        provideHttpClient(),
        provideHttpClientTesting(),
        {
          provide: NegotiationTaskService,
          useValue: {
            listForUser: vi.fn().mockReturnValue(of({ data: { tasks: [mockTask] } })),
            updateStatus: vi.fn().mockReturnValue(of({ data: mockTask })),
          },
        },
        {
          provide: RealtimeService,
          useValue: {
            connect: vi.fn(),
            on: vi.fn().mockReturnValue(of({})),
          },
        },
        {
          provide: AuthStoreService,
          useValue: {
            user: vi.fn().mockReturnValue({ id: 'user-1' }),
          },
        },
      ],
    }).compileComponents();

    taskService = TestBed.inject(NegotiationTaskService);
    fixture = TestBed.createComponent(NegotiationTasks);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should load tasks on init', () => {
    expect(taskService.listForUser).toHaveBeenCalled();
    expect(component.tasks().length).toBe(1);
  });

  it('should have status columns', () => {
    expect(component.columns.length).toBe(3);
    expect(component.columns.some((c) => c.id === 'pending')).toBe(true);
    expect(component.columns.some((c) => c.id === 'done')).toBe(true);
  });

  it('should compute tasksByStatus', () => {
    const grouped = component.tasksByStatus();
    expect(grouped.get('pending')?.length).toBe(1);
  });

  it('should get column tasks', () => {
    const tasks = component.getColumnTasks('pending');
    expect(tasks.length).toBe(1);
  });

  it('should format date', () => {
    // Date formatting depends on timezone, just check it contains a valid date format
    const result = component.formatDate('2024-01-15');
    expect(result).toMatch(/\d{2}\/\d{2}\/\d{4}/);
    expect(component.formatDate(null)).toBe('-');
    expect(component.formatDate(undefined)).toBe('-');
  });

  it('should format time', () => {
    expect(component.formatTime('14:30:00')).toBe('14:30');
    expect(component.formatTime(null)).toBe('-');
    expect(component.formatTime(undefined)).toBe('-');
  });

  it('should get status badge', () => {
    const badge = component.getStatusBadge('pending');
    expect(badge).toContain('bg-warning/10');
  });

  it('should get status label', () => {
    expect(component.getStatusLabel('pending')).toBe('Pendente');
    expect(component.getStatusLabel('done')).toBe('Concluída');
  });

  it('should get negotiation label', () => {
    const label = component.getNegotiationLabel(mockTask as never);
    expect(label).toBe('Test Negotiation');
  });

  it('should have dropListIds', () => {
    expect(component.dropListIds.length).toBe(3);
  });
});
