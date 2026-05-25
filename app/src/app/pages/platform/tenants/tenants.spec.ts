import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { importProvidersFrom } from '@angular/core';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of, throwError } from 'rxjs';
import { Tenants } from './tenants';
import { CompanyService } from '@core/services/company.service';
import { type TenantDetails } from '@shared/models/tenant-details.model';
import { PlatformPlanService } from '@pages/platform/services/platform-plan.service';

describe('Tenants', () => {
  let component: Tenants;
  let fixture: ComponentFixture<Tenants>;
  let companyServiceMock: {
    list: ReturnType<typeof vi.fn>;
    details: ReturnType<typeof vi.fn>;
    delete: ReturnType<typeof vi.fn>;
    restore: ReturnType<typeof vi.fn>;
    forceDelete: ReturnType<typeof vi.fn>;
    purge: ReturnType<typeof vi.fn>;
  };
  let platformPlanServiceMock: { list: ReturnType<typeof vi.fn> };

  const mockTenants = [
    {
      id: 'tenant-1',
      name: 'Acme Corp',
      document: '12345678000100',
      phone: '11999998888',
      email: 'acme@test.com',
      primary_email: 'acme@test.com',
      is_active: true,
      plan_id: 'plan-1',
    },
    {
      id: 'tenant-2',
      name: 'Beta Inc',
      is_active: false,
    },
  ];

  const mockDetails: TenantDetails = {
    company: {
      id: 'tenant-1',
      name: 'Acme Corp',
      tenant_code: 'ACME1234',
      document: '12345678000100',
      primary_email: 'acme@test.com',
      phone: '11999998888',
      address: 'Rua A, 100, Centro',
      street: 'Rua A',
      number: '100',
      complement: null,
      district: 'Centro',
      city: 'São Paulo',
      state: 'SP',
      zip_code: '01001-000',
      is_active: true,
      created_at: '2026-01-01T00:00:00+00:00',
    },
    contracted_plan: {
      id: 'plan-1',
      name: 'Pro',
      slug: 'pro',
      price_monthly: '199.00',
      is_active: true,
    },
    resources: {
      users: { current: 8, limit: 10, available: 2 },
      instances: { current: 2, limit: 3, available: 1 },
      storage: {
        used_bytes: 2684354560,
        limit_bytes: 5368709120,
        available_bytes: 2684354560,
        used_gb: 2.5,
        limit_gb: 5.0,
        available_gb: 2.5,
        mode: 'LIMITED',
      },
      ai: { enabled: true },
      negotiations: { current: 312, limit: null, available: null, mode: 'UNLIMITED' },
    },
  };

  beforeEach(async () => {
    companyServiceMock = {
      list: vi.fn().mockReturnValue(
        of({
          data: mockTenants,
          meta: { current_page: 1, last_page: 1, per_page: 15, total: 2 },
        }),
      ),
      details: vi.fn().mockReturnValue(of({ data: mockDetails })),
      delete: vi.fn().mockReturnValue(of(void 0)),
      restore: vi.fn().mockReturnValue(of({ data: mockTenants[0] })),
      forceDelete: vi.fn().mockReturnValue(of(void 0)),
      purge: vi.fn().mockReturnValue(of(void 0)),
    };
    platformPlanServiceMock = {
      list: vi.fn().mockReturnValue(
        of({
          data: [
            {
              id: 'plan-1',
              name: 'Starter',
              slug: 'starter',
              limit_users: 5,
              storage_mode: 'LIMITED',
              storage_limit_bytes: 1073741824,
              storage_limit_gb: 1,
              ai_enabled: false,
              message_limit_monthly: 800,
              overage_mode: 'stop',
              overage_price_per_message: null,
              whatsapp_integrations_limit: 1,
              negotiations_mode: 'UNLIMITED',
              negotiations_limit: null,
              price_monthly: '0.00',
              is_active: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
        }),
      ),
    };

    await TestBed.configureTestingModule({
      imports: [Tenants],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: CompanyService, useValue: companyServiceMock },
        { provide: PlatformPlanService, useValue: platformPlanServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(Tenants);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('loads tenants on init', () => {
    expect(companyServiceMock.list).toHaveBeenCalled();
    expect(platformPlanServiceMock.list).toHaveBeenCalled();
    expect(component.tenants().length).toBe(2);
    expect(component.isLoading()).toBe(false);
  });

  it('renders plan column with name and formatted price', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Plano');
    expect(compiled.textContent).toContain('Starter - R$0,00');
  });

  it('renders details button for active tenant rows', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const detailButtons = compiled.querySelectorAll('af-icon-button[label="Ver detalhes"]');
    expect(detailButtons.length).toBeGreaterThan(0);
  });

  it('opens side panel and loads tenant details', () => {
    component.openDetails(mockTenants[0] as never);
    expect(component.isDetailsOpen()).toBe(true);
    expect(companyServiceMock.details).toHaveBeenCalledWith('tenant-1');

    fixture.detectChanges();
    expect(component.tenantDetails()).toEqual(mockDetails);
    expect(component.isDetailsLoading()).toBe(false);
  });

  it('renders contracted plan section', () => {
    component.openDetails(mockTenants[0] as never);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Plano contratado');
    expect(compiled.textContent).toContain('Pro');
    expect(compiled.textContent).toContain('R$ 199.00');
  });

  it('renders available resources section with unlimited fallback', () => {
    component.openDetails(mockTenants[0] as never);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Recursos disponíveis');
    expect(compiled.textContent).toContain('Usuários');
    expect(compiled.textContent).toContain('Instâncias WhatsApp');
    expect(compiled.textContent).toContain('Armazenamento');
    expect(compiled.textContent).toContain('Ilimitado');
  });

  it('shows error state and allows retry when details request fails', () => {
    companyServiceMock.details.mockReturnValue(throwError(() => new Error('fail')));

    component.openDetails(mockTenants[0] as never);
    fixture.detectChanges();

    expect(component.detailsError()).toBe(true);
    expect(component.isDetailsLoading()).toBe(false);

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Erro ao carregar detalhes');
  });

  it('closes details panel', () => {
    component.openDetails(mockTenants[0] as never);
    expect(component.isDetailsOpen()).toBe(true);

    component.closeDetails();
    expect(component.isDetailsOpen()).toBe(false);
  });

  it('calculates progress color correctly', () => {
    expect(component.getProgressColor(3, 10)).toBe('bg-primary');
    expect(component.getProgressColor(9, 10)).toBe('bg-warning');
    expect(component.getProgressColor(10, 10)).toBe('bg-danger');
  });

  it('calculates progress width correctly', () => {
    expect(component.getProgressWidth(5, 10)).toBe(50);
    expect(component.getProgressWidth(10, 10)).toBe(100);
    expect(component.getProgressWidth(15, 10)).toBe(100);
    expect(component.getProgressWidth(0, 0)).toBe(0);
  });

  it('shows null plan message when tenant has no plan', () => {
    const detailsWithoutPlan: TenantDetails = {
      ...mockDetails,
      contracted_plan: null,
    };
    companyServiceMock.details.mockReturnValue(of({ data: detailsWithoutPlan }));

    component.openDetails(mockTenants[0] as never);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('Plano não definido');
  });
});
