import { TestBed } from '@angular/core/testing';
import { describe, beforeEach, it, expect } from 'vitest';
import { AppComponent } from './app';
import { NativeBridgeService } from './core/services/platform/native-bridge.service';
import { BackButtonService } from './core/services/platform/back-button.service';
import { RealtimeService } from './core/services/realtime.service';
import { AuthStoreService } from './core/services/auth-store.service';
import { PushService } from './core/services/platform/push.service';

describe('App', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AppComponent],
      providers: [
        {
          provide: NativeBridgeService,
          useValue: {
            initialize: async () => undefined,
            onAppResume: () => ({ unsubscribe: () => undefined }),
          },
        },
        {
          provide: BackButtonService,
          useValue: {
            initialize: async () => undefined,
          },
        },
        {
          provide: RealtimeService,
          useValue: {
            connect: () => undefined,
          },
        },
        {
          provide: AuthStoreService,
          useValue: {
            isAuthenticated: () => false,
          },
        },
        {
          provide: PushService,
          useValue: {
            initializeAfterLogin: async () => undefined,
          },
        },
      ],
    }).compileComponents();
  });

  it('should create the app', () => {
    const fixture = TestBed.createComponent(AppComponent);
    const app = fixture.componentInstance;
    expect(app).toBeTruthy();
  });

  it('should have the expected title', () => {
    const fixture = TestBed.createComponent(AppComponent);
    const app = fixture.componentInstance;
    expect(app.title).toBe('interazap-new');
  });
});
