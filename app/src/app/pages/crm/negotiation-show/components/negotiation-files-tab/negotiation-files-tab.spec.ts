import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { of } from 'rxjs';
import { NegotiationFileService } from 'src/app/core/services/negotiation-file.service';
import { NegotiationFilesTabComponent } from './negotiation-files-tab';

describe('NegotiationFilesTabComponent', () => {
  let fixture: ComponentFixture<NegotiationFilesTabComponent>;
  const serviceMock = {
    list: vi.fn().mockReturnValue(of({ data: { files: [] } })),
    upload: vi.fn().mockReturnValue(of({ data: {} })),
    delete: vi.fn().mockReturnValue(of({ data: {} })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationFilesTabComponent],
      providers: [
        provideZonelessChangeDetection(),
        { provide: NegotiationFileService, useValue: serviceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationFilesTabComponent);
    fixture.componentRef.setInput('negotiationId', '1');
    fixture.detectChanges();
  });

  it('creates and loads files', () => {
    expect(fixture.componentInstance).toBeTruthy();
    expect(serviceMock.list).toHaveBeenCalledWith('1');
  });
});
