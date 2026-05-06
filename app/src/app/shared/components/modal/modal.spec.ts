import { ComponentFixture, TestBed } from '@angular/core/testing';
import { By } from '@angular/platform-browser';

import { AfModalComponent } from './modal';

describe('AfModalComponent', () => {
  let fixture: ComponentFixture<AfModalComponent>;
  let component: AfModalComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AfModalComponent],
    }).compileComponents();

    fixture = TestBed.createComponent(AfModalComponent);
    component = fixture.componentInstance;
  });

  it('deve renderizar container com z-index acima do topbar quando aberto', () => {
    fixture.componentRef.setInput('open', true);
    fixture.detectChanges();

    const dialogContainer = fixture.debugElement.query(
      By.css('div.fixed.inset-0[role="dialog"]'),
    )?.nativeElement as HTMLElement | undefined;

    expect(dialogContainer).toBeTruthy();
    expect(dialogContainer?.className).toContain('z-[100]');
  });
});
