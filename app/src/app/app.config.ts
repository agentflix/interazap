import {
  type ApplicationConfig,
  provideBrowserGlobalErrorListeners,
  provideZoneChangeDetection,
  importProvidersFrom,
  LOCALE_ID,
} from '@angular/core';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter, TitleStrategy } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import { routes } from './app.routes';
import { AppTitleStrategy } from './core/strategies/app-title.strategy';
import { authInterceptor } from './core/interceptors/auth.interceptor';
import { billingLockoutInterceptor } from './core/interceptors/billing-lockout.interceptor';
import { traceIdInterceptor } from './core/interceptors/trace-id.interceptor';
import { requestTimeoutInterceptor } from './core/interceptors/request-timeout.interceptor';
import { buildLucideIconRegistry } from './core/icons/lucide-icon-registry';

const lucideIconRegistry = buildLucideIconRegistry();

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
        authInterceptor,
        billingLockoutInterceptor,
      ]),
    ),
    importProvidersFrom(LucideAngularModule.pick(lucideIconRegistry)),
  ],
};
