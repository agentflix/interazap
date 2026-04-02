import { describe, it, expect, beforeEach, vi } from 'vitest';
import { TestBed, type ComponentFixture } from '@angular/core/testing';
import { importProvidersFrom } from '@angular/core';
import { provideRouter } from '@angular/router';
import { LucideAngularModule, icons } from 'lucide-angular';
import { of, throwError, type Observable } from 'rxjs';
import { KnowledgeUploadComponent } from './knowledge-upload';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';
import { ToastService } from '../../../../../core/services/toast.service';

interface UploadResponse {
  id: string;
  name: string;
}

describe('KnowledgeUploadComponent', () => {
  let fixture: ComponentFixture<KnowledgeUploadComponent>;
  let component: KnowledgeUploadComponent;

  const serviceMock = {
    upload: vi.fn<(file: File, title: string) => Observable<UploadResponse> | Observable<never>>(),
    ingestUrl:
      vi.fn<(url: string, title: string) => Observable<UploadResponse> | Observable<never>>(),
  };

  const toastMock = {
    success: vi.fn<(message: string) => void>(),
    error: vi.fn<(message: string) => void>(),
  };

  beforeEach(async () => {
    serviceMock.upload.mockReset();
    serviceMock.ingestUrl.mockReset();
    toastMock.success.mockReset();
    toastMock.error.mockReset();

    serviceMock.upload.mockReturnValue(of({ id: '1', name: 'Doc' }));
    serviceMock.ingestUrl.mockReturnValue(of({ id: '1', name: 'Doc' }));

    await TestBed.configureTestingModule({
      imports: [KnowledgeUploadComponent],
      providers: [
        provideRouter([]),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: AiKnowledgeService, useValue: serviceMock },
        { provide: ToastService, useValue: toastMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(KnowledgeUploadComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('shows api error message when upload fails with server error', () => {
    serviceMock.upload.mockReturnValueOnce(
      throwError(() => ({ error: { message: 'Limite de armazenamento excedido.' } })),
    );

    component.selectedFile.set(new File(['content'], 'doc.txt', { type: 'text/plain' }));
    component.form.patchValue({ title: 'Meu documento' });

    component.submit();

    expect(toastMock.error).toHaveBeenCalledWith('Limite de armazenamento excedido.');
  });

  it('shows generic error when api provides no message', () => {
    serviceMock.upload.mockReturnValueOnce(throwError(() => ({ error: {} })));

    component.selectedFile.set(new File(['content'], 'doc.txt', { type: 'text/plain' }));
    component.form.patchValue({ title: 'Meu documento' });

    component.submit();

    expect(toastMock.error).toHaveBeenCalledWith('Erro ao enviar documento.');
  });

  it('maps secured pdf parser message to a user-friendly message', () => {
    serviceMock.upload.mockReturnValueOnce(
      throwError(() => ({ error: { message: 'Secured pdf file are currently not supported.' } })),
    );

    component.selectedFile.set(new File(['content'], 'doc.pdf', { type: 'application/pdf' }));
    component.form.patchValue({ title: 'Meu PDF protegido' });

    component.submit();

    expect(toastMock.error).toHaveBeenCalledWith(
      'PDF protegido por senha não é suportado. Remova a proteção e tente novamente.',
    );
  });
});
