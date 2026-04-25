import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable, of } from 'rxjs';
import { catchError, map, tap } from 'rxjs/operators';
import { environment } from '@env/environment';
import { type WindowStatus, type WindowStatusResponse } from 'src/app/core/models/window-status.model';

interface CacheEntry {
  status: WindowStatus;
  timestamp: number;
}

const CACHE_STALE_TIME_MS = 30_000; // 30 seconds

/**
 * Service for verifying the 24-hour message window status of contacts.
 *
 * Determines whether a contact can receive free-form text messages or
 * requires template-based messages when using Meta WhatsApp Business API.
 *
 * @class WindowVerificationService
 */
@Injectable({ providedIn: 'root' })
export class WindowVerificationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/chat/contacts`;
  private readonly cache = new Map<string, CacheEntry>();

  /**
   * Checks the 24-hour window status for a contact.
   *
   * @param contactId - The ID of the contact to check.
   * @returns {Observable<WindowStatus>} Stream with the window status.
   *
   * @remarks
   * Uses a cache with 30-second stale time to avoid redundant API calls
   * when checking the same contact multiple times in quick succession.
   */
  checkStatus(contactId: string): Observable<WindowStatus> {
    const cached = this.getCached(contactId);
    if (cached) {
      return of(cached);
    }

    return this.http.get<WindowStatusResponse>(`${this.baseUrl}/${contactId}/window-status`).pipe(
      map((response) => response.data),
      tap((status) => {
        this.setCache(contactId, status);
      }),
      catchError(() => {
        // On error, return a default status that forces template mode
        const fallback: WindowStatus = {
          canSendFreeText: false,
          lastMessageAt: null,
        };
        return of(fallback);
      }),
    );
  }

  /**
   * Invalidates the cached status for a specific contact.
   * Call this when a new message is sent or received.
   *
   * @param contactId - The ID of the contact whose cache should be invalidated.
   */
  invalidateCache(contactId: string): void {
    this.cache.delete(contactId);
  }

  /**
   * Clears all cached window statuses.
   */
  clearCache(): void {
    this.cache.clear();
  }

  private getCached(contactId: string): WindowStatus | null {
    const entry = this.cache.get(contactId);
    if (!entry) {
      return null;
    }

    const isStale = Date.now() - entry.timestamp > CACHE_STALE_TIME_MS;
    if (isStale) {
      this.cache.delete(contactId);
      return null;
    }

    return entry.status;
  }

  private setCache(contactId: string, status: WindowStatus): void {
    this.cache.set(contactId, {
      status,
      timestamp: Date.now(),
    });
  }
}
