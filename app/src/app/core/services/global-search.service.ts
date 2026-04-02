import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { environment } from '@env/environment';
import { type Observable } from 'rxjs';
import {
  type GlobalSearchItem,
  type GlobalSearchResponse,
  type GlobalSearchType,
  type RecentSearch,
} from '@shared/models/global-search.model';

const RECENT_SEARCHES_KEY = 'interazap:recent_searches';
const RECENT_SEARCHES_LIMIT = 5;

/**
 * Service responsible for global spotlight search and recent history persistence.
 */
@Injectable({ providedIn: 'root' })
export class GlobalSearchService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/search`;

  search(
    query: string,
    types?: GlobalSearchType[],
    perType?: number,
  ): Observable<GlobalSearchResponse> {
    let params = new HttpParams().set('q', query);

    if (types && types.length > 0) {
      const backendTypes = this.mapTypes(types);
      for (const type of backendTypes) {
        params = params.append('types[]', type);
      }
    }

    if (perType !== undefined) {
      params = params.set('per_type', String(perType));
    }

    return this.http.get<GlobalSearchResponse>(this.baseUrl, { params });
  }

  getRecentSearches(): RecentSearch[] {
    const raw = localStorage.getItem(RECENT_SEARCHES_KEY);
    if (!raw) {
      return [];
    }

    try {
      const parsed = JSON.parse(raw) as RecentSearch[];
      return [...parsed].sort((a, b) => b.timestamp - a.timestamp);
    } catch {
      return [];
    }
  }

  addRecentSearch(item: GlobalSearchItem): void {
    const current = this.getRecentSearches();
    const nextItem: RecentSearch = {
      id: item.id,
      label: item.label,
      type: item.type,
      url: item.url,
      timestamp: Date.now(),
    };

    const withoutDuplicates = current.filter(
      (entry) => !(entry.id === nextItem.id && entry.type === nextItem.type),
    );

    const next = [nextItem, ...withoutDuplicates].slice(0, RECENT_SEARCHES_LIMIT);
    localStorage.setItem(RECENT_SEARCHES_KEY, JSON.stringify(next));
  }

  clearRecentSearches(): void {
    localStorage.removeItem(RECENT_SEARCHES_KEY);
  }

  /**
   * @param types spotlight item types
   * @returns backend accepted plural keys
   */
  private mapTypes(
    types: GlobalSearchType[],
  ): ('contacts' | 'companies' | 'negotiations' | 'tickets' | 'users')[] {
    return types.map((type) => {
      switch (type) {
        case 'contact':
          return 'contacts';
        case 'company':
          return 'companies';
        case 'negotiation':
          return 'negotiations';
        case 'ticket':
          return 'tickets';
        case 'user':
          return 'users';
      }
    });
  }
}
