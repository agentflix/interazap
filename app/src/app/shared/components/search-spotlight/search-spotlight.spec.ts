import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { provideRouter, Router } from '@angular/router';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { of } from 'rxjs';
import { LucideAngularModule, icons } from 'lucide-angular';
import { GlobalSearchService } from '@core/services/global-search.service';
import { SearchSpotlightService } from '@core/services/search-spotlight.service';
import { type GlobalSearchResponse } from '@shared/models/global-search.model';
import { SearchSpotlightComponent } from './search-spotlight';

describe('SearchSpotlightComponent', () => {
  let fixture: ComponentFixture<SearchSpotlightComponent>;
  let component: SearchSpotlightComponent;
  let router: Router;

  const searchServiceMock = {
    search: vi.fn(),
    getRecentSearches: vi.fn().mockReturnValue([]),
    addRecentSearch: vi.fn(),
    clearRecentSearches: vi.fn(),
  };

  const responseWithResult: GlobalSearchResponse = {
    data: {
      contacts: {
        total: 1,
        items: [
          {
            id: 'contact-1',
            type: 'contact',
            label: 'João Silva',
            sublabel: 'joao@email.com',
            url: '/crm/contacts?contact_id=contact-1',
            icon: 'tablerUser',
          },
        ],
      },
    },
    meta: { query: 'joão', total: 1, per_type: 5, duration_ms: 18 },
  };

  const responseEmpty: GlobalSearchResponse = {
    data: {
      contacts: { total: 0, items: [] },
      companies: { total: 0, items: [] },
      negotiations: { total: 0, items: [] },
      tickets: { total: 0, items: [] },
      users: { total: 0, items: [] },
    },
    meta: { query: 'none', total: 0, per_type: 5, duration_ms: 12 },
  };

  beforeEach(async () => {
    vi.useFakeTimers();
    vi.clearAllMocks();
    searchServiceMock.search.mockReturnValue(of(responseWithResult));

    await TestBed.configureTestingModule({
      imports: [SearchSpotlightComponent],
      providers: [
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        SearchSpotlightService,
        { provide: GlobalSearchService, useValue: searchServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(SearchSpotlightComponent);
    component = fixture.componentInstance;
    router = TestBed.inject(Router);
    vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    vi.spyOn(router, 'navigate').mockResolvedValue(true);
  });

  it('should render modal after open()', () => {
    component.open();
    fixture.detectChanges();

    expect(component.isOpen()).toBe(true);
    expect(
      (fixture.nativeElement as HTMLElement).querySelector('[data-test="spotlight-query"]'),
    ).not.toBeNull();
  });

  it('should close on Escape', () => {
    component.open();
    component.onGlobalEscape(new KeyboardEvent('keydown', { key: 'Escape' }));
    fixture.detectChanges();

    expect(component.isOpen()).toBe(false);
  });

  it('should show skeleton while loading', () => {
    component.open();
    component.isLoading.set(true);
    fixture.detectChanges();

    const element = fixture.nativeElement as HTMLElement;
    expect(element.querySelectorAll('.animate-pulse').length).toBeGreaterThan(0);
  });

  it('should show grouped results when data arrives', () => {
    component.open();
    component.results.set(responseWithResult);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Contatos CRM');
    expect((fixture.nativeElement as HTMLElement).textContent).toContain('João Silva');
  });

  it('should show empty state when total is zero', () => {
    component.open();
    component.query.set('none');
    component.results.set(responseEmpty);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain(
      'Nenhum resultado encontrado',
    );
  });

  it('should navigate to selected item URL on Enter', () => {
    component.open();
    component.results.set(responseWithResult);
    component.selectedIndex.set(0);
    component.onKeydown(new KeyboardEvent('keydown', { key: 'Enter' }));

    expect(router.navigate).toHaveBeenCalledWith(['/crm/contacts'], {
      queryParams: { contact_id: 'contact-1' },
    });
  });

  it('should clear recent history signal on close to free memory', () => {
    component.open();
    component.close();

    expect(component.recentSearches()).toEqual([]);
  });

  it('should save item to recent history when navigating', () => {
    component.open();
    component.navigateTo(responseWithResult.data.contacts!.items[0]!);

    expect(searchServiceMock.addRecentSearch).toHaveBeenCalledTimes(1);
  });
});
