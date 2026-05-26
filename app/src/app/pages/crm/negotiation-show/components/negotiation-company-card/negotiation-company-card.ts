import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import { type CRMCompany } from 'src/app/core/services/crm-company.service';
import { type NegotiationCompanySummary } from 'src/app/core/services/negotiation.service';
import { ButtonComponent } from '@shared/components/buttons';

/**
 * Cartão na sidebar com informações da empresa vinculada à negociação.
 */
@Component({
  selector: 'app-negotiation-company-card',
  standalone: true,
  imports: [RouterLink, LucideAngularModule, ButtonComponent],
  templateUrl: './negotiation-company-card.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'flex' },
})
export class NegotiationCompanyCardComponent {
  readonly company = input<NegotiationCompanySummary | null>(null);
  readonly companyId = input<string | number | null>(null);

  getCompanyAddress(company?: CRMCompany | NegotiationCompanySummary | null): string {
    if (!company) return 'Endereço não informado';
    const parts = [];
    if (company.address) {
      parts.push(company.address);
    }
    const cityState = [company.city, company.state].filter(Boolean).join(' / ');
    if (cityState) {
      parts.push(cityState);
    }
    if (company.zip_code) {
      parts.push(company.zip_code);
    }
    return parts.length ? parts.join(' - ') : 'Endereço não informado';
  }

  getCompanyPhone(company?: CRMCompany | NegotiationCompanySummary | null): string {
    if (!company?.phone) return 'Telefone não informado';
    return company.phone;
  }
}
