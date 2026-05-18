import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { HttpClient } from '@angular/common/http';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MainLayoutComponent } from './main-layout';
import { AppShellService } from '../../core/services/app-shell.service';

describe('MainLayoutComponent', () => {
  let fixture: ComponentFixture<MainLayoutComponent>;
  let component: MainLayoutComponent;

  function setup(contentScrollable = true, footerVisible = true) {
    const appShellMock = {
      contentScrollable: vi.fn(() => contentScrollable),
      footerVisible: vi.fn(() => footerVisible),
      hideFooter: vi.fn(),
      showFooter: vi.fn(),
      disableContentScroll: vi.fn(),
      enableContentScroll: vi.fn(),
    };

    return {
      appShellMock,
      providers: [
        provideRouter([]),
        { provide: AppShellService, useValue: appShellMock },
        { provide: HttpClient, useValue: { get: vi.fn(), post: vi.fn() } },
      ],
    };
  }

  it('should create', async () => {
    const { providers } = setup();
    await TestBed.configureTestingModule({
      imports: [MainLayoutComponent],
      providers,
    }).compileComponents();

    fixture = TestBed.createComponent(MainLayoutComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component).toBeTruthy();
  });

  it('should inject AppShellService', async () => {
    const { providers } = setup();
    await TestBed.configureTestingModule({
      imports: [MainLayoutComponent],
      providers,
    }).compileComponents();

    fixture = TestBed.createComponent(MainLayoutComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
    expect(component.appShell).toBeDefined();
  });

  it('should render with scrollable content by default', async () => {
    const { providers } = setup(true);
    await TestBed.configureTestingModule({
      imports: [MainLayoutComponent],
      providers,
    }).compileComponents();

    fixture = TestBed.createComponent(MainLayoutComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const main = fixture.nativeElement.querySelector('main');
    expect(main).toBeTruthy();
    expect(main.className).toContain('overflow-y-auto');
  });

  it('should render without scroll when disabled', async () => {
    const { providers } = setup(false);
    await TestBed.configureTestingModule({
      imports: [MainLayoutComponent],
      providers,
    }).compileComponents();

    fixture = TestBed.createComponent(MainLayoutComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const main = fixture.nativeElement.querySelector('main');
    expect(main.className).toContain('overflow-hidden');
    expect(main.className).not.toContain('overflow-y-auto');
  });

  it('should render footer when visible', async () => {
    const { providers } = setup(true, true);
    await TestBed.configureTestingModule({
      imports: [MainLayoutComponent],
      providers,
    }).compileComponents();

    fixture = TestBed.createComponent(MainLayoutComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();

    const footer = fixture.nativeElement.querySelector('af-footer');
    expect(footer).toBeTruthy();
  });
});
