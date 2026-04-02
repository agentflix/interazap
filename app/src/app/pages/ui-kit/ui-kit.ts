import { Component, ChangeDetectionStrategy, signal, inject } from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { ThemeService } from '@core/services/theme.service';
import {
  AfButtonComponent,
  AfSpinnerComponent,
  AfLoadingButtonComponent,
  AfIconButtonComponent,
  AfBadgeComponent,
  AfAvatarComponent,
  AfPageTitleComponent,
  AfEmptyStateComponent,
  AfStatusBadgeComponent,
  AfSkeletonComponent,
  AfTooltipComponent,
  AfDividerComponent,
  AfTextInputComponent,
  AfCheckboxInputComponent,
  AfSwitchInputComponent,
  AfModalComponent,
  AfConfirmModalComponent,
  AfSearchInputComponent,
  AfAddonInputComponent,
  AfPhoneInputComponent,
  AfMaskedInputComponent,
  AfPasswordInputComponent,
  AfPasswordStrengthComponent,
  AfCopyInputComponent,
  AfColorInputComponent,
  AfFileUploadComponent,
  AfSelectInputComponent,
  AfTagInputComponent,
  AfPaginationComponent,
  AfSkeletonTableRowComponent,
  AfCrudPageComponent,
  AfRadioInputComponent,
  AfTextareaInputComponent,
  AfKpiCardComponent,
  AfReportFiltersComponent,
  AfReportExportComponent,
  AfChatBubbleComponent,
  AfChatListItemComponent,
  AfChatComposerComponent,
  AfButtonGroupComponent,
  AfFabComponent,
  AfDropdownButtonComponent,
  AfTableActionsComponent,
  AfBaseListComponent,
  AfUnifiedListComponent,
  AfBreadcrumbComponent,
  AfTabsComponent,
  AfStepperComponent,
  AfAccordionItemComponent,
  AfDrawerComponent,
  AfCollapsibleComponent,
  AfNavbarComponent,
  AfSidebarItemComponent,
  AfAlertComponent,
  AfProgressBarComponent,
  AfProgressCircleComponent,
  AfTimelineComponent,
  AfDescriptionListComponent,
  AfCardComponent,
  AfDataTableComponent,
  AfCodeBlockComponent,
  AfMeterComponent,
  AfStatCardComponent,
  AfDatePickerComponent,
  AfDateRangePickerComponent,
  AfTimePickerComponent,
  AfAutocompleteComponent,
  AfNumberInputComponent,
  AfSliderComponent,
  AfRatingComponent,
  AfOtpInputComponent,
  AfRangeSliderComponent,
  AfBannerComponent,
  AfCalloutComponent,
  AfPopoverComponent,
  AfSheetComponent,
  AfClipboardComponent,
  AfThemeSwitchComponent,
  AfKbdComponent,
  AfContainerComponent,
  AfGridComponent,
  AfStackComponent,
  AfAspectRatioComponent,
  AfScrollAreaComponent,
  AfResizablePanelComponent,
  AfSplitPaneComponent,
  AfStickyHeaderComponent,
  AfAvatarGroupComponent,
  AfMentionInputComponent,
  AfKanbanColumnComponent,
  AfSortableListComponent,
  AfTreeViewComponent,
  AfCalendarComponent,
  AfColorPaletteComponent,
  AfImageCropperComponent,
  AfDropdownMenuComponent,
  AfCommandPaletteComponent,
  AfCountdownComponent,
  AfMarqueeComponent,
  AfTypewriterComponent,
  AfShortcutKeyComponent,
  AfLocaleSwitchComponent,
  AfInfiniteScrollComponent,
  AfVirtualScrollComponent,
  AfErrorBoundaryComponent,
  type AfSelectOption,
  type AfRadioOption,
  type AfAvatarGroupItem,
  type AfColorOption,
  type AfDropdownMenuItem,
  type AfCommandItem,
  type AfDropdownOption,
  type AfBreadcrumbItem,
  type AfTabItem,
  type AfStepItem,
  type AfTimelineEntry,
  type AfDescriptionItem,
  type AfAutocompleteOption,
  type AfLocaleOption,
} from '@shared/components';
import { LucideAngularModule } from 'lucide-angular';

/**
 * UI Kit Showcase page — renders every UI Kit primitive with all variants.
 * Accessible at /ui-kit. Does not depend on any API.
 * Organized by themed sections for easy browsing.
 */
@Component({
  selector: 'app-ui-kit',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfButtonComponent,
    AfSpinnerComponent,
    AfLoadingButtonComponent,
    AfIconButtonComponent,
    AfBadgeComponent,
    AfAvatarComponent,
    AfPageTitleComponent,
    AfEmptyStateComponent,
    AfStatusBadgeComponent,
    AfSkeletonComponent,
    AfTooltipComponent,
    AfDividerComponent,
    AfTextInputComponent,
    AfCheckboxInputComponent,
    AfSwitchInputComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfSearchInputComponent,
    AfAddonInputComponent,
    AfPhoneInputComponent,
    AfMaskedInputComponent,
    AfPasswordInputComponent,
    AfPasswordStrengthComponent,
    AfCopyInputComponent,
    AfColorInputComponent,
    AfFileUploadComponent,
    AfSelectInputComponent,
    AfTagInputComponent,
    AfPaginationComponent,
    AfSkeletonTableRowComponent,
    AfCrudPageComponent,
    AfRadioInputComponent,
    AfTextareaInputComponent,
    AfKpiCardComponent,
    AfReportFiltersComponent,
    AfReportExportComponent,
    AfChatBubbleComponent,
    AfChatListItemComponent,
    AfChatComposerComponent,
    AfButtonGroupComponent,
    AfFabComponent,
    AfDropdownButtonComponent,
    AfTableActionsComponent,
    AfBaseListComponent,
    AfUnifiedListComponent,
    AfBreadcrumbComponent,
    AfTabsComponent,
    AfStepperComponent,
    AfAccordionItemComponent,
    AfDrawerComponent,
    AfCollapsibleComponent,
    AfNavbarComponent,
    AfSidebarItemComponent,
    AfAlertComponent,
    AfProgressBarComponent,
    AfProgressCircleComponent,
    AfTimelineComponent,
    AfDescriptionListComponent,
    AfCardComponent,
    AfDataTableComponent,
    AfCodeBlockComponent,
    AfMeterComponent,
    AfStatCardComponent,
    AfDatePickerComponent,
    AfDateRangePickerComponent,
    AfTimePickerComponent,
    AfAutocompleteComponent,
    AfNumberInputComponent,
    AfSliderComponent,
    AfRatingComponent,
    AfOtpInputComponent,
    AfRangeSliderComponent,
    AfBannerComponent,
    AfCalloutComponent,
    AfPopoverComponent,
    AfSheetComponent,
    AfClipboardComponent,
    AfThemeSwitchComponent,
    AfKbdComponent,
    AfContainerComponent,
    AfGridComponent,
    AfStackComponent,
    AfAspectRatioComponent,
    AfScrollAreaComponent,
    AfResizablePanelComponent,
    AfSplitPaneComponent,
    AfStickyHeaderComponent,
    AfAvatarGroupComponent,
    AfMentionInputComponent,
    AfKanbanColumnComponent,
    AfSortableListComponent,
    AfTreeViewComponent,
    AfCalendarComponent,
    AfColorPaletteComponent,
    AfImageCropperComponent,
    AfDropdownMenuComponent,
    AfCommandPaletteComponent,
    AfCountdownComponent,
    AfMarqueeComponent,
    AfTypewriterComponent,
    AfShortcutKeyComponent,
    AfLocaleSwitchComponent,
    AfInfiniteScrollComponent,
    AfVirtualScrollComponent,
    AfErrorBoundaryComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './ui-kit.html',
})
export default class UiKitComponent {
  protected readonly theme = inject(ThemeService);
  protected readonly componentCount = 112;
  protected readonly isLoading = signal(true);
  protected readonly previewPaletteOpen = signal(false);
  protected readonly previewThemeMode = signal<'light' | 'dark' | 'system'>('system');
  protected readonly selectedPreviewColor = signal('#6366f1');
  protected readonly lastPreviewAction = signal('');
  protected readonly lastPreviewCommand = signal('');
  protected readonly previewDrawerOpen = signal(false);
  protected readonly previewSheetOpen = signal(false);
  protected readonly previewActiveTab = signal('overview');
  protected readonly previewCountdownTarget = new Date(
    Date.now() + 1000 * 60 * 60 * 24,
  ).toISOString();

  protected readonly previewAvatars: AfAvatarGroupItem[] = [
    { name: 'Rafael Silva' },
    { name: 'Ana Costa' },
    { name: 'Lucas Lima' },
    { name: 'Maya Santos' },
    { name: 'Pedro Rocha' },
  ];

  protected readonly previewColors: AfColorOption[] = [
    { label: 'Indigo', value: '#6366f1' },
    { label: 'Emerald', value: '#10b981' },
    { label: 'Amber', value: '#f59e0b' },
    { label: 'Rose', value: '#f43f5e' },
  ];

  protected readonly previewMenuItems: AfDropdownMenuItem[] = [
    { label: 'Editar', value: 'edit', icon: 'pencil' },
    { label: 'Duplicar', value: 'duplicate', icon: 'copy' },
    { divider: true, label: 'divider', value: 'divider' },
    { label: 'Excluir', value: 'delete', icon: 'trash-2', destructive: true },
  ];

  protected readonly previewCommandItems: AfCommandItem[] = [
    {
      id: 'new-contact',
      label: 'Novo contato',
      description: 'Criar contato no CRM',
      icon: 'user-plus',
    },
    {
      id: 'open-settings',
      label: 'Abrir configurações',
      description: 'Ir para configurações',
      icon: 'settings',
    },
    {
      id: 'start-chat',
      label: 'Iniciar chat',
      description: 'Abrir conversa',
      icon: 'message-circle',
    },
  ];

  // Text inputs
  protected readonly nameControl = new FormControl('', { nonNullable: true });
  protected readonly emailControl = new FormControl('', { nonNullable: true });
  protected readonly readOnlyControl = new FormControl('Valor fixo', { nonNullable: true });
  protected readonly passwordControl = new FormControl('', { nonNullable: true });

  // Search & Addon
  protected readonly searchControl = new FormControl('', { nonNullable: true });
  protected readonly searchControl2 = new FormControl('', { nonNullable: true });
  protected readonly tagControl = new FormControl('', { nonNullable: true });
  protected readonly urlControl = new FormControl('', { nonNullable: true });

  // Copy Input
  protected readonly apiKeyControl = new FormControl('sk-proj-abc123def456ghi789', {
    nonNullable: true,
  });
  protected readonly webhookUrlControl = new FormControl('https://api.agentflix.com/webhooks/v1', {
    nonNullable: true,
  });

  // Color Input
  protected readonly colorControl = new FormControl('#6366f1', { nonNullable: true });
  protected readonly colorControl2 = new FormControl('#f59e0b', { nonNullable: true });

  // Phone & Masked
  protected readonly phoneControl = new FormControl('', { nonNullable: true });
  protected readonly cpfControl = new FormControl('', { nonNullable: true });
  protected readonly cnpjControl = new FormControl('', { nonNullable: true });
  protected readonly docControl = new FormControl('', { nonNullable: true });
  protected readonly cepControl = new FormControl('', { nonNullable: true });
  protected readonly currencyControl = new FormControl('', { nonNullable: true });

  protected readonly demoDateControl = new FormControl('', { nonNullable: true });
  protected readonly demoStartDateControl = new FormControl('', { nonNullable: true });
  protected readonly demoEndDateControl = new FormControl('', { nonNullable: true });
  protected readonly demoTimeControl = new FormControl('', { nonNullable: true });
  protected readonly demoAutocompleteControl = new FormControl('', { nonNullable: true });
  protected readonly demoNumberControl = new FormControl(3, { nonNullable: true });
  protected readonly demoRangeMinControl = new FormControl(20, { nonNullable: true });
  protected readonly demoRangeMaxControl = new FormControl(80, { nonNullable: true });

  protected readonly previewDropdownButtonOptions: AfDropdownOption[] = [
    { label: 'Editar', value: 'edit', icon: 'pencil' },
    { label: 'Duplicar', value: 'duplicate', icon: 'copy' },
    { label: 'Excluir', value: 'delete', icon: 'trash-2' },
  ];

  protected readonly previewBreadcrumbItems: AfBreadcrumbItem[] = [
    { label: 'Home', href: '/' },
    { label: 'CRM', href: '/crm' },
    { label: 'Contatos' },
  ];

  protected readonly previewTabs: AfTabItem[] = [
    { id: 'overview', label: 'Visão geral' },
    { id: 'activity', label: 'Atividades' },
    { id: 'settings', label: 'Configurações' },
  ];

  protected readonly previewSteps: AfStepItem[] = [
    { label: 'Dados' },
    { label: 'Validação' },
    { label: 'Confirmação' },
  ];

  protected readonly previewTimelineEntries: AfTimelineEntry[] = [
    { title: 'Contato criado', description: 'Cadastro inicial', timestamp: '09:41' },
    { title: 'Pipeline atualizado', description: 'Etapa: negociação', timestamp: '11:20' },
  ];

  protected readonly previewDescriptionItems: AfDescriptionItem[] = [
    { term: 'Cliente', detail: 'Acme Corp' },
    { term: 'Plano', detail: 'Enterprise' },
    { term: 'Renovação', detail: '2026-10-31' },
  ];

  protected readonly previewAutocompleteOptions: AfAutocompleteOption[] = [
    { value: 'sp', label: 'São Paulo' },
    { value: 'rj', label: 'Rio de Janeiro' },
    { value: 'bh', label: 'Belo Horizonte' },
  ];

  protected readonly previewMentionUsers = [
    { id: '1', name: 'Rafael Silva' },
    { id: '2', name: 'Camila Rocha' },
    { id: '3', name: 'Diego Lima' },
  ];

  protected readonly previewKanbanCards = [
    { id: 'c1', title: 'Subir feature', description: 'UI Kit showcase' },
    { id: 'c2', title: 'Validar QA', description: 'Revisar states' },
  ];

  protected readonly previewSortableItems = [
    { id: '1', label: 'Primeiro item' },
    { id: '2', label: 'Segundo item' },
    { id: '3', label: 'Terceiro item' },
  ];

  protected readonly previewTreeNodes = [
    {
      id: 'root',
      label: 'CRM',
      children: [
        { id: 'n1', label: 'Contatos' },
        { id: 'n2', label: 'Empresas' },
      ],
    },
  ];

  protected readonly previewCalendarEvents = [
    { id: 'e1', title: 'Reunião', date: '2026-02-24', color: '#6366f1' },
    { id: 'e2', title: 'Follow-up', date: '2026-02-26', color: '#10b981' },
  ];

  protected readonly previewLocales: AfLocaleOption[] = [
    { code: 'pt-BR', label: 'Português', flag: '🇧🇷' },
    { code: 'en-US', label: 'English', flag: '🇺🇸' },
    { code: 'es-ES', label: 'Español', flag: '🇪🇸' },
  ];

  // Password
  protected readonly pwLoginControl = new FormControl('', { nonNullable: true });
  protected readonly pwNewControl = new FormControl('', { nonNullable: true });

  // Select
  protected readonly selectControl = new FormControl('', { nonNullable: true });
  protected readonly selectControl2 = new FormControl('', { nonNullable: true });

  protected readonly statusOptions: AfSelectOption[] = [
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
    { value: 'pending', label: 'Pendente' },
    { value: 'suspended', label: 'Suspenso' },
    { value: 'archived', label: 'Arquivado' },
  ];

  protected readonly countryOptions: AfSelectOption[] = [
    { value: 'br', label: '🇧🇷 Brasil' },
    { value: 'us', label: '🇺🇸 Estados Unidos' },
    { value: 'pt', label: '🇵🇹 Portugal' },
    { value: 'ar', label: '🇦🇷 Argentina' },
    { value: 'de', label: '🇩🇪 Alemanha' },
    { value: 'es', label: '🇪🇸 Espanha' },
    { value: 'gb', label: '🇬🇧 Reino Unido' },
    { value: 'jp', label: '🇯🇵 Japão' },
    { value: 'fr', label: '🇫🇷 França' },
    { value: 'it', label: '🇮🇹 Itália' },
  ];

  // Tags
  protected readonly tagsControl = new FormControl('', { nonNullable: true });
  protected readonly tagsControl2 = new FormControl('Angular,TypeScript', { nonNullable: true });

  // Checkbox & Switch
  protected readonly checkboxControl = new FormControl(false, { nonNullable: true });
  protected readonly checkboxControl2 = new FormControl(true, { nonNullable: true });
  protected readonly checkboxDisabled = new FormControl(
    { value: false, disabled: true },
    { nonNullable: true },
  );
  protected readonly switchControl = new FormControl(true, { nonNullable: true });
  protected readonly switchDisabledControl = new FormControl(
    { value: true, disabled: true },
    { nonNullable: true },
  );
  protected readonly switchStandalone = signal(false);

  // Modal state
  protected readonly showModal = signal(false);
  protected readonly showLargeModal = signal(false);
  protected readonly showConfirmDanger = signal(false);
  protected readonly showConfirmWarning = signal(false);

  // Pagination
  protected readonly paginationPage = signal(3);

  // Radio
  protected readonly radioControl = new FormControl('active', { nonNullable: true });
  protected readonly radioControl2 = new FormControl('medium', { nonNullable: true });

  protected readonly radioOptions: AfRadioOption[] = [
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
    { value: 'pending', label: 'Pendente' },
    { value: 'blocked', label: 'Bloqueado', disabled: true },
  ];

  protected readonly priorityOptions: AfRadioOption[] = [
    { value: 'low', label: 'Baixa' },
    { value: 'medium', label: 'Média' },
    { value: 'high', label: 'Alta' },
  ];

  // Textarea
  protected readonly textareaControl = new FormControl('', { nonNullable: true });
  protected readonly notesControl = new FormControl('', { nonNullable: true });

  // Report filters
  protected readonly reportStatusOptions: AfSelectOption[] = [
    { value: 'active', label: 'Ativo' },
    { value: 'inactive', label: 'Inativo' },
    { value: 'completed', label: 'Concluído' },
  ];

  protected toggleTheme(): void {
    this.theme.toggleTheme();
  }

  protected onSwitchToggle(value: boolean): void {
    this.switchStandalone.set(value);
  }

  protected onPreviewCommand(commandId: string): void {
    this.lastPreviewCommand.set(commandId);
    this.previewPaletteOpen.set(false);
  }
}
