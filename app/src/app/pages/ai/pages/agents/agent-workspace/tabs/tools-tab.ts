import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  inject,
  input,
  signal,
  computed,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { AfSelectInputComponent, AfButtonComponent, type AfSelectOption } from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type AiAgentToolLink, type AiToolCatalogItem, type AiAgentPreset } from '@ai/models/ai.model';
import { AiAgentService } from '@ai/services/ai-agent.service';

/** PT-BR translations for tool descriptions */
const TOOL_DESCRIPTIONS_PT: Record<string, string> = {
  update_lead_score: 'Atualizar pontuação do lead',
  create_note: 'Criar nota no contato',
  create_task: 'Criar tarefa',
  notify_seller: 'Notificar vendedor',
  transfer_to_human: 'Transferir para atendente humano',
  close_ticket: 'Encerrar ticket',
  search_knowledge: 'Pesquisar na base de conhecimento',
  get_contact_info: 'Obter informações do contato',
  update_contact_tags: 'Atualizar tags do contato',
  read_ticket: 'Ler dados do ticket',
  send_message: 'Enviar mensagem',
  move_pipeline: 'Mover no pipeline',
  search_contacts: 'Pesquisar contatos',
  schedule_followup: 'Agendar follow-up',
  get_product_info: 'Obter informações do produto',
  create_deal: 'Criar negócio',
  update_deal: 'Atualizar negócio',
  get_deal_info: 'Obter informações do negócio',
  schedule_meeting: 'Agendar reunião',
  send_email: 'Enviar e-mail',
  create_contact: 'Criar contato',
  update_contact: 'Atualizar contato',
  get_order_status: 'Consultar status do pedido',
  create_invoice: 'Criar fatura',
  search_products: 'Pesquisar produtos',
  get_billing_info: 'Obter informações de cobrança',
  cancel_subscription: 'Cancelar assinatura',
  create_refund: 'Criar reembolso',
  escalate_ticket: 'Escalar ticket',
};

/**
 * Aba de Ferramentas — seleção interativa de tools com toggle e salvamento global.
 *
 * Contexto: exibe catálogo de tools agrupado por categoria. Permite vincular/desvincular tools
 * ao agente. Suporta aplicação de presets (conjuntos predefinidos de tools por papel).
 * O salvamento é delegado ao workspace global.
 */
@Component({
  selector: 'app-agent-tools-tab',
  standalone: true,
  imports: [LucideAngularModule, AfButtonComponent, AfSelectInputComponent, ReactiveFormsModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tools-tab.html',
})
export class AgentToolsTabComponent {
  private readonly agentService = inject(AiAgentService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly agentId = input.required<string>();
  readonly catalog = signal<AiToolCatalogItem[]>([]);
  readonly linkedToolNames = signal<string[]>([]);
  readonly initialToolNames = signal<string[]>([]);
  readonly loading = signal(false);
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly loadingPreset = signal(false);

  readonly presetControl = new FormControl<AiAgentPreset>('general');

  readonly presetOptions: AfSelectOption[] = [
    { value: 'general', label: 'Geral' },
    { value: 'sales_qualifier', label: 'Vendas / Qualificador' },
    { value: 'support_l1', label: 'Suporte L1' },
    { value: 'post_sales', label: 'Pós-venda' },
    { value: 'appointment', label: 'Agendamento' },
    { value: 'cs_retention', label: 'Retenção CS' },
  ];

  /** @deprecated Use presetOptions instead — kept for template backward compatibility */
  readonly roleOptions: AfSelectOption[] = this.presetOptions;

  readonly catalogToolNameById = computed(() => {
    const map = new Map<string, string>();
    for (const tool of this.catalog()) {
      const normalizedName = tool.name.trim();
      if (normalizedName.length > 0) {
        map.set(tool.id, normalizedName);
      }
    }
    return map;
  });

  readonly categories = computed(() => {
    const cats = new Set(this.catalog().map((t) => t.category));
    return Array.from(cats).sort();
  });

  readonly hasChanges = computed(() => {
    const current = [...this.linkedToolNames()].sort();
    const initial = [...this.initialToolNames()].sort();
    if (current.length !== initial.length) return true;
    return current.some((v, i) => v !== initial[i]);
  });

  constructor() {
    setTimeout(() => this.loadData(), 0);
  }

  /**
   * Filtra tools por categoria.
   * @param category Nome da categoria
   * @returns Lista de tools da categoria
   */
  toolsByCategory(category: string): AiToolCatalogItem[] {
    return this.catalog().filter((t) => t.category === category);
  }

  /**
   * Verifica se uma tool está vinculada ao agente.
   * @param toolName Nome da tool
   * @returns true se vinculada
   */
  isLinked(toolName: string): boolean {
    return this.linkedToolNames().includes(toolName);
  }

  /**
   * Vincula ou desvincula uma tool.
   * @param toolName Nome da tool a alternar
   */
  toggleTool(toolName: string): void {
    const current = this.linkedToolNames();
    if (current.includes(toolName)) {
      this.linkedToolNames.set(current.filter((n) => n !== toolName));
    } else {
      this.linkedToolNames.set([...current, toolName]);
    }
  }

  /**
   * Aplica um preset de tools, carregando as tools padrão do preset selecionado.
   * Mescla com as tools já vinculadas para não remover configurações manuais.
   * O preset é apenas um atalho de configuração — a permissão efetiva vem das tools
   * vinculadas validadas pelo backend.
   * @param forceRole Papel forçado (ignora o controle de seleção se fornecido)
   */
  applyPreset(forceRole?: string): void {
    const roleToLoad = forceRole || this.presetControl.value;
    if (!roleToLoad) return;

    this.loadingPreset.set(true);
    this.agentService.getToolsPreset(roleToLoad).subscribe({
      next: (presetTools) => {
        // Merge with currently linked tools to avoid dropping what user manually checked
        const current = new Set(this.linkedToolNames());
        for (const pt of presetTools) {
          current.add(pt);
        }
        this.linkedToolNames.set(Array.from(current));
        this.loadingPreset.set(false);
        this.toast.success('Preset aplicado com sucesso.');
      },
      error: () => {
        this.loadingPreset.set(false);
        this.toast.error('Erro ao carregar o preset.');
      },
    });
  }

  // saveTools() removed — tool saving is now handled by the global workspace save

  /**
   * Retorna a descrição da tool traduzida para PT-BR.
   * @param tool Item do catálogo de tools
   * @returns Descrição em português ou descrição original
   */
  getToolDescription(tool: AiToolCatalogItem): string {
    return TOOL_DESCRIPTIONS_PT[tool.name] ?? tool.description;
  }

  /**
   * Retorna o rótulo legível para a categoria da tool.
   * @param category Identificador da categoria
   * @returns Nome da categoria em português
   */
  formatCategory(category: string): string {
    const labels: Record<string, string> = {
      crm: 'CRM',
      sales: 'Vendas',
      chat: 'Chat',
      knowledge: 'Conhecimento',
      productivity: 'Produtividade',
      notification: 'Notificação',
      meta: 'Meta / Sistema',
    };
    return labels[category] ?? category;
  }

  /**
   * Retorna o nome do ícone Lucide para a categoria da tool.
   * @param category Identificador da categoria
   * @returns Nome do ícone
   */
  getCategoryIcon(category: string): string {
    const icons: Record<string, string> = {
      crm: 'users',
      sales: 'trending-up',
      chat: 'message-circle',
      knowledge: 'book-open',
      productivity: 'clock',
      notification: 'bell',
      meta: 'settings',
    };
    return icons[category] ?? 'folder';
  }

  /**
   * Carrega o catálogo de tools e as tools vinculadas ao agente da API.
   */
  loadData(): void {
    const id = this.agentId();
    if (!id) return;

    this.loading.set(true);
    this.error.set(null);

    this.agentService
      .getToolsCatalog()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (catalog) => {
          this.catalog.set(catalog);
          this.loadLinkedTools(id);
        },
        error: () => {
          this.error.set('Erro ao carregar catálogo de tools.');
          this.loading.set(false);
        },
      });
  }

  private loadLinkedTools(agentId: string): void {
    this.agentService
      .getAgentTools(agentId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (tools) => {
          const names = this.extractLinkedToolNames(tools);
          this.linkedToolNames.set(names);
          this.initialToolNames.set(names);
          this.loading.set(false);
        },
        error: () => {
          this.error.set('Erro ao carregar tools vinculadas.');
          this.loading.set(false);
        },
      });
  }

  private extractLinkedToolNames(tools: AiAgentToolLink[]): string[] {
    const names = tools
      .map((tool) => this.resolveLinkedToolName(tool))
      .filter((name) => name.length > 0);

    return Array.from(new Set(names));
  }

  private resolveLinkedToolName(tool: AiAgentToolLink): string {
    const directName = tool.tool_name?.trim() ?? '';
    if (directName.length > 0) {
      return directName;
    }

    const legacyName = (tool as AiAgentToolLink & { name?: string }).name?.trim() ?? '';
    if (legacyName.length > 0) {
      return legacyName;
    }

    return this.catalogToolNameById().get(tool.tool_id)?.trim() ?? '';
  }
}
