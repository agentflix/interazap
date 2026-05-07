import { describe, it, expect, beforeEach, vi } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { importProvidersFrom } from '@angular/core';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of, throwError } from 'rxjs';
import { PlatformLeads } from './platform-leads';
import { PlatformLeadService } from '@core/services/platform-lead.service';

describe('PlatformLeads', () => {
  let component: PlatformLeads;
  let fixture: ComponentFixture<PlatformLeads>;

  let platformLeadServiceMock: {
    list: ReturnType<typeof vi.fn>;
  };

  beforeEach(async () => {
    platformLeadServiceMock = {
      list: vi.fn().mockReturnValue(
        of({
          data: [
            {
              id: 'lead-1',
              name: 'João Lead',
              email: 'joao@example.com',
              phone: '11999998888',
              company: 'Acme',
              source: 'landing_form',
              status: 'new',
              lgpd_consent: true,
            },
          ],
          meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
        }),
      ),
    };

    await TestBed.configureTestingModule({
      imports: [PlatformLeads],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: PlatformLeadService, useValue: platformLeadServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(PlatformLeads);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('carrega leads ao iniciar', () => {
    expect(platformLeadServiceMock.list).toHaveBeenCalled();
    expect(component.leads().length).toBe(1);
    expect(component.hasError()).toBe(false);
  });

  it('filtra por status', () => {
    platformLeadServiceMock.list.mockClear();

    component.statusFilterControl.setValue('qualified');

    expect(platformLeadServiceMock.list).toHaveBeenCalledWith(
      expect.objectContaining({ status: 'qualified' }),
    );
  });

  it('mostra estado de erro quando API falha', () => {
    platformLeadServiceMock.list.mockReturnValueOnce(throwError(() => new Error('fail')));

    component.retry();

    expect(component.hasError()).toBe(true);
  });
});
