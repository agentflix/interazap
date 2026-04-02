import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { importProvidersFrom, provideZonelessChangeDetection } from '@angular/core';
import { of } from 'rxjs';
import { LucideAngularModule, icons } from 'lucide-angular';
import { NegotiationProductsTabComponent } from './negotiation-products-tab';
import { NegotiationProductService } from 'src/app/core/services/negotiation-product.service';
import { ProductServiceService } from 'src/app/core/services/product-service.service';

describe('NegotiationProductsTabComponent', () => {
  let fixture: ComponentFixture<NegotiationProductsTabComponent>;
  const negotiationProductServiceMock = {
    list: vi.fn().mockReturnValue(of({ data: { products: [], total: 0 } })),
    create: vi.fn().mockReturnValue(of({ data: {} })),
    update: vi.fn().mockReturnValue(of({ data: {} })),
    delete: vi.fn().mockReturnValue(of({ data: {} })),
  };
  const catalogServiceMock = {
    all: vi.fn().mockReturnValue(of({ data: [] })),
  };

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationProductsTabComponent],
      providers: [
        provideZonelessChangeDetection(),
        importProvidersFrom(LucideAngularModule.pick(icons)),
        { provide: NegotiationProductService, useValue: negotiationProductServiceMock },
        { provide: ProductServiceService, useValue: catalogServiceMock },
      ],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationProductsTabComponent);
    fixture.componentRef.setInput('negotiationId', '1');
    fixture.detectChanges();
  });

  it('creates and loads products', () => {
    expect(fixture.componentInstance).toBeTruthy();
    expect(negotiationProductServiceMock.list).toHaveBeenCalledWith('1');
  });
});
