import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { PreChatComponent } from './pre-chat.component';
import { WebChatService } from '../../services/webchat.service';
import { of, throwError } from 'rxjs';

describe('PreChatComponent', () => {
  let component: PreChatComponent;
  let fixture: ComponentFixture<PreChatComponent>;

  const mockSessionResponse = {
    token: 'jwt-token',
    sessionId: 'session-123',
    ticketId: 'ticket-1',
    tenantId: 'tenant-1',
  };

  const mockWebChatService = {
    createSession: vi.fn().mockReturnValue(of(mockSessionResponse)),
    connectWebSocket: vi.fn(),
    saveSession: vi.fn(),
  };

  beforeEach(async () => {
    // Clear mocks before each test to ensure clean state
    vi.clearAllMocks();

    TestBed.configureTestingModule({
      imports: [ReactiveFormsModule, PreChatComponent],
      providers: [{ provide: WebChatService, useValue: mockWebChatService }],
    });

    fixture = TestBed.createComponent(PreChatComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  afterEach(() => {
    // cleanup handled by takeUntilDestroyed
  });

  describe('initial state', () => {
    it('should create', () => {
      expect(component).toBeTruthy();
    });

    it('should not be loading initially', () => {
      expect(component.isLoading()).toBe(false);
    });

    it('should not show error initially', () => {
      expect(component.errorMessage()).toBeNull();
    });
  });

  describe('form validation', () => {
    it('should invalidate name control when empty', () => {
      component.nameControl.setValue('');
      component.nameControl.markAsTouched();
      fixture.detectChanges();

      expect(component.nameControl.valid).toBe(false);
    });

    it('should validate name when it has 3+ characters', () => {
      component.nameControl.setValue('João');
      component.nameControl.markAsTouched();
      fixture.detectChanges();

      expect(component.nameControl.valid).toBe(true);
    });

    it('should update form state when controls change', async () => {
      component.nameControl.setValue('Maria');
      component.whatsappControl.setValue('+5511988887777');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();
      // Wait for debounceTime(150) in setupValidation + any additional time for phone masking
      await new Promise((resolve) => setTimeout(resolve, 300));
      fixture.detectChanges();

      const state = component['formState']();
      expect(state.isValid).toBe(true);
      expect(state.name).toBe('Maria');
      // WhatsApp value is masked by AfPhoneInputComponent, so we check it contains expected digits
      expect(state.whatsapp).toMatch(/98888/);
    });
  });

  describe('onSubmit', () => {
    it('should resolve tenant id from /chat/external/:tenantId path', () => {
      window.history.pushState({}, '', '/chat/external/3453efd7-1344-4551-999b-340b37b8d501');

      component.nameControl.setValue('Test User');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();

      expect(mockWebChatService.createSession).toHaveBeenCalled();
      const [tenantId] = mockWebChatService.createSession.mock.calls[0] as [string, string, string];
      expect(tenantId).toBe('3453efd7-1344-4551-999b-340b37b8d501');
    });

    it('should call createSession with correct parameters', () => {
      component.nameControl.setValue('Test User');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();

      expect(mockWebChatService.createSession).toHaveBeenCalled();
      const [slug, name, whatsapp] = mockWebChatService.createSession.mock.calls[0] as [
        string,
        string,
        string,
      ];
      expect(name).toBe('Test User');
      // Phone value is masked by AfPhoneInputComponent
      expect(whatsapp).toBe('(11) 99999-9999');
    });

    it('should emit sessionReady on success', () => {
      let emitted: unknown;
      component.sessionReady.subscribe((data) => {
        emitted = data;
      });

      component.nameControl.setValue('User');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();

      expect(emitted).toEqual({
        token: mockSessionResponse.token,
        sessionId: mockSessionResponse.sessionId,
      });
    });

    it('should save and connect on success', () => {
      component.nameControl.setValue('User');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();

      expect(mockWebChatService.saveSession).toHaveBeenCalledWith(
        mockSessionResponse.token,
        mockSessionResponse.sessionId,
        { contactName: undefined, contactPhone: undefined, protocol: undefined },
      );
      expect(mockWebChatService.connectWebSocket).toHaveBeenCalledWith(mockSessionResponse.token);
    });

    it('should set error message on failure', () => {
      mockWebChatService.createSession.mockReturnValue(
        throwError(() => new Error('Network error')),
      );

      component.nameControl.setValue('User');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();
      fixture.detectChanges();

      expect(component.errorMessage()).toBeTruthy();
      expect(component.isLoading()).toBe(false);
    });

    it('should not submit if name is empty', () => {
      component.nameControl.setValue('');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();

      expect(mockWebChatService.createSession).not.toHaveBeenCalled();
    });

    it('should not submit if name is too short', () => {
      component.nameControl.setValue('AB');
      component.whatsappControl.setValue('(11) 99999-9999');
      component.nameControl.markAsTouched();
      component.whatsappControl.markAsTouched();

      component.onSubmit();

      expect(mockWebChatService.createSession).not.toHaveBeenCalled();
    });
  });
});
