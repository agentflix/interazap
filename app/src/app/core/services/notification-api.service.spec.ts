import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { TestBed } from '@angular/core/testing';
import { beforeEach, describe, expect, it } from 'vitest';
import { type NotificationListResponse } from '@shared/models/notification.model';
import { NotificationApiService } from './notification-api.service';

describe('NotificationApiService', () => {
  let service: NotificationApiService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), NotificationApiService],
    });

    service = TestBed.inject(NotificationApiService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  describe('fetchUnread', () => {
    it('should call GET /api/notifications with default limit 10', () => {
      const mockResponse: NotificationListResponse = {
        data: [],
        unread_count: 0,
      };

      service.fetchUnread().subscribe((response) => {
        expect(response.unread_count).toBe(0);
      });

      const request = httpMock.expectOne((req) => req.url.includes('/notifications'));
      expect(request.request.method).toBe('GET');
      expect(request.request.params.get('limit')).toBe('10');

      request.flush(mockResponse);
      httpMock.verify();
    });

    it('should call GET /api/notifications with custom limit', () => {
      const mockResponse: NotificationListResponse = {
        data: [
          {
            id: 'uuid-1',
            tenant_id: 'tenant-1',
            user_id: 'user-1',
            type: 'new_ticket',
            title: 'Novo Ticket',
            body: 'Ticket #123 criado',
            created_at: '2026-03-28T10:00:00Z',
          },
        ],
        unread_count: 1,
      };

      service.fetchUnread(5).subscribe((response) => {
        expect(response.data).toHaveLength(1);
        expect(response.unread_count).toBe(1);
      });

      const request = httpMock.expectOne((req) => req.url.includes('/notifications'));
      expect(request.request.params.get('limit')).toBe('5');

      request.flush(mockResponse);
      httpMock.verify();
    });
  });

  describe('markAsRead', () => {
    it('should call PATCH /api/notifications/{id}/read', () => {
      service.markAsRead('uuid-abc').subscribe();

      const request = httpMock.expectOne((req) => req.url.includes('/notifications/uuid-abc/read'));
      expect(request.request.method).toBe('PATCH');
      expect(request.request.body).toEqual({});

      request.flush(null);
      httpMock.verify();
    });
  });

  describe('markAllAsRead', () => {
    it('should call POST /api/notifications/read-all', () => {
      service.markAllAsRead().subscribe((response) => {
        expect(response.count).toBe(5);
        expect(response.message).toBe('All notifications marked as read');
      });

      const request = httpMock.expectOne((req) => req.url.includes('/notifications/read-all'));
      expect(request.request.method).toBe('POST');
      expect(request.request.body).toEqual({});

      request.flush({ count: 5, message: 'All notifications marked as read' });
      httpMock.verify();
    });
  });
});
