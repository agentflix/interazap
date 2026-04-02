import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { AfCardComponent, AfEmptyStateComponent, AfStatusBadgeComponent } from '@shared/components';
import { LucideAngularModule } from 'lucide-angular';
import { type RecentActivity } from '../../models/dashboard.model';

/** Parsed activity row for template rendering. */
interface ActivityRow {
  activity: RecentActivity;
  value: number | null;
  status: string | null;
  statusVariant: 'online' | 'error' | 'warning' | 'idle';
  statusLabel: string;
}

/**
 * Recent activities list — shows latest system activities with relative timestamps,
 * formatted BRL values, and status badges in pt-BR.
 */
@Component({
  selector: 'app-recent-activities',
  standalone: true,
  imports: [
    AfCardComponent,
    AfEmptyStateComponent,
    AfStatusBadgeComponent,
    LucideAngularModule,
    CurrencyPipe,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { class: 'block' },
  templateUrl: './recent-activities.html',
})
export class RecentActivitiesComponent {
  readonly activities = input.required<RecentActivity[]>();

  private readonly relativeFormatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });

  /** Status translations to pt-BR */
  private readonly statusMap: Record<
    string,
    { label: string; variant: ActivityRow['statusVariant'] }
  > = {
    accepted: { label: 'Aceita', variant: 'online' },
    rejected: { label: 'Rejeitada', variant: 'error' },
    sent: { label: 'Enviada', variant: 'idle' },
    pending: { label: 'Pendente', variant: 'warning' },
    open: { label: 'Aberta', variant: 'idle' },
    closed: { label: 'Fechada', variant: 'online' },
    won: { label: 'Ganha', variant: 'online' },
    lost: { label: 'Perdida', variant: 'error' },
    draft: { label: 'Rascunho', variant: 'warning' },
  };

  /** Pre-parse activities into template-ready rows. */
  protected readonly rows = (() => {
    // Using a getter-like computed via input transform
    return () => this.activities().map((activity) => this.parseActivity(activity));
  })();

  private parseActivity(activity: RecentActivity): ActivityRow {
    const desc = activity.description ?? '';
    let value: number | null = null;
    let status: string | null = null;

    // Parse "Value: 5555.00" pattern
    const valueMatch = desc.match(/value:\s*([\d.,]+)/i);
    if (valueMatch) {
      value = parseFloat(valueMatch[1].replace(',', '.'));
      if (isNaN(value)) value = null;
    }

    // Parse "Status: accepted" pattern
    const statusMatch = desc.match(/status:\s*(\w+)/i);
    if (statusMatch) {
      status = statusMatch[1].toLowerCase();
    }

    const mapped = status ? this.statusMap[status] : null;

    return {
      activity,
      value,
      status,
      statusVariant: mapped?.variant ?? 'idle',
      statusLabel: mapped?.label ?? status ?? '',
    };
  }

  formatRelativeTime(iso: string): string {
    const date = new Date(iso);
    const diffMs = date.getTime() - Date.now();
    const diffSeconds = Math.round(diffMs / 1000);
    const absSeconds = Math.abs(diffSeconds);

    if (absSeconds < 60) {
      return this.relativeFormatter.format(diffSeconds, 'second');
    }

    const diffMinutes = Math.round(diffSeconds / 60);
    if (Math.abs(diffMinutes) < 60) {
      return this.relativeFormatter.format(diffMinutes, 'minute');
    }

    const diffHours = Math.round(diffMinutes / 60);
    if (Math.abs(diffHours) < 24) {
      return this.relativeFormatter.format(diffHours, 'hour');
    }

    const diffDays = Math.round(diffHours / 24);
    return this.relativeFormatter.format(diffDays, 'day');
  }

  resolveIcon(name: string): string {
    const mapping: Record<string, string> = {
      lucideHandshake: 'handshake',
      lucideHeadset: 'headset',
      lucideFileText: 'file-text',
      lucideActivity: 'activity',
    };
    return mapping[name] ?? name ?? 'activity';
  }
}

export default RecentActivitiesComponent;
