import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { importProvidersFrom, provideZonelessChangeDetection } from '@angular/core';
import { of } from 'rxjs';
import { LucideAngularModule, icons } from 'lucide-angular';
import { NegotiationContactsTabComponent } from './negotiation-contacts-tab';
import { NegotiationContactService } from 'src/app/core/services/negotiation-contact.service';

describe('NegotiationContactsTabComponent', () => {
  let fixture: ComponentFixture<NegotiationContactsTabComponent>;
  const serviceMock = {
    list: vi.fn().mockReturnValue(of({ data: { contacts: [] } })),
    create: vi.fn().mockReturnValue(of({ data: {} })),
    update: vi.fn().mockReturnValue(of({ data: {} })),
    delete: vi.fn().mockReturnValue(of({ data: {} })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationContactsTabComponent],
      providers: [
        provideZonelessChangeDetection(),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: NegotiationContactService, useValue: serviceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationContactsTabComponent);
    fixture.componentRef.setInput('negotiationId', '1');
    fixture.componentRef.setInput('availableContacts', []);
    fixture.detectChanges();
  });

  it('creates and loads contact links', () => {
    expect(fixture.componentInstance).toBeTruthy();
    expect(serviceMock.list).toHaveBeenCalledWith('1');
  });
});
