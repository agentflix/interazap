import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import { type NegotiationContactSummary } from 'src/app/core/services/negotiation.service';
import { ButtonComponent } from '@shared/components/buttons';

/**
 * Sidebar card for primary negotiation contact.
 */
@Component({
  selector: 'app-negotiation-contact-card',
  standalone: true,
  imports: [RouterLink, LucideAngularModule, ButtonComponent],
  templateUrl: './negotiation-contact-card.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'flex' },
})
export class NegotiationContactCardComponent {
  readonly contact = input<NegotiationContactSummary | null>(null);
  readonly contactId = input<string | number | null>(null);
}
