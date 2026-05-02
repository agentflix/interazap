import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { DragDropModule } from '@angular/cdk/drag-drop';
import { RoutingAgentListComponent } from './routing-agent-list';
import { type ChatRoutingQueueAgent } from '../../../services/chat-routing-queue.service';

describe('RoutingAgentListComponent', () => {
  let fixture: ComponentFixture<RoutingAgentListComponent>;
  let component: RoutingAgentListComponent;

  const agents: ChatRoutingQueueAgent[] = [
    {
      id: 'a1',
      queue_id: 'q1',
      user_id: 'user-1',
      position: 1,
      last_assigned_at: null,
      is_active: true,
      created_at: '',
      updated_at: '',
    },
    {
      id: 'a2',
      queue_id: 'q1',
      user_id: 'user-2',
      position: 2,
      last_assigned_at: null,
      is_active: false,
      created_at: '',
      updated_at: '',
    },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RoutingAgentListComponent, DragDropModule],
    }).compileComponents();

    fixture = TestBed.createComponent(RoutingAgentListComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('agents', agents);
    fixture.detectChanges();
  });

  it('renderiza lista de agentes', () => {
    const items = fixture.nativeElement.querySelectorAll('[cdkDrag]');
    expect(items.length).toBe(2);
  });

  it('emite toggleActive ao alternar switch', () => {
    const toggleSpy = vi.fn();
    component.toggleActive.subscribe(toggleSpy);

    const view = component as unknown as { onToggleActive: (agent: ChatRoutingQueueAgent) => void };
    view.onToggleActive(agents[0]);
    expect(toggleSpy).toHaveBeenCalledWith({ userId: 'user-1', isActive: false });
  });

  it('emite remove ao clicar em remover', () => {
    const removeSpy = vi.fn();
    component.remove.subscribe(removeSpy);

    const view = component as unknown as { onRemove: (userId: string) => void };
    view.onRemove('user-2');
    expect(removeSpy).toHaveBeenCalledWith('user-2');
  });

  it('emite reorder ao reordenar', () => {
    const reorderSpy = vi.fn();
    component.reorder.subscribe(reorderSpy);

    const event = {
      previousIndex: 0,
      currentIndex: 1,
      item: { data: agents[0] },
      container: { data: agents },
      previousContainer: { data: agents },
      isPointerOverContainer: true,
      distance: { x: 0, y: 0 },
    };

    const view = component as unknown as { onDrop: (e: unknown) => void };
    view.onDrop(event);
    expect(reorderSpy).toHaveBeenCalled();
    const reordered = reorderSpy.mock.calls[0][0] as ChatRoutingQueueAgent[];
    expect(reordered[0].user_id).toBe('user-2');
    expect(reordered[1].user_id).toBe('user-1');
  });
});
