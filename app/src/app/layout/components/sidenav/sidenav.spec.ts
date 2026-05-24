import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SidenavComponent } from './sidenav';
import { ThemeService } from '../../../core/services/theme.service';
import { AuthStoreService } from '../../../core/services/auth-store.service';
import { LucideAngularModule } from 'lucide-angular';

describe('SidenavComponent', () => {
  let fixture: ComponentFixture<SidenavComponent>;
  let component: SidenavComponent;

  const mockUser = {
    id: 'user-1',
    name: 'Rafael Silva',
    email: 'rafael@interazap.com',
    tenant_plan: {
      ai_enabled: true,
      features: {
        ai_agents_v2: true,
        ai_prompts_governance: false,
        ai_knowledge_base: true,
        ai_usage_tracking: true,
      },
    },
    is_supervisor: false,
  } as any;

  const themeServiceMock = {
    sidebarCollapsed: vi.fn(() => false),
    mobileSidebarOpen: vi.fn(() => false),
    closeMobileSidebar: vi.fn(),
    toggleSidebar: vi.fn(),
    toggleMobileSidebar: vi.fn(),
    isDark: vi.fn(() => false),
  };

  const authStoreMock = {
    user: vi.fn(() => mockUser),
    hasPermission: vi.fn(() => true),
  };

  beforeEach(async () => {
    vi.clearAllMocks();
    authStoreMock.user.mockReturnValue(mockUser);
    authStoreMock.hasPermission.mockReturnValue(true);

    await TestBed.configureTestingModule({
      imports: [SidenavComponent, LucideAngularModule],
      providers: [
        provideRouter([]),
        { provide: ThemeService, useValue: themeServiceMock },
        { provide: AuthStoreService, useValue: authStoreMock },
        { provide: HttpClient, useValue: { get: vi.fn(), post: vi.fn() } },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(SidenavComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should filter menu items based on permissions', () => {
    const items = (component as any).filterMenuItems([
      { type: 'item', label: 'Dashboard', link: '/dashboard', requiredPermission: 'dashboard.view' },
    ]);
    expect(items).toHaveLength(1);
    expect(items[0].label).toBe('Dashboard');
  });

  it('should hide items without required permission', () => {
    authStoreMock.hasPermission.mockReturnValue(false);

    const items = (component as any).filterMenuItems([
      { type: 'item', label: 'Restricted', link: '/admin', requiredPermission: 'admin.access' },
    ]);
    expect(items).toHaveLength(0);
  });

  it('should remove orphan titles (titles with no visible items below)', () => {
    // When filterMenuItems removes an item due to permission, the title above becomes orphan
    // removeOrphanTitles receives the filtered output, so orphan titles are already removed
    const items = (component as any).removeOrphanTitles([
      { type: 'title', label: 'Administração' },
      // No items below — title is orphan
    ]);
    expect(items).toHaveLength(0);
  });

  it('should keep titles that have visible items below', () => {
    const items = (component as any).removeOrphanTitles([
      { type: 'title', label: 'Visão Geral' },
      { type: 'item', label: 'Dashboard', link: '/dashboard' },
    ]);

    expect(items).toHaveLength(2);
    expect(items[0].label).toBe('Visão Geral');
    expect(items[1].label).toBe('Dashboard');
  });

  it('should toggle accordion state', () => {
    (component as any).toggleAccordion('Chat');
    expect((component as any).isAccordionOpen('Chat')).toBe(true);

    (component as any).toggleAccordion('Chat');
    expect((component as any).isAccordionOpen('Chat')).toBe(false);
  });

  it('should return sidebar classes for expanded state', () => {
    themeServiceMock.sidebarCollapsed.mockReturnValue(false);
    const classes = (component as any).sidebarClasses();
    expect(classes).toContain('w-[260px]');
    expect(classes).toContain('bg-white');
    expect(classes).toContain('border-r');
  });

  it('should return sidebar classes for collapsed state', () => {
    themeServiceMock.sidebarCollapsed.mockReturnValue(true);
    const classes = (component as any).sidebarClasses();
    expect(classes).toContain('w-[68px]');
    expect(classes).toContain('bg-white');
    expect(classes).toContain('border-r');
  });

  it('should include focus-visible in menu item classes', () => {
    const classes = (component as any).menuItemClasses();
    expect(classes).toContain('focus-visible:outline-none');
    expect(classes).toContain('focus-visible:ring-2');
    expect(classes).toContain('focus-visible:ring-primary-500');
  });

  it('should hide AI menu items when AI is disabled', () => {
    const userWithoutAi = {
      ...mockUser,
      tenant_plan: { ai_enabled: false, features: {} },
    };
    authStoreMock.user.mockReturnValue(userWithoutAi);

    // Need to recreate component to pick up new mock
    fixture = TestBed.createComponent(SidenavComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const aiAccordion = {
      type: 'accordion' as const,
      label: 'IA',
      iconName: 'brain',
      requiresAiEnabled: true,
      requiredPermission: 'ai.autopilots.manage',
      children: [
        { type: 'item' as const, label: 'Agentes', link: '/ai/agents', requiredPermission: 'ai.autopilots.manage' },
      ],
    };

    const items = (component as any).filterMenuItems([aiAccordion]);
    expect(items).toHaveLength(0);
  });

  it('should show AI menu items when AI is enabled', () => {
    authStoreMock.user.mockReturnValue(mockUser);
    authStoreMock.hasPermission.mockReturnValue(true);

    fixture = TestBed.createComponent(SidenavComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const aiAccordion = {
      type: 'accordion' as const,
      label: 'IA',
      iconName: 'brain',
      requiresAiEnabled: true,
      requiredPermission: 'ai.autopilots.manage',
      children: [
        { type: 'item' as const, label: 'Agentes', link: '/ai/agents', requiredPermission: 'ai.autopilots.manage' },
      ],
    };

    const items = (component as any).filterMenuItems([aiAccordion]);
    expect(items).toHaveLength(1);
    expect(items[0].label).toBe('IA');
  });

  it('should render sidebar element in template', () => {
    const aside = fixture.nativeElement.querySelector('aside');
    expect(aside).toBeTruthy();
  });

  it('should render nav element in template', () => {
    const nav = fixture.nativeElement.querySelector('nav');
    expect(nav).toBeTruthy();
  });
});
