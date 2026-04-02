import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { type RecordedAudio, ChatRecorderService } from './chat-recorder.service';
import { toast } from 'ngx-sonner';

describe('ChatRecorderService', () => {
  let service: ChatRecorderService;
  let originalMediaRecorder: typeof MediaRecorder | null;
  let originalMediaDevices: MediaDevices | null;
  const getRecorder = (): MockMediaRecorder | null => MockMediaRecorder.latest;

  // Class mock to capture instance and simulate behavior statefuly
  class MockMediaRecorder {
    static isTypeSupported = vi.fn().mockReturnValue(true);
    static latest: MockMediaRecorder | null = null;

    state: 'inactive' | 'recording' | 'paused' = 'inactive';
    mimeType = 'audio/webm';
    ondataavailable: ((event: BlobEvent) => void) | null = null;
    onstop: (() => void) | null = null;

    start = vi.fn().mockImplementation(() => {
      this.state = 'recording';
    });

    stop = vi.fn().mockImplementation(() => {
      this.state = 'inactive';
      // Do not auto-call onstop, let test control it or service handles it?
      // Service expects onstop to be called when stop() finishes?
      // Usually MediaRecorder fires onstop async.
    });

    pause = vi.fn().mockImplementation(() => {
      this.state = 'paused';
    });

    resume = vi.fn().mockImplementation(() => {
      this.state = 'recording';
    });

    constructor(_stream: MediaStream, _options?: MediaRecorderOptions) {
      MockMediaRecorder.latest = this;
    }
  }

  beforeEach(() => {
    TestBed.resetTestingModule();

    MockMediaRecorder.latest = null;
    originalMediaDevices = navigator.mediaDevices;
    originalMediaRecorder = window.MediaRecorder;

    // Default navigator mocks
    Object.defineProperty(navigator, 'mediaDevices', {
      writable: true,
      configurable: true,
      value: {
        getUserMedia: vi.fn().mockResolvedValue({
          getTracks: () => [{ stop: vi.fn() }],
        }),
      },
    });

    const recorderWindow = window as Window & { MediaRecorder?: typeof MediaRecorder };
    recorderWindow.MediaRecorder = MockMediaRecorder as unknown as typeof MediaRecorder;

    vi.spyOn(toast, 'error');

    TestBed.configureTestingModule({
      providers: [ChatRecorderService],
    });
    service = TestBed.inject(ChatRecorderService);
  });

  afterEach(() => {
    if (originalMediaDevices) {
      Object.defineProperty(navigator, 'mediaDevices', { value: originalMediaDevices });
    }
    if (originalMediaRecorder) {
      const recorderWindow = window as Window & { MediaRecorder?: typeof MediaRecorder };
      recorderWindow.MediaRecorder = originalMediaRecorder;
    }
    vi.restoreAllMocks();
    vi.useRealTimers();
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('Start Recording', () => {
    it('should start recording successfully', async () => {
      const result = await service.start();

      expect(result).toBe(true);
      const recorder = getRecorder();
      expect(recorder).toBeTruthy();
      expect(recorder?.start).toHaveBeenCalled();
      expect(service.state()).toBe('recording');
    });

    it('should increment duration', async () => {
      vi.useFakeTimers();
      await service.start();

      expect(service.duration()).toBe(0);

      // Advance time to trigger interval
      vi.advanceTimersByTime(500);

      expect(service.duration()).toBeGreaterThan(0); // Should be 500
    });
  });

  describe('Pause Recording', () => {
    it('should pause recording', async () => {
      await service.start();

      service.pause();

      expect(getRecorder()?.pause).toHaveBeenCalled();
      expect(service.state()).toBe('paused');
    });

    it('should stop timer when pausing', async () => {
      vi.useFakeTimers();
      await service.start();

      vi.advanceTimersByTime(500);
      const duration = service.duration();
      expect(duration).toBeGreaterThan(0);

      service.pause();

      vi.advanceTimersByTime(500);
      // Duration should not change
      expect(service.duration()).toBe(duration);
    });
  });

  describe('Resume Recording', () => {
    it('should resume paused recording', async () => {
      await service.start();
      service.pause();

      service.resume();

      expect(getRecorder()?.resume).toHaveBeenCalled();
      expect(service.state()).toBe('recording');
    });

    it('should restart timer when resuming', async () => {
      vi.useFakeTimers();
      await service.start();
      service.pause();

      const duration = service.duration();
      service.resume();

      vi.advanceTimersByTime(200);
      expect(service.duration()).toBeGreaterThan(duration);
    });
  });

  describe('Stop Recording', () => {
    it('should stop recording and emit result', async () => {
      await service.start();

      let result: RecordedAudio | null = null;
      service.recordingCompleted$.subscribe((r) => (result = r));

      // Simulate data available
      const mockEvent = {
        data: new Blob(['audio'], { type: 'audio/webm' }),
      } as unknown as BlobEvent;
      const recorder = getRecorder();
      if (recorder?.ondataavailable) {
        recorder.ondataavailable(mockEvent);
      }

      service.stop();

      // Manually trigger onstop as the browser would
      if (recorder?.onstop) {
        recorder.onstop();
      }

      expect(recorder?.stop).toHaveBeenCalled();
      expect(service.state()).toBe('text');
      expect(result).toBeDefined();
      expect(result).not.toBeNull();
      if (result === null) {
        throw new Error('Expected recorded result to be emitted.');
      }
      expect(result).toHaveProperty('blob');
    });
  });

  describe('Cancel Recording', () => {
    it('should cancel and reset', async () => {
      await service.start();
      const recorder = getRecorder();

      service.cancel();

      expect(recorder?.stop).toHaveBeenCalled();
      expect(service.state()).toBe('text');
      expect(service.duration()).toBe(0);
    });
  });

  describe('Max Recording Time', () => {
    it('should pause when max duration reached', async () => {
      vi.useFakeTimers();
      await service.start();

      vi.advanceTimersByTime(5 * 60 * 1000 + 100);

      expect(service.state()).toBe('paused');
    });
  });
});
