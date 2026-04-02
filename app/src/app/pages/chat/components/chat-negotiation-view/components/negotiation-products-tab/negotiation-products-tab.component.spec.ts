import { FormControl } from '@angular/forms';
import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { type NegotiationProductItem } from 'src/app/core/services/negotiation-product.service';
import { type ProductService } from 'src/app/core/services/product-service.service';
import { NegotiationProductsTabComponent } from './negotiation-products-tab.component';

describe('NegotiationProductsTabComponent', () => {
  let component: NegotiationProductsTabComponent;
  let fixture: ComponentFixture<NegotiationProductsTabComponent>;

  const productOptions = [{ id: 'product-1', name: 'Plano Plus' }] as ProductService[];
  const productItem = {
    id: 'item-1',
    quantity: 2,
    total: 400,
    product: { name: 'Plano Plus' },
  } as NegotiationProductItem;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [NegotiationProductsTabComponent],
      providers: [provideZonelessChangeDetection()],
    }).compileComponents();

    fixture = TestBed.createComponent(NegotiationProductsTabComponent);
    component = fixture.componentInstance;

    fixture.componentRef.setInput('productsTotal', 400);
    fixture.componentRef.setInput('productForm', {
      productId: '',
      quantity: 1,
      price: '',
      discount: '',
    });
    fixture.componentRef.setInput('productOptions', productOptions);
    fixture.componentRef.setInput('productPriceControl', new FormControl<number | null>(200));
    fixture.componentRef.setInput(
      'getItemPriceControl',
      () => new FormControl<number | null>(productItem.total ?? null),
    );
    fixture.componentRef.setInput('productFormValid', true);
    fixture.componentRef.setInput('products', [productItem]);
    fixture.detectChanges();
  });

  it('emits productFieldChange and addProduct from the add product form', () => {
    const productFieldChangeSpy = vi.fn<(value: { field: string; value: string }) => void>();
    const addProductSpy = vi.fn<() => void>();

    component.productFieldChange.subscribe(productFieldChangeSpy);
    component.addProduct.subscribe(addProductSpy);

    const productSelect = fixture.nativeElement.querySelector('select') as HTMLSelectElement | null;
    const quantityInput = fixture.nativeElement.querySelector(
      'input[type="number"]:not(.text-right)',
    ) as HTMLInputElement | null;
    const addProductButtons = Array.from(
      fixture.nativeElement.querySelectorAll('button[type="button"]'),
    ) as HTMLButtonElement[];
    const addProductButton = addProductButtons.find((button) =>
      button.textContent?.includes('Adicionar'),
    );

    if (!productSelect || !quantityInput || !addProductButton) {
      throw new Error('Expected product form controls to exist.');
    }

    productSelect.value = 'product-1';
    productSelect.dispatchEvent(new Event('change'));

    quantityInput.value = '3';
    quantityInput.dispatchEvent(new Event('input'));
    addProductButton.click();

    expect(productFieldChangeSpy).toHaveBeenNthCalledWith(1, {
      field: 'productId',
      value: 'product-1',
    });
    expect(productFieldChangeSpy).toHaveBeenNthCalledWith(2, {
      field: 'quantity',
      value: '3',
    });
    expect(addProductSpy).toHaveBeenCalledTimes(1);
  });

  it('emits productQuantityChange for an existing item', () => {
    const productQuantityChangeSpy =
      vi.fn<(value: { item: NegotiationProductItem; value: string }) => void>();
    component.productQuantityChange.subscribe(productQuantityChangeSpy);

    const quantityInputs = Array.from(
      fixture.nativeElement.querySelectorAll('input[type="number"]'),
    ) as HTMLInputElement[];
    const existingItemQuantityInput = quantityInputs[1];

    existingItemQuantityInput.value = '5';
    existingItemQuantityInput.dispatchEvent(new Event('change'));

    expect(productQuantityChangeSpy).toHaveBeenCalledWith({
      item: productItem,
      value: '5',
    });
  });

  it('emits removeProduct when the remove action is clicked', () => {
    const removeProductSpy = vi.fn<(value: NegotiationProductItem) => void>();
    component.removeProduct.subscribe(removeProductSpy);

    const removeButtons = Array.from(
      fixture.nativeElement.querySelectorAll('button[type="button"]'),
    ) as HTMLButtonElement[];
    const removeButton = removeButtons.find((button) => button.textContent?.includes('Remover'));

    removeButton?.click();

    expect(removeProductSpy).toHaveBeenCalledWith(productItem);
  });
});
