import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ReactiveFormsModule, Validators, NonNullableFormBuilder } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';

import { toast } from 'ngx-sonner';
import {
  type NegotiationProductItem,
  NegotiationProductService,
} from 'src/app/core/services/negotiation-product.service';
import {
  type ProductService,
  ProductServiceService,
} from 'src/app/core/services/product-service.service';
import { ButtonComponent, IconButtonComponent } from '@shared/components/buttons';
import { ConfirmModalComponent } from '@shared/components/confirm-modal/confirm-modal';
import { ModalComponent } from '@shared/components/modal/modal';
import { LoadingButtonComponent } from '@shared/components/loading-button/loading-button';
import {
  CurrencyInputComponent,
  SelectInputComponent,
  TextInputComponent,
} from '@shared/components/inputs';
import { formatCurrency } from '@shared/utils/currency';

/**
 * Tab content responsible for negotiation products and services CRUD.
 */
@Component({
  selector: 'app-negotiation-products-tab',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    ButtonComponent,
    IconButtonComponent,
    LoadingButtonComponent,
    ModalComponent,
    ConfirmModalComponent,
    TextInputComponent,
    SelectInputComponent,
    CurrencyInputComponent,
  ],
  templateUrl: './negotiation-products-tab.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NegotiationProductsTabComponent implements OnInit {
  private readonly productService = inject(NegotiationProductService);
  private readonly catalogService = inject(ProductServiceService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(NonNullableFormBuilder);

  readonly negotiationId = input.required<string | number>();
  readonly productsChanged = output<void>();
  readonly failed = output<string>();

  readonly products = signal<NegotiationProductItem[]>([]);
  readonly productsTotal = signal(0);
  readonly isProductsLoading = signal(false);
  readonly isProductModalOpen = signal(false);
  readonly isProductSaving = signal(false);
  readonly productModalError = signal<string | null>(null);
  readonly editingProduct = signal<NegotiationProductItem | null>(null);
  readonly deletingProduct = signal<NegotiationProductItem | null>(null);
  readonly productCatalog = signal<ProductService[]>([]);
  readonly isProductCatalogLoading = signal(false);

  readonly productForm = this.fb.group({
    product_id: this.fb.control('', Validators.required),
    quantity: this.fb.control(1, [Validators.required, Validators.min(1)]),
    price: this.fb.control(0, [Validators.required, Validators.min(0)]),
    discount: this.fb.control(0, [Validators.min(0)]),
  });

  readonly productsSummary = computed(() => ({
    total: this.productsTotal(),
    count: this.products().length,
  }));

  readonly productSelectOptions = computed(() => {
    const options = this.productCatalog().map((product) => {
      const stockInfo = this.getProductStockLabel(product);
      return {
        label: `${product.name} - ${this.formatCurrency(product.price || 0)}${stockInfo}`,
        value: String(product.id),
      };
    });

    const editing = this.editingProduct();
    if (!editing || !editing.product_id) return options;

    const hasEditingOption = options.some(
      (option) => String(option.value) === String(editing.product_id),
    );

    if (hasEditingOption) return options;

    const fallbackName = editing.product?.name || 'Produto';
    const fallbackPrice = editing.price ?? editing.product?.price ?? 0;

    return [
      {
        label: `${fallbackName} - ${this.formatCurrency(fallbackPrice)}`,
        value: String(editing.product_id),
      },
      ...options,
    ];
  });

  readonly selectedProductStockInfo = computed(() => {
    const selectedId = this.productForm.controls.product_id.value;
    if (!selectedId) return null;

    const product = this.productCatalog().find((item) => String(item.id) === String(selectedId));
    if (!product) return null;

    return {
      track_stock: product.track_stock ?? false,
      stock: product.stock ?? 0,
      stock_quantity: product.stock_quantity ?? 0,
    };
  });

  constructor() {
    this.productForm.controls.product_id.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        if (value) {
          this.onProductSelectChange(value);
        }
      });
  }

  ngOnInit(): void {
    this.loadProducts();
    this.loadProductCatalog();
  }

  loadProducts(): void {
    this.isProductsLoading.set(true);
    this.productService
      .list(this.negotiationId())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.products.set(response.data.products ?? []);
          this.productsTotal.set(response.data.total ?? 0);
          this.isProductsLoading.set(false);
        },
        error: () => {
          this.products.set([]);
          this.productsTotal.set(0);
          this.isProductsLoading.set(false);
          this.failed.emit('Não foi possível carregar os produtos da negociação.');
        },
      });
  }

  loadProductCatalog(): void {
    this.isProductCatalogLoading.set(true);
    this.catalogService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.productCatalog.set(response.data ?? []);
          this.isProductCatalogLoading.set(false);
        },
        error: () => {
          this.productCatalog.set([]);
          this.isProductCatalogLoading.set(false);
        },
      });
  }

  openProductModal(product?: NegotiationProductItem): void {
    this.editingProduct.set(product ?? null);
    this.productModalError.set(null);
    this.isProductModalOpen.set(true);

    this.productForm.reset({
      product_id: product ? String(product.product_id) : '',
      quantity: product?.quantity ?? 1,
      price: product?.price ?? 0,
      discount: product?.discount ?? 0,
    });
  }

  closeProductModal(): void {
    this.isProductModalOpen.set(false);
    this.editingProduct.set(null);
    this.isProductSaving.set(false);
  }

  onProductSelectChange(value: string | number): void {
    const selected = this.productCatalog().find((item) => String(item.id) === String(value));
    if (selected && selected.price !== undefined && selected.price !== null) {
      this.productForm.controls.price.setValue(Number(selected.price) || 0);
    }

    if (selected && (selected.track_stock ?? false)) {
      const availableStock = selected.stock ?? 0;
      const currentQty = this.productForm.controls.quantity.value;
      if (currentQty > availableStock) {
        this.productForm.controls.quantity.setValue(availableStock);
      }
    }
  }

  getProductStockLabel(product: ProductService): string {
    if (product.type === 'service') return '';
    if (!(product.track_stock ?? false)) return ' (ilimitado)';
    const stock = product.stock ?? 0;
    return ` (estoque: ${stock})`;
  }

  getStockBadgeClass(): string {
    const info = this.selectedProductStockInfo();
    if (!info) return '';
    if (!info.track_stock) {
      return 'bg-emerald-500/10 text-emerald-600 border-emerald-500/30';
    }
    if (info.stock === 0) {
      return 'bg-red-500/10 text-red-600 border-red-500/30';
    }
    if (info.stock <= 5) {
      return 'bg-amber-500/10 text-amber-600 border-amber-500/30';
    }
    return 'bg-blue-500/10 text-blue-600 border-blue-500/30';
  }

  getStockBadgeLabel(): string {
    const info = this.selectedProductStockInfo();
    if (!info) return '';
    if (!info.track_stock) return 'Estoque ilimitado';
    if (info.stock === 0) return 'Sem estoque';
    return `${info.stock} em estoque`;
  }

  getQuantityMax(): number | null {
    const info = this.selectedProductStockInfo();
    if (info && info.track_stock) {
      return info.stock;
    }
    return null;
  }

  saveProduct(): void {
    if (this.productForm.invalid) {
      this.productForm.markAllAsTouched();
      return;
    }

    const value = this.productForm.getRawValue();
    const editing = this.editingProduct();
    const selectedProductId = value.product_id ? String(value.product_id) : undefined;
    const selectedCatalogItem = this.productCatalog().find(
      (item) => String(item.id) === String(selectedProductId),
    );
    const resolvedPrice = Number(value.price ?? selectedCatalogItem?.price ?? 0) || 0;

    const payload = {
      product_id: selectedProductId,
      crm_product_id: selectedProductId,
      name: selectedCatalogItem?.name ?? editing?.product?.name ?? 'Item',
      quantity: value.quantity,
      unit_price: resolvedPrice,
      price: resolvedPrice,
      discount: value.discount || 0,
    };

    this.isProductSaving.set(true);

    const request = editing
      ? this.productService.update(this.negotiationId(), editing.id, payload)
      : this.productService.create(this.negotiationId(), payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isProductSaving.set(false);
        toast.success(editing ? 'Produto atualizado.' : 'Produto adicionado.');
        this.closeProductModal();
        this.loadProducts();
        this.productsChanged.emit();
      },
      error: (apiError) => {
        this.isProductSaving.set(false);
        const apiMessage =
          apiError?.error?.errors?.stock?.[0] ??
          apiError?.error?.errors?.crm_product_id?.[0] ??
          apiError?.error?.message;
        this.productModalError.set(apiMessage || 'Não foi possível salvar o produto.');
      },
    });
  }

  confirmDeleteProduct(product: NegotiationProductItem): void {
    this.deletingProduct.set(product);
  }

  cancelDeleteProduct(): void {
    this.deletingProduct.set(null);
  }

  deleteProduct(): void {
    const product = this.deletingProduct();
    if (!product) return;

    this.productService
      .delete(this.negotiationId(), product.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          toast.success('Produto removido.');
          this.deletingProduct.set(null);
          this.loadProducts();
          this.productsChanged.emit();
        },
        error: () => {
          this.failed.emit('Não foi possível remover o produto.');
        },
      });
  }

  getProductFormTotal(): number {
    const value = this.productForm.getRawValue();
    const quantity = Number(value.quantity) || 0;
    const price = Number(value.price) || 0;
    const discount = Number(value.discount) || 0;
    return Math.max(0, quantity * price - discount);
  }

  formatCurrency(value?: number | null): string {
    return formatCurrency(value);
  }
}
