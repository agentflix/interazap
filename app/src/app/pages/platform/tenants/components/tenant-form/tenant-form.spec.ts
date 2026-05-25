import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { of } from 'rxjs';
import { TenantFormComponent } from './tenant-form';
import { CompanyService } from '@core/services/company.service';
import { PlatformPlanService } from '@platform/services/platform-plan.service';
import { UtilsService } from '@core/services/utils.service';
import { AiGovernanceService } from '@core/services/ai-governance.service';
import { ToastService } from '@core/services/toast.service';

describe('TenantFormComponent', () => {
  let component: TenantFormComponent;
  let fixture: ComponentFixture<TenantFormComponent>;

  const companyServiceMock = {
    create: vi.fn(),
    update: vi.fn(),
  };

  const platformPlanServiceMock = {
    list: vi.fn(),
  };

  const utilsServiceMock = {
    lookupCnpj: vi.fn(),
    lookupCep: vi.fn(),
  };

  const aiGovernanceServiceMock = {
    listSegments: vi.fn(),
    assignSegment: vi.fn(),
  };

  const toastServiceMock = {
    error: vi.fn(),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    companyServiceMock.create.mockReturnValue(of({ data: { id: 'tenant-new' } }));
    companyServiceMock.update.mockReturnValue(of({ data: { id: 'tenant-1' } }));

    platformPlanServiceMock.list.mockReturnValue(
      of({
        data: [
          {
            id: 'plan-1',
            name: 'Starter',
            is_active: true,
          },
        ],
      }),
    );

    aiGovernanceServiceMock.listSegments.mockReturnValue(
      of({
        data: [
          {
            id: 'segment-1',
            name: 'Geral',
            is_active: true,
          },
        ],
      }),
    );

    aiGovernanceServiceMock.assignSegment.mockReturnValue(of({ data: null }));

    await TestBed.configureTestingModule({
      imports: [TenantFormComponent],
      providers: [
        { provide: CompanyService, useValue: companyServiceMock },
        { provide: PlatformPlanService, useValue: platformPlanServiceMock },
        { provide: UtilsService, useValue: utilsServiceMock },
        { provide: AiGovernanceService, useValue: aiGovernanceServiceMock },
        { provide: ToastService, useValue: toastServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(TenantFormComponent);
    component = fixture.componentInstance;
  });

  it('sends plan_id when updating an existing tenant', () => {
    fixture.componentRef.setInput('tenant', {
      id: 'tenant-1',
      name: 'Tenant Teste',
      document: '12345678000190',
      email: 'tenant@example.com',
      segment_id: null,
      plan_id: 'plan-1',
      is_active: true,
    });
    fixture.detectChanges();

    expect(component.form.controls.plan_id.disabled).toBe(true);

    component.form.controls.name.setValue('Tenant Atualizado');
    component.form.controls.is_active.setValue(false);

    component.submit();

    expect(companyServiceMock.update).toHaveBeenCalledWith(
      'tenant-1',
      expect.objectContaining({
        name: 'Tenant Atualizado',
        plan_id: 'plan-1',
        is_active: false,
      }),
    );
  });
});
