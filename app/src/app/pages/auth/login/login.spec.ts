import { TestBed } from '@angular/core/testing';
import { describe, beforeEach, it, expect, vi } from 'vitest';
import { provideRouter, Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import LoginComponent from './login';
import { AuthService } from '@core/services/auth.service';
import { AuthStoreService } from '@core/services/auth-store.service';

describe('LoginComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [LoginComponent],
      providers: [
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            login: () => of({ data: {} }),
            loginWith2FA: () => of({ data: {} }),
          },
        },
        {
          provide: AuthStoreService,
          useValue: {
            isAuthenticated: () => false,
            setAuth: () => undefined,
          },
        },
      ],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should show error message on login error', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    const component = fixture.componentInstance;
    const authService = TestBed.inject(AuthService);

    vi.spyOn(authService, 'login').mockReturnValue(throwError(() => new Error('401')));
    component.form.patchValue({ email: 'a@a.com', password: '123456' });
    component.submit();

    expect(component.message()).toBe('Credenciais invalidas.');
  });

  it('should navigate dashboard when already authenticated', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    const component = fixture.componentInstance;
    const authStore = TestBed.inject(AuthStoreService);
    const router = TestBed.inject(Router);

    vi.spyOn(authStore, 'isAuthenticated').mockReturnValue(true);
    const navSpy = vi.spyOn(router, 'navigate');
    component.ngOnInit();

    expect(navSpy).toHaveBeenCalledWith(['/dashboard']);
  });

  it('should show two factor challenge when backend requires 2FA', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    const component = fixture.componentInstance;
    const authService = TestBed.inject(AuthService);

    vi.spyOn(authService, 'login').mockReturnValue(
      of({ data: { email: 'admin@agentflix.com.br', two_factor_required: true } }),
    );

    component.form.patchValue({ email: 'admin@agentflix.com.br', password: '123456' });
    component.submit();

    expect(component.twoFactorPendingEmail()).toBe('admin@agentflix.com.br');
    expect(component.isSubmitDisabled()).toBe(true);
  });

  it('should submit two factor code and authenticate user', () => {
    const fixture = TestBed.createComponent(LoginComponent);
    const component = fixture.componentInstance;
    const authService = TestBed.inject(AuthService);
    const authStore = TestBed.inject(AuthStoreService);
    const router = TestBed.inject(Router);

    vi.spyOn(authService, 'loginWith2FA').mockReturnValue(
      of({
        data: {
          token: 'token-123',
          user: {
            id: '1',
            name: 'Admin',
            email: 'admin@agentflix.com.br',
          },
          permissions: ['chat.view'],
          tenant_plan: null,
        },
      }),
    );
    const setAuthSpy = vi.spyOn(authStore, 'setAuth');
    const navSpy = vi.spyOn(router, 'navigate');

    component.twoFactorPendingEmail.set('admin@agentflix.com.br');
    component.onTwoFactorCodeCompleted('123456');
    component.submit();

    expect(setAuthSpy).toHaveBeenCalled();
    expect(navSpy).toHaveBeenCalledWith(['/dashboard']);
    expect(component.twoFactorPendingEmail()).toBeNull();
  });
});
