import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { forkJoin } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import {
  type UazapiInstance,
  type UazapiInstanceStatus,
  UazapiInstancesService,
} from '@core/services/uazapi-instances.service';
import { ToastService } from '@core/services/toast.service';
import { UazapiInstanceFormComponent } from './components/uazapi-instance-form/uazapi-instance-form';

@Component({
  selector: 'app-uazapi-instances',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfCheckboxInputComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfIconButtonComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfAlertComponent,
    UazapiInstanceFormComponent,
/**
 * Uazapi instances page component for the Platform module.
 * @selector app-uazapi-instances
 */
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './uazapi-instances.html',
})
export class UazapiInstances implements OnInit {
  private readonly service = inject(UazapiInstancesService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly instanceFormRef = viewChild<UazapiInstanceFormComponent>('instanceForm');
  readonly isFormSaving = computed(() => this.instanceFormRef()?.isSaving() ?? false);

  readonly statusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Conectado', value: 'connected' },
    { label: 'Conectando', value: 'connecting' },
    { label: 'Aguardando QR', value: 'qr' },
    { label: 'Desconectado', value: 'disconnected' },
  ];

  readonly instances = signal<UazapiInstance[]>([]);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.instances().length === 0,
  );

  readonly statusFilterControl = new FormControl<string>('all', { nonNullable: true });

  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedIds = signal<string[]>([]);
  readonly selectedCount = computed(() => this.selectedIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);

  readonly showFormModal = signal(false);
  readonly selectedInstance = signal<UazapiInstance | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedInstance() ? 'Editar instância' : 'Nova instância',
  );

  readonly showDisconnectModal = signal(false);
  readonly disconnectTarget = signal<UazapiInstance | null>(null);
  readonly isDisconnecting = signal(false);
  readonly disconnectTitle = computed(() =>
    this.disconnectTarget() ? 'Desconectar instância' : 'Desconectar instâncias',
  );
  readonly disconnectConfirmLabel = computed(() =>
    this.disconnectTarget() ? 'Desconectar' : 'Desconectar selecionadas',
  );
  readonly disconnectMessage = computed(() => {
    const single = this.disconnectTarget();
    if (single) return `Deseja desconectar a instância "${this.displayName(single)}"?`;
    return `Deseja desconectar as ${this.selectedCount()} instâncias selecionadas?`;
  });

  readonly showDeleteModal = signal(false);
  readonly deleteTarget = signal<UazapiInstance | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteTitle = computed(() => 'Excluir instância');
  readonly deleteConfirmLabel = computed(() => 'Excluir');
  readonly deleteMessage = computed(() => {
    const single = this.deleteTarget();
    if (!single)
      return 'Tem certeza que deseja excluir esta instância? Esta ação não pode ser desfeita.';
    return `Tem certeza que deseja excluir a instância "${this.displayName(single)}"? Esta ação não pode ser desfeita.`;
  });

  readonly showProfileImageModal = signal(false);
  readonly isStatusModalOpen = signal(false);
  readonly statusTarget = signal<UazapiInstance | null>(null);
  readonly statusResult = signal<Record<string, unknown> | null>(null);
  readonly isStatusLoading = signal(false);
  readonly statusResultEntries = computed(() => {
    const status = this.statusResult();
    if (!status) return [] as { key: string; value: string }[];
    return Object.entries(status).map(([key, value]) => ({ key, value: this.stringify(value) }));
  });
  readonly profileImageTarget = signal<UazapiInstance | null>(null);
  readonly isUpdatingProfileImage = signal(false);
  readonly profileImageMode = signal<'url' | 'base64' | 'remove'>('url');
  readonly profileImageUrlControl = new FormControl<string>('', { nonNullable: true });
  readonly profileImageBase64Control = new FormControl<string>('', { nonNullable: true });
  readonly profileImagePreview = signal<string | null>(null);

  readonly isUpdatingPresence = signal(false);

  private currentPage = 1;
  private searchTerm = '';

  constructor() {
    this.profileImageUrlControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((url) => {
        if (this.profileImageMode() === 'url' && url.trim().length > 0) {
          this.profileImagePreview.set(url.trim());
        } else {
          this.profileImagePreview.set(null);
        }
      });

    this.statusFilterControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.currentPage = 1;
        this.loadInstances();
      });

    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const selectable = this.instances().filter((instance) => this.isSelectable(instance));
        const ids = checked ? selectable.map((instance) => instance.id) : [];
        this.selectedIds.set(ids);
        selectable.forEach((instance) =>
          this.getRowSelectionControl(instance.id).setValue(checked),
        );
      });
  }

  ngOnInit(): void {
    this.loadInstances();
  }

  openCreate(): void {
    this.selectedInstance.set(null);
    this.showFormModal.set(true);
  }

  openEdit(instance: UazapiInstance): void {
    this.selectedInstance.set(instance);
    this.showFormModal.set(true);
  }

  handleFormSaved(_instance: UazapiInstance): void {
    this.showFormModal.set(false);
    this.selectedInstance.set(null);
    this.toast.success('Instância salva com sucesso.');
    this.loadInstances();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedInstance.set(null);
  }

  openDisconnect(instance: UazapiInstance): void {
    this.disconnectTarget.set(instance);
    this.showDisconnectModal.set(true);
  }

  openDelete(instance: UazapiInstance): void {
    this.deleteTarget.set(instance);
    this.showDeleteModal.set(true);
  }

  bulkDisconnect(): void {
    if (!this.hasSelection()) return;
    this.disconnectTarget.set(null);
    this.showDisconnectModal.set(true);
  }

  confirmDisconnect(): void {
    if (this.isDisconnecting()) return;

    const single = this.disconnectTarget();
    const ids = single ? [single.id] : this.selectedIds();
    if (ids.length === 0) return;

    this.isDisconnecting.set(true);
    forkJoin(ids.map((id) => this.service.disconnect(id)))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDisconnecting.set(false);
          this.showDisconnectModal.set(false);
          this.disconnectTarget.set(null);
          this.clearSelection();
          this.toast.success(
            ids.length > 1
              ? 'Instâncias desconectadas com sucesso.'
              : 'Instância desconectada com sucesso.',
          );
          this.loadInstances();
        },
        error: () => {
          this.isDisconnecting.set(false);
          this.showDisconnectModal.set(false);
        },
      });
  }

  confirmDelete(): void {
    const target = this.deleteTarget();
    if (!target || this.isDeleting()) return;

    this.isDeleting.set(true);
    this.service
      .delete(target.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.deleteTarget.set(null);
          this.clearSelection();
          this.toast.success('Instância excluída com sucesso.');
          this.loadInstances();
        },
        error: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
        },
      });
  }

  openStatusModal(instance: UazapiInstance): void {
    this.statusTarget.set(instance);
    this.statusResult.set(null);
    this.isStatusModalOpen.set(true);
    this.fetchStatus();
  }

  closeStatusModal(): void {
    this.isStatusModalOpen.set(false);
    this.statusTarget.set(null);
    this.statusResult.set(null);
  }

  profilePicUrl(instance: UazapiInstance): string | null {
    const metadata = instance.metadata;
    if (!metadata) return null;
    const profile = metadata['profile'];
    const profilePicFromProfile =
      typeof profile === 'object' && profile !== null
        ? (profile as Record<string, unknown>)['profilePicUrl']
        : undefined;

    const picUrl =
      (metadata['profilePicUrl'] as string | undefined) ??
      (metadata['profile_pic_url'] as string | undefined) ??
      (metadata['picture'] as string | undefined) ??
      (typeof profilePicFromProfile === 'string' ? profilePicFromProfile : undefined);

    return typeof picUrl === 'string' && picUrl.trim().length > 0 ? picUrl.trim() : null;
  }

  currentPresence(instance: UazapiInstance): 'available' | 'unavailable' | null {
    const metadata = instance.metadata;
    if (!metadata) return null;
    const presence = metadata['current_presence'] as string | undefined;
    if (presence === 'available' || presence === 'unavailable') return presence;
    return null;
  }

  togglePresence(instance: UazapiInstance): void {
    if (this.isUpdatingPresence()) return;
    const current = this.currentPresence(instance) ?? 'available';
    const next: 'available' | 'unavailable' = current === 'available' ? 'unavailable' : 'available';
    this.isUpdatingPresence.set(true);
    this.service
      .updatePresence(instance.id, next)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (updated) => {
          this.isUpdatingPresence.set(false);
          this.toast.success(
            `Presença alterada para ${next === 'available' ? 'Online' : 'Offline'}`,
          );
          const idx = this.instances().findIndex((i) => i.id === instance.id);
          if (idx !== -1) {
            const updatedInstances = [...this.instances()];
            updatedInstances[idx] = { ...updatedInstances[idx], metadata: updated.metadata };
            this.instances.set(updatedInstances);
          }
        },
        error: () => {
          this.isUpdatingPresence.set(false);
        },
      });
  }

  openProfileImageModal(instance: UazapiInstance): void {
    this.profileImageTarget.set(instance);
    this.profileImageMode.set('url');
    this.profileImageUrlControl.reset({ value: '', disabled: false });
    this.profileImageBase64Control.reset({ value: '', disabled: false });
    this.profileImagePreview.set(null);
    this.showProfileImageModal.set(true);
  }

  closeProfileImageModal(): void {
    this.showProfileImageModal.set(false);
    this.profileImageTarget.set(null);
    this.profileImagePreview.set(null);
  }

  setProfileImageMode(mode: 'url' | 'base64' | 'remove'): void {
    this.profileImageMode.set(mode);
  }

  submitProfileImage(): void {
    const target = this.profileImageTarget();
    if (!target || this.isUpdatingProfileImage()) return;

    const mode = this.profileImageMode();
    let image: string;

    if (mode === 'remove') {
      image = 'remove';
    } else if (mode === 'url') {
      const url = this.profileImageUrlControl.value.trim();
      if (!url) {
        this.toast.warning('Informe a URL da imagem.');
        return;
      }
      image = url;
    } else {
      const base64 = this.profileImageBase64Control.value.trim();
      if (!base64) {
        this.toast.warning('Informe o código Base64 da imagem.');
        return;
      }
      image = base64;
    }

    this.isUpdatingProfileImage.set(true);
    this.service
      .updateProfileImage(target.id, image)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (updated) => {
          this.isUpdatingProfileImage.set(false);
          this.closeProfileImageModal();
          this.toast.success('Imagem do perfil atualizada com sucesso.');
          const idx = this.instances().findIndex((i) => i.id === target.id);
          if (idx !== -1) {
            const updatedInstances = [...this.instances()];
            updatedInstances[idx] = { ...updatedInstances[idx], metadata: updated.metadata };
            this.instances.set(updatedInstances);
          }
        },
        error: () => {
          this.isUpdatingProfileImage.set(false);
        },
      });
  }

  fetchStatus(): void {
    const target = this.statusTarget();
    if (!target) return;

    this.isStatusLoading.set(true);
    this.service
      .status(target.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isStatusLoading.set(false);
          this.statusResult.set(response.status ?? null);
        },
        error: () => {
          this.isStatusLoading.set(false);
        },
      });
  }

  onSearch(term: string): void {
    this.searchTerm = term;
    this.currentPage = 1;
    this.loadInstances();
  }

  loadPage(page: number): void {
    this.currentPage = page;
    this.loadInstances();
  }

  retry(): void {
    this.loadInstances();
  }

  tokenDisplay(instance: UazapiInstance): string {
    const token = this.copyableToken(instance);
    if (token !== null) {
      return this.tokenPreview(token);
    }

    const preview = this.normalizeText(instance.token_preview);
    return preview.length > 0 ? preview : '—';
  }

  copyableToken(instance: UazapiInstance): string | null {
    const directToken = this.normalizeText(instance.token);
    if (directToken.length > 0) return directToken;

    const metadataToken = this.pickConnectionString(instance.metadata ?? {}, [
      'token',
      'instance.token',
      'data.token',
      'connection.token',
    ]);
    return metadataToken;
  }

  tokenPreview(token?: string | null): string {
    const sanitizedToken = typeof token === 'string' ? token.trim() : '';
    if (sanitizedToken.length === 0) return '—';
    if (sanitizedToken.length <= 4) return '••••';
    return `••••••${sanitizedToken.slice(-4)}`;
  }

  displayName(instance: UazapiInstance): string {
    const name = this.normalizeText(instance.name);
    if (name.length > 0) return name;
    const systemName = this.normalizeText(instance.system_name);
    if (systemName.length > 0) return systemName;
    return instance.id;
  }

  statusLabel(status?: string | null): string {
    const normalizedStatus = this.normalizeText(status).toLowerCase();
    switch (normalizedStatus) {
      case 'connected':
        return 'Conectado';
      case 'connecting':
        return 'Conectando';
      case 'qr':
        return 'Aguardando QR';
      case 'disconnected':
        return 'Desconectado';
      default:
        return 'Indefinido';
    }
  }

  statusBadge(status?: string | null): 'online' | 'offline' | 'warning' | 'error' | 'idle' {
    const normalizedStatus = this.normalizeText(status).toLowerCase();
    switch (normalizedStatus) {
      case 'connected':
        return 'online';
      case 'connecting':
      case 'qr':
        return 'warning';
      case 'disconnected':
        return 'offline';
      default:
        return 'idle';
    }
  }

  isSelectable(instance: UazapiInstance): boolean {
    return (instance.status ?? '').toLowerCase() === 'connected';
  }

  copy(value: string): void {
    void navigator.clipboard
      .writeText(value)
      .then(() => this.toast.success('Token copiado com sucesso.'))
      .catch(() => this.toast.error('Não foi possível copiar o token.'));
  }

  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedIds());
      if (checked) selected.add(id);
      else selected.delete(id);

      this.selectedIds.set([...selected]);
      const selectable = this.instances().filter((instance) => this.isSelectable(instance));
      const allSelected =
        selectable.length > 0 && selectable.every((instance) => selected.has(instance.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private loadInstances(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.service
      .list({
        search: this.searchTerm.length > 0 ? this.searchTerm : undefined,
        status: this.toStatusFilter(this.statusFilterControl.value),
        page: this.currentPage,
        per_page: 15,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.instances.set(response.data);
          this.meta.set(response.meta);
          this.syncSelectionControls(response.data);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }

  private syncSelectionControls(instances: UazapiInstance[]): void {
    const activeIds = new Set(instances.map((item) => item.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }

    const selected = this.selectedIds().filter((id) => activeIds.has(id));
    this.selectedIds.set(selected);

    for (const item of instances) {
      this.getRowSelectionControl(item.id).setValue(selected.includes(item.id));
    }

    const selectable = instances.filter((instance) => this.isSelectable(instance));
    this.selectAllControl.setValue(selectable.length > 0 && selected.length === selectable.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }

  private pickConnectionString(source: Record<string, unknown>, paths: string[]): string | null {
    for (const path of paths) {
      const value = this.getNestedValue(source, path);
      if (typeof value === 'string' && value.trim() !== '') return value.trim();
    }
    return null;
  }

  private getNestedValue(source: Record<string, unknown>, path: string): unknown {
    const chunks = path.split('.');
    let current: unknown = source;

    for (const chunk of chunks) {
      if (typeof current !== 'object' || current === null || !(chunk in current)) {
        return null;
      }
      current = (current as Record<string, unknown>)[chunk];
    }

    return current;
  }

  private toStatusFilter(value: string): UazapiInstanceStatus {
    const normalized = value as UazapiInstanceStatus;
    if (
      normalized === 'all' ||
      normalized === 'connected' ||
      normalized === 'connecting' ||
      normalized === 'qr' ||
      normalized === 'disconnected'
    ) {
      return normalized;
    }
    return 'all';
  }

  private stringify(value: unknown): string {
    if (typeof value === 'string') return value;
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    if (value === null || value === undefined) return '—';
    try {
      return JSON.stringify(value);
    } catch {
      return '—';
    }
  }

  private normalizeText(value?: string | null): string {
    return typeof value === 'string' ? value.trim() : '';
  }
}
