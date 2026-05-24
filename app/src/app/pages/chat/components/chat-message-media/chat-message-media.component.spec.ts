import { describe, it, expect, beforeEach, afterEach, vi, type Mock } from 'vitest';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ChatMessageMediaComponent } from './chat-message-media.component';
import { ChatMediaLoaderService } from 'src/app/core/services/chat-media-loader.service';
import { CalledMessageService } from 'src/app/core/services/called-message.service';
import { of, throwError } from 'rxjs';

const photoSwipeLightboxMock = vi.hoisted(() => {
  const init = vi.fn();
  const loadAndOpen = vi.fn();
  const destroy = vi.fn();
  const on = vi.fn();
  const ctor = vi.fn(() => ({
    init,
    loadAndOpen,
    destroy,
    on,
  }));
  return { init, loadAndOpen, destroy, on, ctor };
});

vi.mock('photoswipe/lightbox', () => ({
  default: photoSwipeLightboxMock.ctor,
}));

describe('ChatMessageMediaComponent', () => {
  let component: ChatMessageMediaComponent;
  let fixture: ComponentFixture<ChatMessageMediaComponent>;
  let mediaLoader: {
    load: Mock;
    prime: Mock;
  };
  let messageService: {
    getMediaUrl: Mock;
  };
  let httpTesting: HttpTestingController;
  let mockObserver: {
    observe: Mock;
    disconnect: Mock;
    unobserve: Mock;
  };
  let intersectionCallback: IntersectionObserverCallback | null = null;
  let intersectionObserverConstructorCalled = false;

  function createMockIntersectionObserverEntry(
    target: Element,
    isIntersecting: boolean,
  ): IntersectionObserverEntry {
    const mockRect: DOMRectReadOnly = {
      x: 0,
      y: 0,
      width: 100,
      height: 100,
      top: 0,
      right: 100,
      bottom: 100,
      left: 0,
      toJSON: () => ({}),
    };

    return {
      target,
      isIntersecting,
      intersectionRatio: isIntersecting ? 1 : 0,
      boundingClientRect: mockRect,
      intersectionRect: mockRect,
      rootBounds: mockRect,
      time: Date.now(),
    };
  }

  beforeEach(async () => {
    // Mock IntersectionObserver
    mockObserver = {
      observe: vi.fn(),
      disconnect: vi.fn(),
      unobserve: vi.fn(),
    };

    // Reset tracking variable
    intersectionObserverConstructorCalled = false;

    // Create a proper class-based mock that can be instantiated with 'new'
    class MockIntersectionObserver implements IntersectionObserver {
      readonly root: Element | Document | null = null;
      readonly rootMargin: string = '';
      readonly thresholds: readonly number[] = [];

      constructor(callback: IntersectionObserverCallback) {
        intersectionCallback = callback;
        intersectionObserverConstructorCalled = true;
      }

      observe = mockObserver.observe;
      disconnect = mockObserver.disconnect;
      unobserve = mockObserver.unobserve;
      takeRecords(): IntersectionObserverEntry[] {
        return [];
      }
    }

    (
      window as unknown as { IntersectionObserver: typeof IntersectionObserver }
    ).IntersectionObserver = MockIntersectionObserver as unknown as typeof IntersectionObserver;

    mediaLoader = {
      load: vi.fn().mockReturnValue(of('https://example.com/media.jpg')),
      prime: vi.fn(),
    };
    messageService = {
      getMediaUrl: vi.fn().mockReturnValue(of({ data: { url: 'https://example.com/document.pdf' } })),
    };

    await TestBed.configureTestingModule({
      imports: [ChatMessageMediaComponent],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: ChatMediaLoaderService, useValue: mediaLoader },
        { provide: CalledMessageService, useValue: messageService },
      ],
    }).compileComponents();
    httpTesting = TestBed.inject(HttpTestingController);

    fixture = TestBed.createComponent(ChatMessageMediaComponent);
    component = fixture.componentInstance;
    component.messageId = 'msg-1';
    component.calledId = 'called-1';
  });

  afterEach(() => {
    mediaLoader.load.mockReset();
    mediaLoader.prime.mockReset();
    messageService.getMediaUrl.mockReset();
    httpTesting.verify();
    photoSwipeLightboxMock.ctor.mockClear();
    photoSwipeLightboxMock.init.mockClear();
    photoSwipeLightboxMock.loadAndOpen.mockClear();
    photoSwipeLightboxMock.destroy.mockClear();
    photoSwipeLightboxMock.on.mockClear();
  });

  describe('Initial State', () => {
    it('should initialize with default values', () => {
      expect(component.isVisible()).toBe(false);
      expect(component.isLoaded()).toBe(false);
      expect(component.hasError()).toBe(false);
      expect(component.resolvedUrl()).toBeNull();
    });

    it('should initialize with image type by default', () => {
      expect(component.type).toBe('image');
    });
  });

  describe('Media Type Support', () => {
    it('should support image type', () => {
      component.type = 'image';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucideImage');
    });

    it('should support video type', () => {
      component.type = 'video';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucidePlay');
    });

    it('should support audio type', () => {
      component.type = 'audio';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucideMusic');
    });

    it('should support document type', () => {
      component.type = 'document';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucideFile');
    });

    it('should support ptt (push-to-talk) type', () => {
      component.type = 'ptt';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucideMusic');
    });

    it('should support ptv (video message) type', () => {
      component.type = 'ptv';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucidePlay');
    });

    it('should support sticker type', () => {
      component.type = 'sticker';
      fixture.detectChanges();
      expect(component.getIconName()).toBe('lucideImage');
    });
  });

  describe('Lazy Loading', () => {
    it('should setup IntersectionObserver on init', () => {
      fixture.detectChanges();
      component.ngAfterViewInit();
      expect(intersectionObserverConstructorCalled).toBe(true);
    });

    it('should disconnect observer on destroy', () => {
      fixture.detectChanges();
      component.ngAfterViewInit();
      component.ngOnDestroy();
      expect(mockObserver.disconnect).toHaveBeenCalled();
    });

    it('should load media when visible', async () => {
      fixture.detectChanges();
      component.ngAfterViewInit();

      // Simulate intersection
      intersectionCallback?.(
        [createMockIntersectionObserverEntry(component.mediaContainer.nativeElement, true)],
        mockObserver as unknown as IntersectionObserver,
      );

      await new Promise((resolve) => setTimeout(resolve, 100));
      expect(mediaLoader.load).toHaveBeenCalledWith('called-1', 'msg-1');
    });

    it('should not load media when not visible', () => {
      fixture.detectChanges();
      component.ngAfterViewInit();

      intersectionCallback?.(
        [createMockIntersectionObserverEntry(component.mediaContainer.nativeElement, false)],
        mockObserver as unknown as IntersectionObserver,
      );

      expect(mediaLoader.load).not.toHaveBeenCalled();
    });

    it('should only load once', async () => {
      fixture.detectChanges();
      component.ngAfterViewInit();

      intersectionCallback?.(
        [createMockIntersectionObserverEntry(component.mediaContainer.nativeElement, true)],
        mockObserver as unknown as IntersectionObserver,
      );

      await new Promise((resolve) => setTimeout(resolve, 100));

      // Try to load again
      intersectionCallback?.(
        [createMockIntersectionObserverEntry(component.mediaContainer.nativeElement, true)],
        mockObserver as unknown as IntersectionObserver,
      );

      expect(mediaLoader.load).toHaveBeenCalledTimes(1);
    });
  });

  describe('Media Loading', () => {
    it('should set resolved URL on successful load', async () => {
      mediaLoader.load.mockReturnValue(of('https://example.com/image.jpg'));

      fixture.detectChanges();
      component.ngAfterViewInit();

      intersectionCallback?.(
        [
          {
            isIntersecting: true,
            target: component.mediaContainer.nativeElement,
          } as unknown as IntersectionObserverEntry,
        ],
        mockObserver as unknown as IntersectionObserver,
      );

      await new Promise((resolve) => setTimeout(resolve, 100));
      expect(component.resolvedUrl()).toBe('https://example.com/image.jpg');
      expect(component.hasError()).toBe(false);
    });

    it('should handle loading error', async () => {
      mediaLoader.load.mockReturnValue(throwError(() => new Error('Load failed')));

      fixture.detectChanges();
      component.ngAfterViewInit();

      intersectionCallback?.(
        [
          {
            isIntersecting: true,
            target: component.mediaContainer.nativeElement,
          } as unknown as IntersectionObserverEntry,
        ],
        mockObserver as unknown as IntersectionObserver,
      );

      await new Promise((resolve) => setTimeout(resolve, 100));
      expect(component.hasError()).toBe(true);
      expect(component.resolvedUrl()).toBeNull();
    });

    it('should use fallback URL for priming cache', () => {
      component.fallbackUrl = 'https://example.com/fallback.jpg';

      component.ngOnChanges({
        fallbackUrl: {
          currentValue: 'https://example.com/fallback.jpg',
          previousValue: null,
          firstChange: true,
          isFirstChange: () => true,
        },
      });

      expect(mediaLoader.prime).toHaveBeenCalledWith(
        'called-1',
        'msg-1',
        'https://example.com/fallback.jpg',
      );
    });
  });

  describe('Image Load Events', () => {
    it('should mark as loaded when image loads', () => {
      component.onLoad();
      expect(component.isLoaded()).toBe(true);
    });

    it('should mark as loaded when media is ready', () => {
      component.onMediaReady();
      expect(component.isLoaded()).toBe(true);
    });
  });

  describe('Metadata Processing', () => {
    it('should format duration from metadata', () => {
      component.metadata = { duration: 125 };
      expect(component.metadataDuration()).toBe('02:05');
    });

    it('should handle duration as string', () => {
      component.metadata = { duration: '90' };
      expect(component.metadataDuration()).toBe('01:30');
    });

    it('should return 00:00 for missing duration', () => {
      component.metadata = null;
      expect(component.metadataDuration()).toBe('00:00');
    });

    it('should return 00:00 for invalid duration', () => {
      component.metadata = { duration: 'invalid' };
      expect(component.metadataDuration()).toBe('00:00');
    });

    it('should pad single digits correctly', () => {
      component.metadata = { duration: 65 };
      expect(component.metadataDuration()).toBe('01:05');
    });
  });

  describe('Document Operations', () => {
    let appendChildSpy: Mock;
    let removeChildSpy: Mock;
    let createObjectURLSpy: Mock;
    let revokeObjectURLSpy: Mock;
    let mockBlob: Blob;
    let mockAnchor: { click: Mock; href: string; download: string; style: { display: string } };

    beforeEach(() => {
      mockAnchor = { click: vi.fn(), href: '', download: '', style: { display: '' } };

      mockBlob = new Blob(['content'], { type: 'application/pdf' });
      messageService.getMediaUrl.mockReturnValue(
        of({ data: { url: 'https://example.com/document.pdf' } }),
      );

      createObjectURLSpy = vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:mock-url');
      revokeObjectURLSpy = vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {});

      appendChildSpy = vi
        .spyOn(document.body, 'appendChild')
        .mockImplementation(() => mockAnchor as unknown as Node);
      removeChildSpy = vi
        .spyOn(document.body, 'removeChild')
        .mockImplementation(() => mockAnchor as unknown as Node);

      vi.spyOn(document, 'createElement').mockReturnValue(
        mockAnchor as unknown as HTMLAnchorElement,
      );
    });

    afterEach(() => {
      vi.restoreAllMocks();
    });

    it('should fetch blob and trigger anchor download', async () => {
      component.resolvedUrl.set('https://example.com/document.pdf');

      component.openDocument();
      const request = httpTesting.expectOne('https://example.com/document.pdf');
      request.flush(mockBlob);
      await vi.waitFor(() => expect(appendChildSpy).toHaveBeenCalled());

      expect(messageService.getMediaUrl).toHaveBeenCalledWith('called-1', 'msg-1');
      expect(createObjectURLSpy).toHaveBeenCalled();
      expect(mockAnchor.href).toBe('blob:mock-url');
      expect(mockAnchor.style.display).toBe('none');
      expect(mockAnchor.click).toHaveBeenCalled();
      expect(removeChildSpy).toHaveBeenCalled();
    });

    it('should fall back to window.open when signed download fails', async () => {
      const openSpy = vi.spyOn(window, 'open').mockImplementation(() => null);
      component.resolvedUrl.set('https://example.com/document.pdf');

      component.openDocument();
      const request = httpTesting.expectOne('https://example.com/document.pdf');
      request.error(new ProgressEvent('error'));
      await vi.waitFor(() => expect(openSpy).toHaveBeenCalled());

      expect(openSpy).toHaveBeenCalledWith(
        'https://example.com/document.pdf',
        '_blank',
        'noopener,noreferrer',
      );
    });

    it('should use signed URL even without fallback URL', async () => {
      component.resolvedUrl.set(null);

      component.openDocument();
      const request = httpTesting.expectOne('https://example.com/document.pdf');
      request.flush(new Blob(['content'], { type: 'application/pdf' }));
      await vi.waitFor(() => expect(appendChildSpy).toHaveBeenCalled());

      expect(messageService.getMediaUrl).toHaveBeenCalledWith('called-1', 'msg-1');
      expect(appendChildSpy).toHaveBeenCalled();
    });
  });

  describe('Image Zoom', () => {
    it('should open PhotoSwipe when image URL exists', async () => {
      component.resolvedUrl.set('https://example.com/image.jpg');
      const image = document.createElement('img');
      Object.defineProperty(image, 'naturalWidth', { value: 640, configurable: true });
      Object.defineProperty(image, 'naturalHeight', { value: 480, configurable: true });

      await component.openImageZoom({ currentTarget: image } as unknown as MouseEvent);

      const galleryData = (
        component as unknown as {
          resolveGalleryData: (
            currentUrl: string,
            width?: number,
            height?: number,
          ) => { index: number; dataSource: { src: string }[] };
        }
      ).resolveGalleryData('https://example.com/image.jpg', 640, 480);

      expect(galleryData.index).toBe(0);
      expect(galleryData.dataSource[0]?.src).toBe('https://example.com/image.jpg');
    });

    it('should not open PhotoSwipe when URL is missing', async () => {
      const image = document.createElement('img');
      await component.openImageZoom({ currentTarget: image } as unknown as MouseEvent);

      expect(photoSwipeLightboxMock.ctor).not.toHaveBeenCalled();
    });

    it('should destroy existing lightbox on component destroy', async () => {
      const destroy = vi.fn();
      (component as unknown as { imageLightbox: { destroy: () => void } | null }).imageLightbox = {
        destroy,
      };
      component.ngOnDestroy();

      expect(destroy).toHaveBeenCalledTimes(1);
    });

    it('should open gallery using active media index', async () => {
      component.resolvedUrl.set('https://cdn.example.com/current.jpg');
      component.galleryActiveId = 'msg-1';
      component.galleryItems = [
        { id: 'msg-1', src: 'https://cdn.example.com/fallback-current.jpg' },
        { id: 'msg-2', src: 'https://cdn.example.com/other.jpg' },
      ];

      const image = document.createElement('img');
      Object.defineProperty(image, 'naturalWidth', { value: 800, configurable: true });
      Object.defineProperty(image, 'naturalHeight', { value: 600, configurable: true });

      await component.openImageZoom({ currentTarget: image } as unknown as MouseEvent);

      const galleryData = (
        component as unknown as {
          resolveGalleryData: (
            currentUrl: string,
            width?: number,
            height?: number,
          ) => { index: number; dataSource: { src: string }[] };
        }
      ).resolveGalleryData('https://cdn.example.com/current.jpg', 800, 600);

      expect(galleryData.index).toBe(0);
      expect(galleryData.dataSource).toEqual(
        expect.arrayContaining([
          expect.objectContaining({ src: 'https://cdn.example.com/current.jpg' }),
          expect.objectContaining({ src: 'https://cdn.example.com/other.jpg' }),
        ]),
      );
    });
  });
});
