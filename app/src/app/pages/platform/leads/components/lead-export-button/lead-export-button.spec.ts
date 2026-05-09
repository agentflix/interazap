import { ComponentFixture, TestBed } from '@angular/core/testing';
import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import { of, throwError } from 'rxjs';
import { LeadExportButtonComponent } from './lead-export-button';
import { PlatformLeadService } from '@core/services/platform-lead.service';

describe('LeadExportButtonComponent', () => {
  let component: LeadExportButtonComponent;
  let fixture: ComponentFixture<LeadExportButtonComponent>;

  const serviceMock = {
    export: vi.fn(),
  };

  const originalCreateObjectURL = URL.createObjectURL;
  const originalRevokeObjectURL = URL.revokeObjectURL;

  beforeEach(async () => {
    serviceMock.export.mockReturnValue(of(new Blob(['csv'])));

    URL.createObjectURL = vi.fn().mockReturnValue('blob:test');
    URL.revokeObjectURL = vi.fn();

    await TestBed.configureTestingModule({
      imports: [LeadExportButtonComponent],
      providers: [{ provide: PlatformLeadService, useValue: serviceMock }],
    }).compileComponents();

    fixture = TestBed.createComponent(LeadExportButtonComponent);
    component = fixture.componentInstance;
    fixture.componentRef.setInput('searchTerm', 'joao');
    fixture.detectChanges();
  });

  afterEach(() => {
    URL.createObjectURL = originalCreateObjectURL;
    URL.revokeObjectURL = originalRevokeObjectURL;
  });

  it('exporta csv com filtros atuais', () => {
    const click = vi.fn();
    const anchor = document.createElement('a');
    anchor.click = click;
    vi.spyOn(document, 'createElement').mockReturnValue(anchor);

    component.toggleOpen();
    component.exportCsv();

    expect(serviceMock.export).toHaveBeenCalledWith({
      search: 'joao',
      sort_by: 'created_at',
      sort_dir: 'desc',
    });
    expect(URL.createObjectURL).toHaveBeenCalled();
    expect(click).toHaveBeenCalled();
  });

  it('emite erro quando export falha', () => {
    serviceMock.export.mockReturnValueOnce(throwError(() => new Error('fail')));
    const errorSpy = vi.fn();
    component.errorOccurred.subscribe(errorSpy);

    component.toggleOpen();
    component.exportCsv();

    expect(errorSpy).toHaveBeenCalledWith('Não foi possível exportar os leads.');
  });
});
