import { CommonModule } from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  Input,
  computed,
  inject,
  signal,
  effect,
  untracked,
} from '@angular/core';
import { FormControl } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NgIcon, provideIcons } from '@ng-icons/core';
import { lucideLoader2 } from '@ng-icons/lucide';
import { toast } from 'ngx-sonner';
import { type Contact } from 'src/app/core/models/contact.model';
import { type CalledContactSummary } from 'src/app/core/services/called.service';
import { type Negotiation, NegotiationService } from 'src/app/core/services/negotiation.service';
import { ChatRealtimeService } from 'src/app/core/services/chat-realtime.service';
import {
  type NegotiationProductItem,
  NegotiationProductService,
} from 'src/app/core/services/negotiation-product.service';
import {
  type NegotiationTask,
  NegotiationTaskService,
} from 'src/app/core/services/negotiation-task.service';
import { type Funnel, type FunnelStep, FunnelService } from 'src/app/core/services/funnel.service';
import {
  type ProductService,
  ProductServiceService,
} from 'src/app/core/services/product-service.service';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';
import { NegotiationTimelineComponent } from './components/negotiation-timeline/negotiation-timeline.component';
import {
  NegotiationActionsComponent,
  type NotifyChannel,
  type TaskFieldChangeEvent,
} from './components/negotiation-actions/negotiation-actions.component';
import { NegotiationProductsTabComponent } from './components/negotiation-products-tab/negotiation-products-tab.component';

type ChatNegotiationContact =
  | Contact
  | (CalledContactSummary & { crm_company_id?: string | number | null });

interface NegotiationCreateForm {
  title: string;
  funnelId: string;
  stepId: string;
  expectedClose: string;
  notes: string;
  companyId: string;
}

interface NegotiationDetailForm {
  funnelId: string;
  stepId: string;
  expectedClose: string;
}

interface NegotiationProductForm {
  productId: string;
  quantity: number;
  price: string;
  discount: string;
}

interface NegotiationTaskForm {
  title: string;
  actionType: string;
  dueDate: string;
  startTime: string;
  endTime: string;
  notifyChannel: NotifyChannel;
}

/**
 * Componente principal para gestão de negociações (oportunidades) vinculadas a um contato.
 *
 * Permite listar negociações existentes, criar novas, gerenciar itens/produtos,
 * agendar e alternar status de tarefas, e atualizar o estágio do funil de vendas.
 *
 * @class ChatNegotiationView
 * @description Centralizador de CRM para negociações no contexto do atendimento.
 */
@Component({
  selector: 'app-chat-negotiation-view',
  standalone: true,
  imports: [
    CommonModule,
    NgIcon,
    AfScrollAreaComponent,
    NegotiationTimelineComponent,
    NegotiationActionsComponent,
    NegotiationProductsTabComponent,
  ],
  templateUrl: './chat-negotiation-view.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  viewProviders: [
    provideIcons({
      lucideLoader2,
    }),
  ],
  host: {
    class: 'block h-full',
  },
})
export class ChatNegotiationView {
  private readonly negotiationService = inject(NegotiationService);
  private readonly negotiationTaskService = inject(NegotiationTaskService);
  private readonly negotiationProductService = inject(NegotiationProductService);
  private readonly funnelService = inject(FunnelService);
  private readonly productCatalogService = inject(ProductServiceService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly realtime = inject(ChatRealtimeService);

  private listRequestToken = 0;
  private detailRequestToken = 0;
  private productsRequestToken = 0;
  private tasksRequestToken = 0;

  /**
   * Armazenamento reativo dos dados básicos do contato ativo.
   * @private
   */
  private readonly contactSignal = signal<ChatNegotiationContact | null>(null);

  /**
   * Dados do contato ativo acessíveis publicamente.
   * @type {Signal<ChatNegotiationContact | null>}
   */
  readonly currentContact = computed(() => this.contactSignal());

  /**
   * Lista de negociações vinculadas ao contato.
   * @type {WritableSignal<Negotiation[]>}
   */
  readonly negotiations = signal<Negotiation[]>([]);

  /**
   * Indica se a lista de negociações está sendo carregada inicialmente.
   * @type {WritableSignal<boolean>}
   */
  readonly isLoadingList = signal(false);

  /**
   * Armazena mensagens de erro ocorridas durante a listagem.
   * @type {WritableSignal<string | null>}
   */
  readonly listError = signal<string | null>(null);

  /**
   * ID da negociação selecionada para visualização detalhada.
   * @type {WritableSignal<string | number | null>}
   */
  readonly selectedNegotiationId = signal<string | number | null>(null);

  /**
   * Objeto completo da negociação selecionada.
   * @type {WritableSignal<Negotiation | null>}
   */
  readonly selectedNegotiation = signal<Negotiation | null>(null);

  /**
   * Indica se os detalhes da negociação selecionada estão sendo carregados.
   * @type {WritableSignal<boolean>}
   */
  readonly isLoadingDetail = signal(false);

  /**
   * Armazena mensagens de erro persistentes do carregamento de detalhe.
   * @type {WritableSignal<string | null>}
   */
  readonly detailError = signal<string | null>(null);

  /**
   * Lista de funis de venda disponíveis no sistema.
   * @type {WritableSignal<Funnel[]>}
   */
  readonly funnels = signal<Funnel[]>([]);

  /**
   * Dicionário de etapas mapeadas por ID de funil para carregamento otimizado.
   * @type {WritableSignal<Record<string, FunnelStep[]>>}
   */
  readonly funnelSteps = signal<Record<string, FunnelStep[]>>({});

  /**
   * Lista de produtos/serviços vinculados à negociação selecionada.
   * @type {WritableSignal<NegotiationProductItem[]>}
   */
  readonly products = signal<NegotiationProductItem[]>([]);

  /**
   * Valor total da negociação baseado na soma dos produtos.
   * @type {WritableSignal<number>}
   */
  readonly productsTotal = signal(0);

  /**
   * Indica se a lista de produtos da negociação está carregando.
   * @type {WritableSignal<boolean>}
   */
  readonly isProductsLoading = signal(false);

  /**
   * Armazena mensagens de erro persistentes do carregamento de produtos.
   * @type {WritableSignal<string | null>}
   */
  readonly productsError = signal<string | null>(null);

  /**
   * ID do produto que está com alteração pendente (update/remove).
   * @type {WritableSignal<string | number | null>}
   */
  readonly pendingProductId = signal<string | number | null>(null);

  /**
   * Lista de tarefas vinculadas à negociação selecionada.
   * @type {WritableSignal<NegotiationTask[]>}
   */
  readonly tasks = signal<NegotiationTask[]>([]);

  /**
   * Indica se a lista de tarefas da negociação está carregando.
   * @type {WritableSignal<boolean>}
   */
  readonly isTasksLoading = signal(false);

  /**
   * Armazena mensagens de erro persistentes do carregamento de tarefas.
   * @type {WritableSignal<string | null>}
   */
  readonly tasksError = signal<string | null>(null);

  /**
   * ID da tarefa que está em processo de conclusão/reabertura.
   * @type {WritableSignal<string | number | null>}
   */
  readonly completingTaskId = signal<string | number | null>(null);

  /**
   * Opções de produtos/serviços do catálogo para adição à negociação.
   * @type {WritableSignal<ProductService[]>}
   */
  readonly productOptions = signal<ProductService[]>([]);

  /**
   * Indica se as opções do catálogo de produtos estão carregando.
   * @type {WritableSignal<boolean>}
   */
  readonly isProductOptionsLoading = signal(false);

  /**
   * Estado do formulário de criação de uma nova negociação.
   * @type {WritableSignal<NegotiationCreateForm>}
   */
  readonly createForm = signal<NegotiationCreateForm>({
    title: '',
    funnelId: '',
    stepId: '',
    expectedClose: '',
    notes: '',
    companyId: '',
  });

  /**
   * Indica se o processo de criação de negociação está em curso.
   * @type {WritableSignal<boolean>}
   */
  readonly isCreating = signal(false);

  /**
   * Armazena mensagens de erro ocorridas durante a criação.
   * @type {WritableSignal<string | null>}
   */
  readonly createError = signal<string | null>(null);

  /**
   * Estado do formulário de atualização de detalhes da negociação selecionada.
   * @type {WritableSignal<NegotiationDetailForm>}
   */
  readonly detailForm = signal<NegotiationDetailForm>({
    funnelId: '',
    stepId: '',
    expectedClose: '',
  });

  /**
   * Indica se a salvamento dos detalhes da negociação está ocorrendo.
   * @type {WritableSignal<boolean>}
   */
  readonly isSavingDetails = signal(false);

  /**
   * Estado do formulário para adicionar um produto à negociação.
   * @type {WritableSignal<NegotiationProductForm>}
   */
  readonly productForm = signal<NegotiationProductForm>({
    productId: '',
    quantity: 1,
    price: '',
    discount: '',
  });

  /** FormControl reativo para o campo de preço do novo produto */
  readonly productPriceControl = new FormControl<number | null>(null);

  /** Map de FormControls para edição de preço de itens existentes */
  private readonly itemPriceControlsMap = new Map<string | number, FormControl<number | null>>();

  readonly getItemPriceControlFn = (item: NegotiationProductItem): FormControl<number | null> =>
    this.getItemPriceControl(item);

  /**
   * Obtém ou cria o FormControl de preço para um item existente na negociação.
   * @param item Item do produto na negociação.
   * @returns FormControl sincronizado com o preço do item.
   */
  getItemPriceControl(item: NegotiationProductItem): FormControl<number | null> {
    let ctrl = this.itemPriceControlsMap.get(item.id);
    if (!ctrl) {
      ctrl = new FormControl<number | null>(item.price ?? null);
      ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((value) => {
        if (value !== null && !Number.isNaN(value)) {
          this.updateProduct(item, { price: value });
        }
      });
      this.itemPriceControlsMap.set(item.id, ctrl);
    }
    return ctrl;
  }

  /**
   * Indica se a adição do produto está send processada.
   * @type {WritableSignal<boolean>}
   */
  readonly isSavingProduct = signal(false);

  /**
   * Estado do formulário para agendamento de uma nova tarefa na negociação.
   * @type {WritableSignal<NegotiationTaskForm>}
   */
  readonly taskForm = signal<NegotiationTaskForm>({
    title: '',
    actionType: 'call',
    dueDate: '',
    startTime: '',
    endTime: '',
    notifyChannel: 'whatsapp',
  });

  /**
   * Indica se a criação da tarefa está sendo processada.
   * @type {WritableSignal<boolean>}
   */
  readonly isSavingTask = signal(false);

  /**
   * Verifica se o contato possui alguma negociação registrada.
   * @type {Signal<boolean>}
   */
  readonly hasNegotiations = computed(() => this.negotiations().length > 0);

  /**
   * Valida se as condições mínimas para criação de uma negociação foram atendidas no formulário.
   * @type {Signal<boolean>}
   */
  readonly canCreateNegotiation = computed(() => {
    const contact = this.contactSignal();
    const form = this.createForm();
    return (
      Boolean(contact) &&
      form.title.trim().length > 3 &&
      Boolean(form.funnelId) &&
      Boolean(form.stepId) &&
      Boolean(contact?.crm_company_id || form.companyId.trim().length > 0)
    );
  });

  /**
   * Verifica se o formulário de detalhes possui alterações em relação aos dados originais da negociação.
   * @type {Signal<boolean>}
   */
  readonly canSaveDetails = computed(() => {
    const negotiation = this.selectedNegotiation();
    if (!negotiation) return false;
    const form = this.detailForm();
    const expected = this.formatDateInput(negotiation.expected_close_date);
    return (
      Boolean(form.funnelId && String(form.funnelId) !== String(negotiation.funnel_id)) ||
      Boolean(form.stepId && String(form.stepId) !== String(negotiation.step_id)) ||
      form.expectedClose !== expected
    );
  });

  /**
   * Valida o preenchimento correto do formulário de adição de produto.
   * @type {Signal<boolean>}
   */
  readonly productFormValid = computed(() => {
    const form = this.productForm();
    return (
      Boolean(form.productId) &&
      form.quantity > 0 &&
      form.price.trim().length > 0 &&
      !Number.isNaN(Number(form.price))
    );
  });

  /**
   * Valida o preenchimento mínimo do formulário de tarefa.
   * @type {Signal<boolean>}
   */
  readonly taskFormValid = computed(() => this.taskForm().title.trim().length > 0);

  /**
   * Obter a lista de etapas para um determinado funil.
   * @param funnelId ID do funil desejado.
   * @returns {FunnelStep[]} Array de etapas do funil.
   */
  stepsFor(funnelId: string | number | ''): FunnelStep[] {
    if (!funnelId) return [];
    return this.funnelSteps()[String(funnelId)] ?? [];
  }

  /**
   * Verificar se a negociação fornecida é a que está atualmente ativa na visualização.
   * @param id ID da negociação.
   * @returns {boolean}
   */
  isSelectedNegotiation(id: string | number): boolean {
    return String(this.selectedNegotiationId()) === String(id);
  }

  /**
   * Tratar a mudança de quantidade de um produto na lista de itens da negociação.
   * @param item Objeto do item do produto na negociação.
   * @param value Nova quantidade em string.
   */
  onProductQuantityChange(item: NegotiationProductItem, value: string): void {
    const parsed = Number(value);
    if (Number.isNaN(parsed) || parsed <= 0) return;
    this.updateProduct(item, { quantity: parsed });
  }

  /**
   * Tratar a mudança de preço de um produto na lista de itens da negociação.
   * @param item Objeto do item do produto na negociação.
   * @param value Novo preço em string.
   */
  onProductPriceChange(item: NegotiationProductItem, value: string): void {
    const parsed = Number(value);
    if (Number.isNaN(parsed)) return;
    this.updateProduct(item, { price: parsed });
  }

  /**
   * Propriedade de entrada para receber o contato do atendimento selecionado.
   * @param value Resumo ou objeto completo do contato.
   */
  @Input() set contact(value: ChatNegotiationContact | null) {
    this.contactSignal.set(value);
    this.resetView();
    if (value) {
      this.prefillCreateForm(value);
      this.loadNegotiations();
    }
  }

  constructor() {
    this.loadFunnels();
    this.loadProductCatalog();

    // Sincronizar productPriceControl com productForm signal
    this.productPriceControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => {
        const priceStr = value !== null && value !== undefined ? String(value) : '';
        this.productForm.update((state) => ({ ...state, price: priceStr }));
      });

    // Effect para atualização de deal via WebSocket
    effect(() => {
      const { version } = this.realtime.dealUpdated();
      if (version > 0) {
        untracked(() => {
          if (this.contactSignal()) {
            this.loadNegotiations();
          }
        });
      }
    });
  }

  /**
   * Tratar alteração de campos no formulário de criação de negociação.
   * @param field Nome do campo no estado do formulário.
   * @param value Novo valor do campo.
   */
  onCreateFieldChange(field: keyof NegotiationCreateForm, value: string): void {
    this.createForm.update((state) => ({ ...state, [field]: value }));
    if (field === 'funnelId' && value) {
      this.loadStepsForFunnel(value);
      this.createForm.update((state) => ({ ...state, stepId: '' }));
    }
  }

  /**
   * Tratar alteração de campos no formulário de atualização de detalhes.
   * @param field Nome do campo no estado do formulário.
   * @param value Novo valor do campo.
   */
  onDetailFieldChange(field: string, value: string): void {
    this.detailForm.update((state) => ({ ...state, [field]: value }));
    if (field === 'funnelId' && value) {
      this.loadStepsForFunnel(value);
      this.detailForm.update((state) => ({ ...state, stepId: '' }));
    }
  }

  /**
   * Tratar alteração de campos no formulário de adição de produto.
   * @param field Nome do campo no estado do formulário.
   * @param value Novo valor do campo.
   */
  onProductFieldChange(field: keyof NegotiationProductForm, value: string): void {
    if (field === 'quantity') {
      const parsed = Number(value);
      this.productForm.update((state) => ({
        ...state,
        quantity: Number.isNaN(parsed) ? 1 : parsed,
      }));
      return;
    }
    this.productForm.update((state) => ({ ...state, [field]: value }));
  }

  /**
   * Tratar alteração de campos no formulário de criação de tarefa.
   * @param field Nome do campo no estado do formulário.
   * @param value Novo valor do campo.
   */
  onTaskFieldChange(event: TaskFieldChangeEvent): void {
    if (event.field === 'notifyChannel') {
      this.taskForm.update((state) => ({
        ...state,
        notifyChannel: this.normalizeNotifyChannel(event.value),
      }));
      return;
    }

    this.taskForm.update((state) => ({ ...state, [event.field]: event.value }));
  }

  /**
   * Processar a criação de uma nova negociação via API.
   */
  createNegotiation(): void {
    const contact = this.contactSignal();
    if (!contact || !this.canCreateNegotiation()) return;
    const form = this.createForm();
    this.createError.set(null);
    this.isCreating.set(true);
    this.negotiationService
      .create({
        title: form.title.trim(),
        funnel_id: form.funnelId,
        step_id: form.stepId,
        contact_id: contact.id,
        crm_company_id: contact.crm_company_id ?? form.companyId.trim(),
        expected_close_date: form.expectedClose || undefined,
        notes: form.notes.trim() || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isCreating.set(false);
          toast.success('Negociação criada com sucesso.');
          this.loadNegotiations(response.data.negotiation.id);
          this.createForm.set({
            title: '',
            funnelId: form.funnelId,
            stepId: form.stepId,
            expectedClose: '',
            notes: '',
            companyId: form.companyId,
          });
        },
        error: () => {
          this.isCreating.set(false);
          this.createError.set('Não foi possível criar a negociação.');
          toast.error('Falha ao criar negociação.');
        },
      });
  }

  /**
   * Selecionar uma negociação existente para visualizar e editar detalhes.
   * @param id ID da negociação.
   */
  selectNegotiation(id: string | number): void {
    if (String(this.selectedNegotiationId()) === String(id)) return;
    this.selectedNegotiationId.set(id);
    this.loadNegotiationDetail(id);
  }

  /**
   * Salvar as alterações feitas nos detalhes do funil ou data da negociação.
   */
  saveDetails(): void {
    const negotiation = this.selectedNegotiation();
    if (!negotiation || !this.canSaveDetails()) return;
    const form = this.detailForm();
    this.isSavingDetails.set(true);
    this.negotiationService
      .update(negotiation.id, {
        funnel_id: form.funnelId || undefined,
        step_id: form.stepId || undefined,
        expected_close_date: form.expectedClose || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSavingDetails.set(false);
          this.selectedNegotiation.set(response.data.negotiation);
          this.syncDetailForm(response.data.negotiation);
          this.updateListNegotiation(response.data.negotiation);
          toast.success('Negociação atualizada.');
        },
        error: () => {
          this.isSavingDetails.set(false);
          toast.error('Não foi possível atualizar a negociação.');
        },
      });
  }

  /**
   * Adicionar um novo produto do catálogo à negociação ativa.
   */
  addProduct(): void {
    const negotiation = this.selectedNegotiation();
    const form = this.productForm();
    if (!negotiation || !this.productFormValid()) return;
    this.isSavingProduct.set(true);
    this.negotiationProductService
      .create(negotiation.id, {
        product_id: form.productId,
        quantity: form.quantity,
        price: Number(form.price),
        discount: form.discount ? Number(form.discount) : undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSavingProduct.set(false);
          toast.success('Produto adicionado.');
          this.productForm.set({
            productId: '',
            quantity: 1,
            price: '',
            discount: '',
          });
          this.productPriceControl.setValue(null, { emitEvent: false });
          this.loadProducts(negotiation.id);
        },
        error: () => {
          this.isSavingProduct.set(false);
          toast.error('Não foi possível adicionar o produto.');
        },
      });
  }

  /**
   * Atualizar propriedades (preço ou quantidade) de um produto já vinculado à negociação.
   * @param item Objeto do item do produto.
   * @param changes Objeto contendo as alterações parciais.
   */
  updateProduct(
    item: NegotiationProductItem,
    changes: Partial<{ quantity: number; price: number; discount: number }>,
  ): void {
    const negotiation = this.selectedNegotiation();
    if (!negotiation) return;
    this.pendingProductId.set(item.id);
    this.negotiationProductService
      .update(negotiation.id, item.id, changes)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.pendingProductId.set(null);
          toast.success('Produto atualizado.');
          this.loadProducts(negotiation.id);
        },
        error: () => {
          this.pendingProductId.set(null);
          toast.error('Não foi possível atualizar o produto.');
        },
      });
  }

  /**
   * Remover um produto da lista de itens da negociação ativa.
   * @param item Objeto do item do produto.
   */
  removeProduct(item: NegotiationProductItem): void {
    const negotiation = this.selectedNegotiation();
    if (!negotiation) return;
    this.pendingProductId.set(item.id);
    this.negotiationProductService
      .delete(negotiation.id, item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.pendingProductId.set(null);
          toast.success('Item removido.');
          this.loadProducts(negotiation.id);
        },
        error: () => {
          this.pendingProductId.set(null);
          toast.error('Não foi possível remover o item.');
        },
      });
  }

  /**
   * Agendar uma nova tarefa de acompanhamento para a negociação ativa.
   */
  createTask(): void {
    const negotiation = this.selectedNegotiation();
    if (!negotiation || !this.taskFormValid()) return;
    const form = this.taskForm();
    this.isSavingTask.set(true);
    this.negotiationTaskService
      .create(negotiation.id, {
        title: form.title.trim(),
        action_type: form.actionType,
        due_date: form.dueDate || null,
        start_time: form.startTime || null,
        end_time: form.endTime || null,
        notify_whatsapp: form.notifyChannel === 'whatsapp',
        notify_email: form.notifyChannel === 'email',
        notify_push: form.notifyChannel === 'push',
        notify_ui: form.notifyChannel === 'none' ? true : undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSavingTask.set(false);
          toast.success('Tarefa criada.');
          this.taskForm.set({
            title: '',
            actionType: form.actionType,
            dueDate: '',
            startTime: '',
            endTime: '',
            notifyChannel: form.notifyChannel,
          });
          this.loadTasks(negotiation.id);
        },
        error: () => {
          this.isSavingTask.set(false);
          toast.error('Não foi possível criar a tarefa.');
        },
      });
  }

  /**
   * Alternar o estado de conclusão (pendente/concluída) de uma tarefa.
   * @param task Objeto da tarefa a ser alterado.
   */
  toggleTask(task: NegotiationTask): void {
    const negotiation = this.selectedNegotiation();
    if (!negotiation) return;
    this.completingTaskId.set(task.id);
    this.negotiationTaskService
      .toggle(negotiation.id, task.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.completingTaskId.set(null);
          this.tasks.update((items) =>
            items.map((item) => (String(item.id) === String(task.id) ? response.data : item)),
          );
        },
        error: () => {
          this.completingTaskId.set(null);
          toast.error('Não foi possível atualizar a tarefa.');
        },
      });
  }

  /**
   * Formatar uma string de data para o padrão local (DD/MM/AAAA).
   * @param value String de data ISO.
   * @returns {string} Data formatada.
   */
  formatDate(value?: string | null): string {
    if (!value) return '-';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '-';
    return date.toLocaleDateString('pt-BR');
  }

  /**
   * Obter a classe CSS de cor baseada no status da negociação.
   * @param status Status da negociação (won, lost, open).
   * @returns {string} Classes CSS do Tailwind.
   */
  statusTone(status?: string | null): string {
    switch (status) {
      case 'won':
        return 'bg-success/10 text-success';
      case 'lost':
        return 'bg-danger/10 text-danger';
      default:
        return 'bg-primary/10 text-primary';
    }
  }

  /**
   * Resetar o estado interno de visualização para um novo contato ou fechamento.
   * @private
   */
  private resetView(): void {
    this.bumpListRequestToken();
    this.cancelSelectionRequests();
    this.isLoadingList.set(false);
    this.negotiations.set([]);
    this.listError.set(null);
    this.createError.set(null);
    this.selectedNegotiationId.set(null);
    this.resetSelectedNegotiationState();
    this.detailError.set(null);
    this.productsError.set(null);
    this.tasksError.set(null);
  }

  /**
   * Preencher o formulário de criação com dados iniciais baseados no contato.
   * @param contact O contato ativo.
   * @private
   */
  private prefillCreateForm(contact: ChatNegotiationContact): void {
    this.createForm.set({
      title: contact.name ? `Negociação - ${contact.name}` : 'Nova negociação',
      funnelId: this.createForm().funnelId,
      stepId: '',
      expectedClose: '',
      notes: '',
      companyId: String(contact.crm_company_id ?? ''),
    });
  }

  /**
   * Carregar a lista de negociações do contato via API.
   * @param focusId ID de uma negociação opcional para focar/selecionar após carregar.
   * @private
   */
  private loadNegotiations(focusId?: string | number | null): void {
    const contact = this.contactSignal();
    if (!contact) return;
    const requestToken = this.bumpListRequestToken();
    const contactId = String(contact.id);
    this.isLoadingList.set(true);
    this.listError.set(null);
    this.negotiationService
      .list({ contact_id: contact.id, per_page: 50 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (!this.isCurrentListRequest(requestToken, contactId)) {
            return;
          }

          const items = response.data ?? [];
          this.negotiations.set(items);
          this.isLoadingList.set(false);
          const targetId = focusId ?? items[0]?.id ?? null;
          if (targetId) {
            this.selectedNegotiationId.set(targetId);
            this.loadNegotiationDetail(targetId);
          } else {
            this.selectedNegotiationId.set(null);
            this.resetSelectedNegotiationState();
            this.detailError.set(null);
            this.productsError.set(null);
            this.tasksError.set(null);
          }
        },
        error: () => {
          if (!this.isCurrentListRequest(requestToken, contactId)) {
            return;
          }

          this.isLoadingList.set(false);
          this.listError.set('Não foi possível carregar as negociações do contato.');
        },
      });
  }

  /**
   * Carregar os detalhes completos, produtos e tarefas de uma negociação específica.
   * @param id ID da negociação.
   * @private
   */
  private loadNegotiationDetail(id: string | number): void {
    const requestToken = this.bumpDetailRequestToken();
    this.cancelChildCollectionRequests();
    this.isLoadingDetail.set(true);
    this.detailError.set(null);
    this.productsError.set(null);
    this.tasksError.set(null);
    this.resetSelectedNegotiationState();
    this.negotiationService
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (!this.isCurrentDetailRequest(requestToken, id)) {
            return;
          }

          const negotiation = response.data.negotiation;
          this.selectedNegotiation.set(negotiation);
          this.syncDetailForm(negotiation);
          this.isLoadingDetail.set(false);
          this.loadProducts(negotiation.id);
          this.loadTasks(negotiation.id);
          this.loadStepsForFunnel(String(negotiation.funnel_id));
        },
        error: () => {
          if (!this.isCurrentDetailRequest(requestToken, id)) {
            return;
          }

          this.resetSelectedNegotiationState();
          this.isLoadingDetail.set(false);
          this.detailError.set('Não foi possível abrir os detalhes da negociação selecionada.');
          toast.error('Não foi possível abrir a negociação.');
        },
      });
  }

  /**
   * Sincronizar os valores do formulário de detalhes com as propriedades da negociação carregada.
   * @param negotiation Objeto da negociação.
   * @private
   */
  private syncDetailForm(negotiation: Negotiation): void {
    this.detailForm.set({
      funnelId: String(negotiation.funnel_id ?? ''),
      stepId: String(negotiation.step_id ?? ''),
      expectedClose: this.formatDateInput(negotiation.expected_close_date),
    });
  }

  /**
   * Formatar uma data para o padrão de input HTML (YYYY-MM-DD).
   * @param value Data em formato bruto.
   * @returns {string} String formatada para input de data.
   * @private
   */
  private formatDateInput(value?: string | null): string {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';
    return date.toISOString().slice(0, 10);
  }

  /**
   * Carregar todos os funis de vendas disponíveis na conta do usuário.
   * @private
   */
  private loadFunnels(): void {
    this.funnelService
      .all()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.funnels.set(response.data.funnels ?? []);
        },
      });
  }

  /**
   * Carregar as etapas vinculadas a um determinado funil de vendas.
   * @param funnelId ID do funil.
   * @private
   */
  private loadStepsForFunnel(funnelId?: string | number | null): void {
    if (!funnelId) return;
    const key = String(funnelId);
    if (this.funnelSteps()[key]) return;
    this.funnelService
      .listSteps(funnelId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.funnelSteps.update((current) => ({
            ...current,
            [key]: response.data.steps ?? [],
          }));
        },
      });
  }

  /**
   * Buscar a lista de produtos associados a uma negociação específica.
   * @param negotiationId ID da negociação.
   * @private
   */
  private loadProducts(negotiationId: string | number): void {
    const requestToken = this.bumpProductsRequestToken();
    this.isProductsLoading.set(true);
    this.productsError.set(null);
    this.products.set([]);
    this.productsTotal.set(0);
    this.negotiationProductService
      .list(negotiationId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (!this.isCurrentProductsRequest(requestToken, negotiationId)) {
            return;
          }

          this.products.set(response.data.products ?? []);
          this.productsTotal.set(response.data.total ?? 0);
          this.isProductsLoading.set(false);
        },
        error: () => {
          if (!this.isCurrentProductsRequest(requestToken, negotiationId)) {
            return;
          }

          this.products.set([]);
          this.productsTotal.set(0);
          this.isProductsLoading.set(false);
          this.productsError.set('Não foi possível carregar os produtos da negociação.');
          toast.error('Não foi possível carregar os produtos.');
        },
      });
  }

  /**
   * Buscar a lista de tarefas agendadas para uma negociação específica.
   * @param negotiationId ID da negociação.
   * @private
   */
  private loadTasks(negotiationId: string | number): void {
    const requestToken = this.bumpTasksRequestToken();
    this.isTasksLoading.set(true);
    this.tasksError.set(null);
    this.tasks.set([]);
    this.negotiationTaskService
      .list(negotiationId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          if (!this.isCurrentTasksRequest(requestToken, negotiationId)) {
            return;
          }

          this.tasks.set(response.data.tasks ?? []);
          this.isTasksLoading.set(false);
        },
        error: () => {
          if (!this.isCurrentTasksRequest(requestToken, negotiationId)) {
            return;
          }

          this.tasks.set([]);
          this.isTasksLoading.set(false);
          this.tasksError.set('Não foi possível carregar as tarefas da negociação.');
          toast.error('Não foi possível carregar as tarefas.');
        },
      });
  }

  /**
   * Limpa os dados reativos associados à negociação selecionada.
   * @private
   */
  private resetSelectedNegotiationState(): void {
    this.selectedNegotiation.set(null);
    this.detailForm.set({
      funnelId: '',
      stepId: '',
      expectedClose: '',
    });
    this.products.set([]);
    this.productsTotal.set(0);
    this.tasks.set([]);
    this.itemPriceControlsMap.clear();
  }

  /**
   * Carregar as opções de produtos/serviços do catálogo geral do sistema.
   * @private
   */
  private loadProductCatalog(): void {
    this.isProductOptionsLoading.set(true);
    this.productCatalogService
      .list({ per_page: 50, is_active: true })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.productOptions.set(response.data ?? []);
          this.isProductOptionsLoading.set(false);
        },
        error: () => {
          this.isProductOptionsLoading.set(false);
        },
      });
  }

  /**
   * Atualizar os dados de uma negociação específica dentro da lista reativa (para refletir mudanças visuais).
   * @param negotiation Objeto da negociação atualizada.
   * @private
   */
  private updateListNegotiation(negotiation: Negotiation): void {
    this.negotiations.update((items) =>
      items.map((item) =>
        String(item.id) === String(negotiation.id) ? { ...item, ...negotiation } : item,
      ),
    );
  }

  private normalizeNotifyChannel(value: string): NotifyChannel {
    switch (value) {
      case 'whatsapp':
      case 'email':
      case 'push':
      case 'none':
        return value;
      default:
        return 'none';
    }
  }

  private bumpListRequestToken(): number {
    this.listRequestToken += 1;
    return this.listRequestToken;
  }

  private bumpDetailRequestToken(): number {
    this.detailRequestToken += 1;
    return this.detailRequestToken;
  }

  private bumpProductsRequestToken(): number {
    this.productsRequestToken += 1;
    return this.productsRequestToken;
  }

  private bumpTasksRequestToken(): number {
    this.tasksRequestToken += 1;
    return this.tasksRequestToken;
  }

  private cancelSelectionRequests(): void {
    this.bumpDetailRequestToken();
    this.cancelChildCollectionRequests();
    this.isLoadingDetail.set(false);
  }

  private cancelChildCollectionRequests(): void {
    this.bumpProductsRequestToken();
    this.bumpTasksRequestToken();
    this.isProductsLoading.set(false);
    this.isTasksLoading.set(false);
  }

  private isCurrentListRequest(requestToken: number, contactId: string): boolean {
    return (
      requestToken === this.listRequestToken &&
      String(this.contactSignal()?.id ?? '') === String(contactId)
    );
  }

  private isCurrentDetailRequest(requestToken: number, negotiationId: string | number): boolean {
    return (
      requestToken === this.detailRequestToken &&
      String(this.selectedNegotiationId()) === String(negotiationId)
    );
  }

  private isCurrentProductsRequest(requestToken: number, negotiationId: string | number): boolean {
    return (
      requestToken === this.productsRequestToken &&
      String(this.selectedNegotiationId()) === String(negotiationId)
    );
  }

  private isCurrentTasksRequest(requestToken: number, negotiationId: string | number): boolean {
    return (
      requestToken === this.tasksRequestToken &&
      String(this.selectedNegotiationId()) === String(negotiationId)
    );
  }
}
