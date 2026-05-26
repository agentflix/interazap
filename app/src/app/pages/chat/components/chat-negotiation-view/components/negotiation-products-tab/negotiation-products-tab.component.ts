import { CommonModule } from '@angular/common';
import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import type { FormControl } from '@angular/forms';
import { CurrencyInputComponent } from '@shared/components/inputs';
import { CurrencyPipe } from '@shared/pipes/currency.pipe';
import { type NegotiationProductItem } from 'src/app/core/services/negotiation-product.service';
import { type ProductService } from 'src/app/core/services/product-service.service';

/** Dados do formulário para adicionar produto à negociação. */
interface NegotiationProductForm {
  productId: string;
  quantity: number;
  price: string;
  discount: string;
}

/** Evento de alteração do formulário de produtos. */
interface ProductFieldChangeEvent {
  field: keyof NegotiationProductForm;
  value: string;
}

/** Evento de alteração de quantidade dos itens existentes. */
interface ProductQuantityChangeEvent {
  item: NegotiationProductItem;
  value: string;
}

/**
 * Aba de produtos da negociação — listagem e adição de itens de produto.
 *
 * @remarks
 * Componente presentacional puro. Exibe produtos já vinculados com controles de
 * quantidade e remoção, além de formulário para adicionar novos produtos com
 * preço e desconto. Emite eventos granulares ao container.
 */
@Component({
  selector: 'app-negotiation-products-tab',
  standalone: true,
  imports: [CommonModule, CurrencyPipe, CurrencyInputComponent],
  templateUrl: './negotiation-products-tab.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationProductsTabComponent {
  readonly productsTotal = input<number>(0);
  readonly productForm = input.required<NegotiationProductForm>();
  readonly productOptions = input<ProductService[]>([]);
  readonly productPriceControl = input.required<FormControl<number | null>>();
  readonly getItemPriceControl =
    input.required<(item: NegotiationProductItem) => FormControl<number | null>>();

  readonly isSavingProduct = input<boolean>(false);
  readonly productFormValid = input<boolean>(false);
  readonly isProductsLoading = input<boolean>(false);
  readonly productsError = input<string | null>(null);
  readonly products = input<NegotiationProductItem[]>([]);
  readonly pendingProductId = input<string | number | null>(null);

  readonly productFieldChange = output<ProductFieldChangeEvent>();
  readonly addProduct = output<void>();
  readonly productQuantityChange = output<ProductQuantityChangeEvent>();
  readonly removeProduct = output<NegotiationProductItem>();

  onProductSelectChange(field: keyof NegotiationProductForm, event: Event): void {
    this.productFieldChange.emit({
      field,
      value: this.readSelectValue(event),
    });
  }

  onProductInputChange(field: keyof NegotiationProductForm, event: Event): void {
    this.productFieldChange.emit({
      field,
      value: this.readInputValue(event),
    });
  }

  /** Emite o evento de adição de produto à negociação. */
  emitAddProduct(): void {
    this.addProduct.emit();
  }

  emitQuantityChange(item: NegotiationProductItem, event: Event): void {
    this.productQuantityChange.emit({
      item,
      value: this.readInputValue(event),
    });
  }

  /** Emite o evento de remoção de um produto da negociação. */
  emitRemoveProduct(item: NegotiationProductItem): void {
    this.removeProduct.emit(item);
  }

  private readInputValue(event: Event): string {
    return (event.target as HTMLInputElement | null)?.value ?? '';
  }

  private readSelectValue(event: Event): string {
    return (event.target as HTMLSelectElement | null)?.value ?? '';
  }
}
