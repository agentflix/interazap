import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { Subject } from 'rxjs';
import { describe, expect, it, beforeEach, vi } from 'vitest';

import { NetworkStatusService } from '../../../core/services/network-status.service';
import { OfflineBannerComponent } from './offline-banner.component';

class NetworkStatusServiceStub {
  private readonly statusChangesSubject = new Subject<boolean>();

  readonly statusChanges$ = this.statusChangesSubject.asObservable();
  online = false;
  startMonitoring = vi.fn();
  stopMonitoring = vi.fn();

  setOnline(online: boolean): void {
    this.online = online;
    this.statusChangesSubject.next(online);
  }
}

describe('OfflineBannerComponent', () => {
  let fixture: ComponentFixture<OfflineBannerComponent>;
  let networkStatus: NetworkStatusServiceStub;

  beforeEach(async () => {
    networkStatus = new NetworkStatusServiceStub();

    await TestBed.configureTestingModule({
      imports: [OfflineBannerComponent],
      providers: [{ provide: NetworkStatusService, useValue: networkStatus }],
    }).compileComponents();

    fixture = TestBed.createComponent(OfflineBannerComponent);
  });

  it('renderiza a mensagem de offline em português brasileiro', () => {
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain(
      'Você está offline. Alguns recursos podem estar indisponíveis.',
    );
    expect(fixture.nativeElement.textContent).toContain('Tentar novamente');
  });

  it('oculta o banner quando a conexão volta', () => {
    fixture.detectChanges();

    networkStatus.setOnline(true);
    fixture.detectChanges();

    expect(fixture.nativeElement.querySelector('af-banner')).toBeNull();
  });
});
