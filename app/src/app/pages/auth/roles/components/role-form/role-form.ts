import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { type FormControl, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfScrollAreaComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  type GroupedPermissions,
  type Role,
  type RolePayload,
} from '../../../../../core/models/role.model';
import { RoleService } from '@core/services/role.service';

/** Map module keys to Portuguese labels */
const MODULE_LABELS: Record<string, string> = {
  users: 'Usuários',
  chat: 'Chat',
  crm: 'CRM',
  channels: 'Canais',
  reports: 'Relatórios',
  billing: 'Financeiro',
  settings: 'Configurações',
  departments: 'Departamentos',
  contacts: 'Contatos',
  pipelines: 'Pipelines',
  transmission_lists: 'Listas de Transmissão',
  automation: 'Automação',
  dashboard: 'Dashboard',
  platform: 'Plataforma',
  ai: 'Inteligência Artificial',
  gateway: 'Gateway',
};

/** Map permission suffix to Portuguese verb */
const PERMISSION_VERB_LABELS: Record<string, string> = {
  view: 'Visualizar',
  manage: 'Gerenciar',
  create: 'Criar',
  edit: 'Editar',
  delete: 'Excluir',
  export: 'Exportar',
  import: 'Importar',
  approve: 'Aprovar',
  list: 'Listar',
};

/**
 * Role form — create/edit access profiles with granular permissions.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-role-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfTextInputComponent,
    AfCheckboxInputComponent,
    AfScrollAreaComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './role-form.html',
})
export class RoleFormComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(RoleService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  /** @input Role to edit; null for create mode */
  readonly role = input<Role | null>(null);
  readonly saved = output<Role>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly isLoadingPermissions = signal(false);
  readonly errorMessage = signal('');
  readonly groupedPermissions = signal<GroupedPermissions>({});
  readonly selectedPermissions = signal<Set<string>>(new Set());
  readonly expandedModules = signal<Set<string>>(new Set());

  readonly permissionModules = computed(() =>
    Object.keys(this.groupedPermissions()).sort((a, b) =>
      this.formatModuleLabel(a).localeCompare(this.formatModuleLabel(b)),
    ),
  );

  readonly form = this.fb.group({
    name: this.fb.control('', Validators.required),
  });

  // Dynamic checkbox controls map (by permission key or module key)
  private readonly permissionControls = new Map<string, FormControl<boolean>>();
  private readonly moduleControls = new Map<string, FormControl<boolean>>();

  constructor() {
    effect(() => {
      const item = this.role();
      if (!item) {
        this.lastLoadedId.set(null);
        this.form.reset({ name: '' });
        this.selectedPermissions.set(new Set());
        this.errorMessage.set('');
        return;
      }
      if (this.lastLoadedId() === item.id) return;
      this.lastLoadedId.set(item.id);
      this.form.reset({ name: item.name });
      const perms = item.permissions ?? [];
      this.selectedPermissions.set(new Set(perms));
      this.errorMessage.set('');
      // Sync all controls after permissions signal changes
      this.syncAllControls();
    });
  }

  ngOnInit(): void {
    this.loadPermissions();
  }

  // ── Actions ───────────────────────────────────────────────────────────────────

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const payload: RolePayload = {
      name: this.form.controls.name.value?.trim() ?? '',
      permissions: [...this.selectedPermissions()],
    };

    const editing = this.role();
    this.isSaving.set(true);
    this.errorMessage.set('');

    const request = editing
      ? this.service.update(editing.id, payload)
      : this.service.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response.data);
      },
      error: (error) => {
        this.isSaving.set(false);
        this.errorMessage.set(error.error?.message || 'Não foi possível salvar o perfil.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  // ── Permission toggles ────────────────────────────────────────────────────────

  togglePermission(permission: string): void {
    const set = new Set(this.selectedPermissions());
    if (set.has(permission)) {
      set.delete(permission);
    } else {
      set.add(permission);
    }
    this.selectedPermissions.set(set);
    this.syncModuleControl(this.getModuleForPermission(permission));
  }

  toggleModule(module: string): void {
    const perms = this.groupedPermissions()[module];
    const allSelected = this.isModuleFullySelected(module);
    const set = new Set(this.selectedPermissions());
    for (const p of perms) {
      if (allSelected) {
        set.delete(p);
      } else {
        set.add(p);
      }
    }
    this.selectedPermissions.set(set);
    this.syncModuleControl(module);
    this.syncPermissionControls(module);
  }

  isPermissionSelected(permission: string): boolean {
    return this.selectedPermissions().has(permission);
  }

  isModuleFullySelected(module: string): boolean {
    const perms = this.groupedPermissions()[module];
    return perms.length > 0 && perms.every((p) => this.selectedPermissions().has(p));
  }

  selectedCountForModule(module: string): number {
    const perms = this.groupedPermissions()[module];
    return perms.filter((p) => this.selectedPermissions().has(p)).length;
  }

  // ── Module collapse ───────────────────────────────────────────────────────────

  isModuleExpanded(module: string): boolean {
    return this.expandedModules().has(module);
  }

  toggleModuleExpanded(module: string): void {
    const set = new Set(this.expandedModules());
    if (set.has(module)) {
      set.delete(module);
    } else {
      set.add(module);
    }
    this.expandedModules.set(set);
  }

  // ── Dynamic checkbox controls ────────────────────────────────────────────────

  getPermissionCheckboxControl(permission: string): FormControl<boolean> {
    if (this.permissionControls.has(permission)) {
      return this.permissionControls.get(permission) as FormControl<boolean>;
    }
    const ctrl = this.fb.nonNullable.control(this.selectedPermissions().has(permission));
    ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((v) => {
      const set = new Set(this.selectedPermissions());
      if (v) {
        set.add(permission);
      } else {
        set.delete(permission);
      }
      this.selectedPermissions.set(set);
      const module = this.getModuleForPermission(permission);
      if (module) this.syncModuleControl(module);
    });
    this.permissionControls.set(permission, ctrl as FormControl<boolean>);
    return ctrl as FormControl<boolean>;
  }

  getModuleCheckboxControl(module: string): FormControl<boolean> {
    if (this.moduleControls.has(module)) {
      return this.moduleControls.get(module) as FormControl<boolean>;
    }
    const ctrl = this.fb.nonNullable.control(this.isModuleFullySelected(module));
    ctrl.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.toggleModule(module);
    });
    this.moduleControls.set(module, ctrl as FormControl<boolean>);
    return ctrl as FormControl<boolean>;
  }

  // ── Labels ────────────────────────────────────────────────────────────────────

  formatModuleLabel(module: string): string {
    return MODULE_LABELS[module] ?? module.charAt(0).toUpperCase() + module.slice(1);
  }

  formatPermissionLabel(permission: string): string {
    const parts = permission.split('.');
    const suffix = parts.at(-1) ?? '';
    return PERMISSION_VERB_LABELS[suffix] ?? suffix.charAt(0).toUpperCase() + suffix.slice(1);
  }

  // ── Error getter ──────────────────────────────────────────────────────────────

  nameError(): string {
    const c = this.form.controls.name;
    if (!c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Nome é obrigatório.';
    return '';
  }

  // ── Private helpers ───────────────────────────────────────────────────────────

  private loadPermissions(): void {
    this.isLoadingPermissions.set(true);
    this.service
      .permissions()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.groupedPermissions.set(response.data.permissions);
          this.isLoadingPermissions.set(false);
          // Expand first module by default
          const first = Object.keys(response.data.permissions)[0];
          if (first) this.expandedModules.set(new Set([first]));
          this.syncAllControls();
        },
        error: () => {
          this.isLoadingPermissions.set(false);
          this.errorMessage.set('Não foi possível carregar as permissões.');
        },
      });
  }

  private syncAllControls(): void {
    for (const [perm, ctrl] of this.permissionControls.entries()) {
      ctrl.setValue(this.selectedPermissions().has(perm), { emitEvent: false });
    }
    for (const [module, ctrl] of this.moduleControls.entries()) {
      ctrl.setValue(this.isModuleFullySelected(module), { emitEvent: false });
    }
  }

  private syncModuleControl(module: string): void {
    const ctrl = this.moduleControls.get(module);
    ctrl?.setValue(this.isModuleFullySelected(module), { emitEvent: false });
  }

  private syncPermissionControls(module: string): void {
    const perms = this.groupedPermissions()[module];
    for (const p of perms) {
      const ctrl = this.permissionControls.get(p);
      ctrl?.setValue(this.selectedPermissions().has(p), { emitEvent: false });
    }
  }

  private getModuleForPermission(permission: string): string {
    for (const [module, perms] of Object.entries(this.groupedPermissions())) {
      if (perms.includes(permission)) return module;
    }
    return '';
  }
}
