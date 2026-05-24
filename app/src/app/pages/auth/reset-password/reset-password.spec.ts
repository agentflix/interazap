import { TestBed } from '@angular/core/testing';
import { describe, beforeEach, it, expect, vi } from 'vitest';
import { provideRouter } from '@angular/router';
import { of, throwError } from 'rxjs';
import ResetPasswordComponent from './reset-password';
import { AuthService } from '@core/services/auth.service';

describe('ResetPasswordComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ResetPasswordComponent],
      providers: [
        provideRouter([]),
        {
          provide: AuthService,
          useValue: {
            forgotPassword: () => of({ success: true, message: 'ok' }),
          },
        },
      ],
    }).compileComponents();
  });

  it('should set success message on successful submit', () => {
    const fixture = TestBed.createComponent(ResetPasswordComponent);
    const component = fixture.componentInstance;
    const authService = TestBed.inject(AuthService);

    vi.spyOn(authService, 'forgotPassword').mockReturnValue(of({ success: true, message: 'ok' }));
    component.form.patchValue({ email: 'user@interazap.test' });
    component.submit();

    expect(component.successMessage()).toContain('sucesso');
  });

  it('should set error message on failed submit', () => {
    const fixture = TestBed.createComponent(ResetPasswordComponent);
    const component = fixture.componentInstance;
    const authService = TestBed.inject(AuthService);

    vi.spyOn(authService, 'forgotPassword').mockReturnValue(throwError(() => new Error('fail')));
    component.form.patchValue({ email: 'user@interazap.test' });
    component.submit();

    expect(component.errorMessage()).toContain('Não foi possível');
  });
});
