import type { ComponentFixture } from '@angular/core/testing';
import { TestBed } from '@angular/core/testing';

import { AfButtonComponent } from './button';

describe('AfButtonComponent', () => {
  let fixture: ComponentFixture<AfButtonComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfButtonComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AfButtonComponent);
  });

  it('deve aplicar alturas fixas por tamanho', () => {
    const expectedClasses: Record<'xs' | 'sm' | 'md' | 'lg', string> = {
      xs: 'h-7',
      sm: 'h-8',
      md: 'h-10',
      lg: 'h-11',
    };

    for (const [size, expectedClass] of Object.entries(expectedClasses)) {
      fixture.componentRef.setInput('size', size);
      fixture.detectChanges();

      const button = fixture.nativeElement.querySelector('button') as HTMLButtonElement | null;

      expect(button?.className).toContain(expectedClass);
    }
  });
});
