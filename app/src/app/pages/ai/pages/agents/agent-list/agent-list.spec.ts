import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { provideRouter } from '@angular/router';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of } from 'rxjs';
import { describe, expect, it, beforeEach, vi } from 'vitest';
import { AgentListComponent } from './agent-list';
import { AiAgentService } from '@ai/services/ai-agent.service';
import { ToastService } from '../../../../../core/services/toast.service';

describe('AgentListComponent', () => {
  let fixture: ComponentFixture<AgentListComponent>;

  const agentServiceMock = {
    list: vi.fn(),
    delete: vi.fn(),
  };

  const toastMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  beforeEach(async () => {
    agentServiceMock.list.mockReturnValue(
      of({
        data: [
          {
            id: 'agent-1',
            name: 'Support L1',
            type: 'support',
            role: 'support_l1',
            model_id: 'gpt-4o-mini',
            max_tokens: 2048,
            temperature: 0.7,
            top_p: 1,
            is_active: true,
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 10, total: 1 },
      }),
    );

    await TestBed.configureTestingModule({
      imports: [AgentListComponent],
      providers: [
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: AiAgentService, useValue: agentServiceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(AgentListComponent);
  });

  it('loads and renders agent list', () => {
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.textContent).toContain('Support L1');
    expect(element.textContent).toContain('Ativo');
    expect(agentServiceMock.list).toHaveBeenCalled();

    // Ensure emoji is explicitly NOT rendered
    const emojiSpan = element.querySelector('.text-lg');
    expect(emojiSpan).toBeNull();
  });
});
