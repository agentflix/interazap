import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router } from '@angular/router';

/**
 * Redirect legacy upload route to knowledge list with upload modal opened.
 */
@Component({
  selector: 'app-ai-knowledge-upload-redirect',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './knowledge-upload-redirect.html',
})
export class KnowledgeUploadRedirectComponent {
  private readonly router = inject(Router);

  constructor() {
    void this.router.navigate(['/ai/knowledge/base'], {
      queryParams: { create: '1' },
      replaceUrl: true,
    });
  }
}
