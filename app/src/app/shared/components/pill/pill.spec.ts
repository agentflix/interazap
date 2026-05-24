import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';
import { Component } from '@angular/core';
import { AfPillComponent } from './pill';

/** Wrapper to test input signals via template binding */
@Component({
  standalone: true,
  imports: [AfPillComponent],
  template: `
    <af-pill [variant]="variant" [dot]="dot">Label</af-pill>
  `,
})
class TestHostComponent {
  variant: 'default' | 'success' | 'warning' | 'danger' | 'info' = 'default';
  dot = false;
}

describe('AfPillComponent', () => {
  let host: TestHostComponent;
  let fixture: ComponentFixture<TestHostComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfPillComponent, TestHostComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(TestHostComponent);
    host = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should render default variant', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const span = compiled.querySelector('span');
    expect(span?.textContent?.trim()).toBe('Label');
    expect(span?.className).toContain('rounded-full');
    expect(span?.className).toContain('bg-neutral-100');
  });

  it('should render success variant', () => {
    host.variant = 'success';
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const span = compiled.querySelector('span');
    expect(span?.className).toContain('bg-primary-50');
    expect(span?.className).toContain('text-primary-700');
  });

  it('should render danger variant', () => {
    host.variant = 'danger';
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const span = compiled.querySelector('span');
    expect(span?.className).toContain('bg-danger-50');
    expect(span?.className).toContain('text-danger-600');
  });

  it('should show dot when enabled', () => {
    host.dot = true;
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const spans = compiled.querySelectorAll('span');
    expect(spans.length).toBe(2);
    // First span is the outer pill wrapper
    expect(spans[0]?.className).toContain('rounded-full');
    // Second span is the dot
    expect(spans[1]?.className).toContain('rounded-full');
    expect(spans[1]?.className).toContain('size-1.5');
  });

  it('should not show dot when disabled', () => {
    host.dot = false;
    fixture.detectChanges();
    const compiled = fixture.nativeElement as HTMLElement;
    const spans = compiled.querySelectorAll('span');
    expect(spans.length).toBe(1);
  });

  it('should include transition-colors for smooth hover', () => {
    const compiled = fixture.nativeElement as HTMLElement;
    const span = compiled.querySelector('span');
    expect(span?.className).toContain('transition-colors');
    expect(span?.className).not.toContain('transition-all');
  });
});
