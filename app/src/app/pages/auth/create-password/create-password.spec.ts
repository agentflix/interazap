import { TestBed } from '@angular/core/testing';
import { describe, beforeEach, it, expect } from 'vitest';
import { provideRouter } from '@angular/router';
import CreatePasswordComponent from './create-password';

describe('CreatePasswordComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CreatePasswordComponent],
      providers: [provideRouter([])],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(CreatePasswordComponent);
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should detect mismatch between passwords', () => {
    const fixture = TestBed.createComponent(CreatePasswordComponent);
    const component = fixture.componentInstance;

    component.form.patchValue({ password: '123456', passwordConfirmation: '654321' });

    expect(component.passwordsMismatch()).toBe(true);
  });
});
