import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AuthService } from '@core/services/auth.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { CompanyService } from '@core/services/company.service';
import { PlatformUserService } from '@core/services/platform-user.service';
import { RoleService } from '@core/services/role.service';
import { ToastService } from '@core/services/toast.service';
import { PlatformUsers } from './platform-users';

describe('PlatformUsers', () => {
  let component: PlatformUsers;
  let fixture: ComponentFixture<PlatformUsers>;

  const platformUserServiceMock = {
    list: vi.fn().mockReturnValue(
      of({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 15, total: 0 },
      }),
    ),
    delete: vi.fn().mockReturnValue(of(null)),
  };

  const companyServiceMock = {
    list: vi.fn().mockReturnValue(
      of({
        data: [],
        meta: { current_page: 1, last_page: 1, per_page: 100, total: 0 },
      }),
    ),
  };

  const roleServiceMock = {
    listAll: vi.fn().mockReturnValue(
      of({
        data: [
          { id: '1', name: 'Administrador' },
          { id: '2', name: 'Inquilino' },
          { id: '3', name: 'Gerente' },
          { id: '4', name: 'Atendente' },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 100, total: 4 },
      }),
    ),
  };

  const toastMock = {
    success: vi.fn(),
    error: vi.fn(),
  };

  const authServiceMock = {
    impersonateUser: vi.fn().mockReturnValue(of({ data: {} })),
  };

  const authStoreMock = {
    hasPermission: vi.fn().mockReturnValue(true),
    startImpersonation: vi.fn(),
  };

  const routerMock = {
    navigate: vi.fn().mockResolvedValue(true),
  };

  beforeEach(async () => {
    vi.clearAllMocks();

    await TestBed.configureTestingModule({
      imports: [PlatformUsers],
      providers: [
        { provide: PlatformUserService, useValue: platformUserServiceMock },
        { provide: CompanyService, useValue: companyServiceMock },
        { provide: RoleService, useValue: roleServiceMock },
        { provide: ToastService, useValue: toastMock },
        { provide: AuthService, useValue: authServiceMock },
        { provide: AuthStoreService, useValue: authStoreMock },
        { provide: Router, useValue: routerMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PlatformUsers);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('deve criar o componente', () => {
    expect(component).toBeTruthy();
  });

  it('deve remover Administrador das opções de perfis de acesso', () => {
    const values = component.roleOptions().map((option) => option.value);

    expect(values).not.toContain('Administrador');
    expect(values).toContain('Inquilino');
    expect(values).toContain('Gerente');
    expect(values).toContain('Atendente');
  });
});
