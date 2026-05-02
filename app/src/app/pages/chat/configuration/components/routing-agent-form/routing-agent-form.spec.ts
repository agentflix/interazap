import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { RoutingAgentFormComponent } from './routing-agent-form';
import { type User } from '@core/services/user.service';
import { type ChatRoutingQueueAgent } from '../../../services/chat-routing-queue.service';

describe('RoutingAgentFormComponent', () => {
  let fixture: ComponentFixture<RoutingAgentFormComponent>;
  let component: RoutingAgentFormComponent;

  const users: User[] = [
    { id: 'user-1', name: 'João', email: 'joao@test.com', is_active: true },
    { id: 'user-2', name: 'Maria', email: 'maria@test.com', is_active: true },
  ];

  const agents: ChatRoutingQueueAgent[] = [
    {
      id: 'a1',
      queue_id: 'q1',
      user_id: 'user-1',
      position: 1,
      last_assigned_at: null,
      is_active: true,
      skills: [],
      created_at: '',
      updated_at: '',
    },
  ];

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [RoutingAgentFormComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(RoutingAgentFormComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('users', users);
    fixture.componentRef.setInput('agents', agents);
    fixture.detectChanges();
  });

  it('filtra usuários já adicionados', () => {
    const view = component as unknown as {
      availableUsers: () => { value: string; label: string }[];
    };
    const available = view.availableUsers();
    expect(available.length).toBe(1);
    expect(available[0].value).toBe('user-2');
  });

  it('emite addAgent com usuário e posição ao submeter', () => {
    const addSpy = vi.fn();
    component.addAgent.subscribe(addSpy);

    component.form.controls.userId.setValue('user-2');
    component.form.controls.position.setValue(3);
    const view = component as unknown as { onSubmit: () => void };
    view.onSubmit();

    expect(addSpy).toHaveBeenCalledWith({ userId: 'user-2', position: 3 });
    expect(component.form.controls.userId.value).toBeNull();
  });

  it('emite addAgent sem posição quando não informada', () => {
    const addSpy = vi.fn();
    component.addAgent.subscribe(addSpy);

    component.form.controls.userId.setValue('user-2');
    const view = component as unknown as { onSubmit: () => void };
    view.onSubmit();

    expect(addSpy).toHaveBeenCalledWith({ userId: 'user-2', position: undefined });
  });

  it('não emite quando formulário é inválido', () => {
    const addSpy = vi.fn();
    component.addAgent.subscribe(addSpy);

    const view = component as unknown as { onSubmit: () => void };
    view.onSubmit();
    expect(addSpy).not.toHaveBeenCalled();
  });
});
