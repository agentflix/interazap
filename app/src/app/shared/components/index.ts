/**
 * InteraZap UI Kit — Barrel exports for all 112 shared components.
 *
 * Import from this file:
 * ```ts
 * import { AfButtonComponent, AfBadgeComponent } from '@shared/components';
 * ```
 */

// ─── Atoms / Primitives ──────────────────────────────────────────────
export { AfButtonComponent } from './button/button';
export { AfSpinnerComponent } from './spinner/spinner';
export { AfLoadingButtonComponent } from './loading-button/loading-button';
export { AfIconButtonComponent } from './icon-button/icon-button';
export { AfBadgeComponent } from './badge/badge';
export { AfAvatarComponent } from './avatar/avatar';
export { AfPageTitleComponent } from './page-title/page-title';
export { AfEmptyStateComponent } from './empty-state/empty-state';
export { AfStatusBadgeComponent } from './status-badge/status-badge';
export { AfSkeletonComponent } from './skeleton/skeleton';
export { AfTooltipComponent } from './tooltip/tooltip';
export { AfDividerComponent } from './divider/divider';

// ─── Templates / WhatsApp ────────────────────────────────────────────
export {
  TemplateSelectorComponent,
  type MetaTemplate,
  type MetaTemplateComponent,
  type MetaTemplateParameter,
  type TemplateSelectedEvent,
} from '../../pages/chat/components/new-conversation-modal/components/template-selector/template-selector';

// ─── Form Primitives & Modals ────────────────────────────────────────
export { AfFormLabelComponent } from './form-label/form-label';
export { AfFormErrorComponent } from './form-error/form-error';
export {
  AfTextInputComponent,
  type AfTextInputType,
  type AfInputSize,
} from './text-input/text-input';
export { AfCheckboxInputComponent } from './checkbox-input/checkbox-input';
export { AfSwitchInputComponent } from './switch-input/switch-input';
export { AfModalComponent } from './modal/modal';
export { AfConfirmModalComponent } from './confirm-modal/confirm-modal';

// ─── Advanced Inputs ─────────────────────────────────────────────────
export { AfSearchInputComponent } from './search-input/search-input';
export { SearchSpotlightComponent } from './search-spotlight/search-spotlight';
export { AfAddonInputComponent } from './addon-input/addon-input';
export { AfPhoneInputComponent, type AfCountryCode } from './phone-input/phone-input';
export { AfMaskedInputComponent } from './masked-input/masked-input';
export { AfPasswordInputComponent } from './password-input/password-input';
export {
  AfPasswordStrengthComponent,
  type AfPasswordStrength,
} from './password-strength/password-strength';
export { AfCopyInputComponent } from './copy-input/copy-input';
export { AfColorInputComponent } from './color-input/color-input';
export { AfFileUploadComponent, type AfUploadFile } from './file-upload/file-upload';
export { AfSelectInputComponent, type AfSelectOption } from './select-input/select-input';
export { AfTagInputComponent } from './tag-input/tag-input';
export { AfCheckboxGroupComponent, type AfCheckboxOption } from './checkbox-group/checkbox-group';
export { AfRadioInputComponent, type AfRadioOption } from './radio-input/radio-input';
export { AfTextareaInputComponent } from './textarea-input/textarea-input';

// ─── Directives ──────────────────────────────────────────────────────
export { AfMaskDirective, type AfMaskPreset } from '../directives/mask.directive';

// ─── CRUD Core ───────────────────────────────────────────────────────
export { AfPaginationComponent } from './pagination/pagination';
export { AfSkeletonTableRowComponent } from './skeleton-table-row/skeleton-table-row';
export { AfCrudPageComponent } from './crud-page/crud-page';
export { AfSortableHeaderComponent } from './sortable-header/sortable-header';
export type { SortDirection, SortState } from './crud-page/models/index';

// ─── Dashboard & Reports ─────────────────────────────────────────────
export { AfKpiCardComponent } from './kpi-card/kpi-card';
export { AfApexchartComponent } from './apexchart/apexchart';
export {
  AfReportFiltersComponent,
  type AfReportFilterPayload,
} from './report-filters/report-filters';
export { AfReportExportComponent, type AfReportExportPayload } from './report-export/report-export';

// ─── Chat Primitives ─────────────────────────────────────────────────
export { AfChatBubbleComponent } from './chat-bubble/chat-bubble';
export { AfChatListItemComponent } from './chat-list-item/chat-list-item';
export { AfChatComposerComponent } from './chat-composer/chat-composer';

// ─── Legacy Parity (9) ──────────────────────────────────────────────
export { AfButtonGroupComponent } from './button-group/button-group';
export { AfFabComponent } from './fab/fab';
export {
  AfDropdownButtonComponent,
  type AfDropdownOption,
} from './dropdown-button/dropdown-button';
export { AfTableActionsComponent } from './table-actions/table-actions';
export { AfCurrencyInputComponent } from './currency-input/currency-input';
export { AfDocumentInputComponent } from './document-input/document-input';
export { AfFileInputComponent } from './file-input/file-input';
export { AfBaseListComponent } from './base-list/base-list';
export { AfUnifiedListComponent } from './unified-list/unified-list';

// ─── Navigation (8) ─────────────────────────────────────────────────
export { AfBreadcrumbComponent, type AfBreadcrumbItem } from './breadcrumb/breadcrumb';
export { AfTabsComponent, type AfTabItem } from './tabs/tabs';
export { AfStepperComponent, type AfStepItem } from './stepper/stepper';
export { AfAccordionItemComponent } from './accordion/accordion';
export { AfDrawerComponent } from './drawer/drawer';
export { AfCollapsibleComponent } from './collapsible/collapsible';
export { AfNavbarComponent } from './navbar/navbar';
export { AfSidebarItemComponent } from './sidebar-item/sidebar-item';

// ─── Data Display (10) ──────────────────────────────────────────────
export { AfProgressBarComponent } from './progress-bar/progress-bar';
export { AfProgressCircleComponent } from './progress-circle/progress-circle';
export { AfTimelineComponent, type AfTimelineEntry } from './timeline/timeline';
export {
  AfDescriptionListComponent,
  type AfDescriptionItem,
} from './description-list/description-list';
export { AfCardComponent } from './card/card';
export { AfDataTableComponent } from './data-table/data-table';
export { AfCodeBlockComponent } from './code-block/code-block';
export { AfMeterComponent } from './meter/meter';
export { AfStatCardComponent } from './stat-card/stat-card';
export { AfPdfPreviewComponent } from './pdf-preview/pdf-preview';

// ─── Form Inputs (9) ────────────────────────────────────────────────
export { AfDatePickerComponent } from './date-picker/date-picker';
export { AfDateRangePickerComponent } from './date-range-picker/date-range-picker';
export { AfTimePickerComponent } from './time-picker/time-picker';
export { AfAutocompleteComponent, type AfAutocompleteOption } from './autocomplete/autocomplete';
export { AfNumberInputComponent } from './number-input/number-input';
export { AfSliderComponent } from './slider/slider';
export { AfRatingComponent } from './rating/rating';
export { AfOtpInputComponent } from './otp-input/otp-input';
export { AfRangeSliderComponent } from './range-slider/range-slider';

// ─── Feedback & Overlay (8) ─────────────────────────────────────────
export { AfAlertComponent } from './alert/alert';
export { AfBannerComponent } from './banner/banner';
export { AfCalloutComponent } from './callout/callout';
export { AfPopoverComponent } from './popover/popover';
export { AfDropdownMenuComponent, type AfDropdownMenuItem } from './dropdown-menu/dropdown-menu';
export { AfSheetComponent } from './sheet/sheet';
export { AfKbdComponent } from './kbd/kbd';
export { AfCommandPaletteComponent, type AfCommandItem } from './command-palette/command-palette';

// ─── Layout (8) ─────────────────────────────────────────────────────
export { AfContainerComponent } from './container/container';
export { AfGridComponent } from './grid/grid';
export { AfStackComponent } from './stack/stack';
export { AfAspectRatioComponent } from './aspect-ratio/aspect-ratio';
export { AfScrollAreaComponent } from './scroll-area/scroll-area';
export { AfResizablePanelComponent } from './resizable-panel/resizable-panel';
export { AfSplitPaneComponent } from './split-pane/split-pane';
export { AfStickyHeaderComponent } from './sticky-header/sticky-header';

// ─── Advanced / Composite (8) ───────────────────────────────────────
export { AfAvatarGroupComponent, type AfAvatarGroupItem } from './avatar-group/avatar-group';
export { AfMentionInputComponent, type AfMentionUser } from './mention-input/mention-input';
export { AfKanbanColumnComponent, type AfKanbanCard } from './kanban-column/kanban-column';
export { AfSortableListComponent, type AfSortableItem } from './sortable-list/sortable-list';
export { AfTreeViewComponent, type AfTreeNode } from './tree-view/tree-view';
export { AfCalendarComponent, type AfCalendarEvent } from './calendar/calendar';
export { AfColorPaletteComponent, type AfColorOption } from './color-palette/color-palette';
export { AfImageCropperComponent } from './image-cropper/image-cropper';

// ─── Utility (10) ───────────────────────────────────────────────────
export { AfClipboardComponent } from './clipboard/clipboard';
export { AfCountdownComponent } from './countdown/countdown';
export { AfMarqueeComponent } from './marquee/marquee';
export { AfTypewriterComponent } from './typewriter/typewriter';
export { AfShortcutKeyComponent } from './shortcut-key/shortcut-key';
export { AfThemeSwitchComponent } from './theme-switch/theme-switch';
export { AfLocaleSwitchComponent, type AfLocaleOption } from './locale-switch/locale-switch';
export { AfInfiniteScrollComponent } from './infinite-scroll/infinite-scroll';
export { AfVirtualScrollComponent } from './virtual-scroll/virtual-scroll';
export { AfErrorBoundaryComponent } from './error-boundary/error-boundary';
