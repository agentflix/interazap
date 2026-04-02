import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import {
  type GlobalSearchItem,
  type GlobalSearchResponse,
  type RecentSearch,
} from '@shared/models/global-search.model';
import { GlobalSearchService } from './global-search.service';

describe('GlobalSearchService', () => {
  let service: GlobalSearchService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    localStorage.clear();

    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), GlobalSearchService],
    });

    service = TestBed.inject(GlobalSearchService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  it('search should call GET /api/search with expected params', () => {
    const mockResponse: GlobalSearchResponse = {
      data: {},
      meta: { query: 'john', total: 0, per_type: 3, duration_ms: 10 },
    };

    service.search('john', ['contact', 'ticket'], 3).subscribe((response) => {
      expect(response.meta.query).toBe('john');
    });

    const request = httpMock.expectOne((req) => req.url.endsWith('/search'));
    expect(request.request.method).toBe('GET');
    expect(request.request.params.get('q')).toBe('john');
    expect(request.request.params.getAll('types[]')).toEqual(['contacts', 'tickets']);
    expect(request.request.params.get('per_type')).toBe('3');

    request.flush(mockResponse);
    httpMock.verify();
  });

  it('addRecentSearch should store only 5 items and deduplicate by id+type', () => {
    const baseItem = (index: number): GlobalSearchItem => ({
      id: `id-${index}`,
      type: 'contact',
      label: `Label ${index}`,
      url: `/crm/contacts/id-${index}`,
      icon: 'tablerUser',
    });

    service.addRecentSearch(baseItem(1));
    service.addRecentSearch(baseItem(2));
    service.addRecentSearch(baseItem(3));
    service.addRecentSearch(baseItem(4));
    service.addRecentSearch(baseItem(5));
    service.addRecentSearch(baseItem(6));
    service.addRecentSearch(baseItem(3));

    const recents = service.getRecentSearches();
    expect(recents).toHaveLength(5);
    expect(recents[0]?.id).toBe('id-3');
    expect(recents.filter((item) => item.id === 'id-3')).toHaveLength(1);
  });

  it('getRecentSearches should return entries sorted by timestamp desc', () => {
    const unsorted: RecentSearch[] = [
      { id: '1', label: 'Old', type: 'contact', url: '/a', timestamp: 1 },
      { id: '2', label: 'New', type: 'contact', url: '/b', timestamp: 3 },
      { id: '3', label: 'Mid', type: 'contact', url: '/c', timestamp: 2 },
    ];

    localStorage.setItem('interazap:recent_searches', JSON.stringify(unsorted));

    const recents = service.getRecentSearches();
    expect(recents.map((item) => item.id)).toEqual(['2', '3', '1']);
  });

  it('clearRecentSearches should remove storage key', () => {
    localStorage.setItem('interazap:recent_searches', JSON.stringify([{ id: '1' }]));

    service.clearRecentSearches();

    expect(localStorage.getItem('interazap:recent_searches')).toBeNull();
  });
});
