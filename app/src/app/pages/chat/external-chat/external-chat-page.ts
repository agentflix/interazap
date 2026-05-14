import { type OnInit, ChangeDetectionStrategy, Component, inject, signal, computed } from '@angular/core';
import type { SafeResourceUrl } from '@angular/platform-browser';
import { DomSanitizer } from '@angular/platform-browser';
import { environment } from '@env/environment';
import { CopyButtonComponent } from './copy-button/copy-button';
import { LucideAngularModule } from 'lucide-angular';
import { AfCodeBlockComponent, AfPageTitleComponent, AfSelectInputComponent } from '@shared/components';
import { FormControl } from '@angular/forms';
import { ToastService } from '@core/services/toast.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import type { AfSelectOption } from '@shared/components';

/**
 * External Chat page — displays public widget URL and embed code for external sites.
 *
 * @remarks
 * Allows admins to copy the public chat widget URL and a JavaScript embed snippet
 * to place on external websites. The public widget is accessible at
 * `/chat/external/{tenantId}` and the embed version at `/embed/{tenantId}`.
 */
@Component({
  selector: 'app-external-chat-page',
  standalone: true,
  imports: [LucideAngularModule, AfPageTitleComponent, AfCodeBlockComponent, AfSelectInputComponent, CopyButtonComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './external-chat-page.html',
})
export class ExternalChatPage implements OnInit {
  private readonly sanitizer = inject(DomSanitizer);
  private readonly authStore = inject(AuthStoreService);
  private readonly toast = inject(ToastService);

  // ─── Embed type selection ─────────────────────────────────────────────────
  readonly embedTypeControl = new FormControl<'full-page' | 'iframe' | 'widget'>('widget', { nonNullable: true });

  readonly embedTypeOptions: AfSelectOption[] = [
    { label: 'Widget Flutuante (recomendado)', value: 'widget' },
    { label: 'Iframe', value: 'iframe' },
    { label: 'Página Completa', value: 'full-page' },
  ];

  // ─── Tenant ID ────────────────────────────────────────────────────────────
  private readonly _tenantId = signal<string>('');

  // ─── Public URLs (environment-aware) ─────────────────────────────────────
  readonly widgetUrl = computed(
    () => `${environment.publicWebchatUrl}/chat/external/${this._tenantId()}`,
  );
  readonly embedUrl = computed(
    () => `${environment.publicWebchatUrl}/embed/${this._tenantId()}`,
  );

  readonly safeEmbedUrl = computed<SafeResourceUrl>(() =>
    this.sanitizer.bypassSecurityTrustResourceUrl(this.embedUrl()),
  );

  // ─── Embed codes (signal-based, only recomputes when tenant changes) ───────
  readonly widgetEmbedCode = computed(() =>
    this.buildWidgetCode(this._tenantId()),
  );
  readonly iframeEmbedCode = computed(() =>
    this.buildIframeCode(this._tenantId()),
  );
  readonly fullPageEmbedCode = computed(() =>
    this.buildFullPageCode(this._tenantId()),
  );

  // ─── Active embed code (derived from selected type) ────────────────────────
  readonly activeEmbedCode = computed(() => {
    switch (this.embedTypeControl.value) {
      case 'widget':
        return this.widgetEmbedCode();
      case 'iframe':
        return this.iframeEmbedCode();
      case 'full-page':
        return this.fullPageEmbedCode();
    }
  });

  ngOnInit(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    this._tenantId.set(String(tenantId ?? 'unknown-tenant'));
  }

  onCopied(): void {
    this.toast.success('Código copiado para a área de transferência');
  }

  private buildWidgetCode(tenantId: string): string {
    return `<script>
(function(w,d,s,o,f,js,fjs){
  w['InteraZap']=o;w[o]=w[o]||function(){
  (w[o].q=w[o].q||[]).push(arguments)};
  js=d.createElement(s),fjs=d.getElementsByTagName(s)[0];
  js.id='iz-embed';js.src=f;js.async=1;fjs.parentNode.insertBefore(js,fjs);
}(window,document,'script','iz','${environment.publicWebchatUrl}/embed/${tenantId}.js'));
</script>`;
  }

  private buildIframeCode(tenantId: string): string {
    const url = `${environment.publicWebchatUrl}/embed/${tenantId}`;
    return `<iframe
  src="${url}"
  width="100%"
  height="600"
  style="border:none;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,0.12);"
  allow="camera;microphone"
></iframe>`;
  }

  private buildFullPageCode(tenantId: string): string {
    const url = `${environment.publicWebchatUrl}/chat/external/${tenantId}`;
    return `<a href="${url}" target="_blank">Abrir chat InteraZap</a>`;
  }
}

export default ExternalChatPage;