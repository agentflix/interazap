import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { FunnelFormComponent } from './funnel-form';
import { FunnelService } from '@core/services/crm-funnel.service';

describe('FunnelFormComponent', () => {
  let component: FunnelFormComponent;
  let fixture: ComponentFixture<FunnelFormComponent>;

  const funnelServiceMock = {
    create: vi.fn().mockReturnValue(of({ data: { id: '1', name: 'Test' } })),
    update: vi.fn().mockReturnValue(of({ data: { id: '1', name: 'Updated' } })),
    listSteps: vi.fn().mockReturnValue(of({ data: [] })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FunnelFormComponent],
      providers: [{ provide: FunnelService, useValue: funnelServiceMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(FunnelFormComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should initialize form controls', () => {
    expect(component.form.get('name')).toBeDefined();
    expect(component.form.get('description')).toBeDefined();
    expect(component.form.get('is_active')).toBeDefined();
  });

  it('should mark name as invalid when empty', () => {
    const nameControl = component.form.get('name');
    nameControl?.setValue('');
    nameControl?.markAsTouched();

    expect(nameControl?.invalid).toBe(true);
  });

  it('should emit cancelled', () => {
    const cancelledSpy = vi.spyOn(component.cancelled, 'emit');

    component.cancel();

    expect(cancelledSpy).toHaveBeenCalled();
  });
});
