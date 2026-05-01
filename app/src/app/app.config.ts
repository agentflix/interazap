import {
  type ApplicationConfig,
  ErrorHandler,
  provideBrowserGlobalErrorListeners,
  provideZoneChangeDetection,
  importProvidersFrom,
  LOCALE_ID,
  type Provider,
  APP_INITIALIZER,
  inject,
} from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter, TitleStrategy } from '@angular/router';
import { HashLocationStrategy, LocationStrategy } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import { routes } from './app.routes';
import { AppTitleStrategy } from './core/strategies/app-title.strategy';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { bearerInterceptor } from './core/interceptors/bearer.interceptor';
import { billingLockoutInterceptor } from './core/interceptors/billing-lockout.interceptor';
import { traceIdInterceptor } from './core/interceptors/trace-id.interceptor';
import { requestTimeoutInterceptor } from './core/interceptors/request-timeout.interceptor';
import { offlineDetectionInterceptor } from './core/interceptors/offline-detection.interceptor';
import { buildLucideIconRegistry } from './core/icons/lucide-icon-registry';
import { environment } from '../environments/environment';
import { SentryService, SentryAngularErrorHandler } from './core/services/platform/sentry.service';

const lucideIconRegistry = buildLucideIconRegistry();

const mobileLocationProviders: Provider[] = environment.mobile
  ? [{ provide: LocationStrategy, useClass: HashLocationStrategy }]
  : [];

const sentryProviders: Provider[] = environment.mobile
  ? [
      { provide: ErrorHandler, useClass: SentryAngularErrorHandler },
      {
        provide: APP_INITIALIZER,
        useFactory: () => {
          const sentry = inject(SentryService);
          return () => sentry.init();
        },
        multi: true,
      },
    ]
  : [];

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),
    { provide: TitleStrategy, useClass: AppTitleStrategy },
    { provide: LOCALE_ID, useValue: 'pt-BR' },
    provideHttpClient(
      withInterceptors([
        traceIdInterceptor,
        requestTimeoutInterceptor,
        offlineDetectionInterceptor,
        bearerInterceptor,
        authInterceptor,
        billingLockoutInterceptor,
      ]),
    ),
    importProvidersFrom(LucideAngularModule.pick(lucideIconRegistry)),
    ...mobileLocationProviders,
    ...sentryProviders,
  ],
};
