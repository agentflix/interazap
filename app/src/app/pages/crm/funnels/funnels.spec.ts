import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';
import { Funnels } from './funnels';
import { type Funnel } from '@core/services/crm-funnel.service';

describe('Funnels', () => {
  let component: Funnels;
  let fixture: ComponentFixture<Funnels>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Funnels],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    fixture = TestBed.createComponent(Funnels);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });

  it('should expose status filter options', () => {
    expect(component.filterStatusOptions.length).toBe(3);
    expect(component.filterStatusOptions[0].value).toBe('all');
  });

  it('should open create modal', () => {
    component.openCreate();

    expect(component.showFormModal()).toBe(true);
    expect(component.selectedFunnel()).toBeNull();
  });

  it('should open edit and delete modals', () => {
    const funnel = {
      id: 'f-1',
      name: 'Funil 1',
      is_active: true,
    } as Funnel;

    component.openEdit(funnel);
    expect(component.showFormModal()).toBe(true);
    expect(component.selectedFunnel()).toEqual(funnel);

    component.openDelete(funnel);
    expect(component.showDeleteModal()).toBe(true);
    expect(component.funnelToDelete()).toEqual(funnel);
  });
});
