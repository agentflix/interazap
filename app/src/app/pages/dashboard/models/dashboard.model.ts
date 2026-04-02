/**
 * Top-level summary metrics for the dashboard.
 */
export interface DashboardSummary {
  /** Total revenue from won negotiations */
  total_revenue_won: number;
  /** Sum of open negotiation values in the pipeline */
  pipeline_open_value: number;
  /** Number of currently open support tickets */
  active_tickets_count: number;
  /** Average CSAT rating (0-5 scale) */
  csat_average: number;
}

/**
 * Single step in the sales funnel visualization.
 */
export interface FunnelStep {
  /** Name of the funnel stage */
  step_name: string;
  /** Hex color code for the step visualization */
  step_color: string;
  /** Number of negotiations in this step */
  count: number;
  /** Total value of negotiations in this step */
  total_amount: number;
}

/**
 * Monthly revenue breakdown by status.
 */
export interface RevenueMonth {
  /** Month number (1-12) */
  month: number;
  /** Year */
  year: number;
  /** Total amount from won negotiations */
  won_amount: number;
  /** Total value of open negotiations */
  open_amount: number;
}

/**
 * Negotiation statistics broken down by status and loss reasons.
 */
export interface NegotiationStats {
  /** Count of negotiations by their current status */
  by_status: {
    open: number;
    won: number;
    lost: number;
  };
  /** Top reasons for lost negotiations */
  top_loss_reasons: { name: string; count: number }[];
}

/**
 * Support ticket volume and SLA metrics.
 */
export interface TicketStats {
  /** Daily ticket volume over the period */
  daily_volume: { date: string; count: number }[];
  /** Ticket counts grouped by priority level */
  by_priority: {
    low: number;
    normal: number;
    high: number;
    urgent: number;
  };
  /** Percentage of tickets meeting SLA first-response target */
  sla_compliance_rate: number;
  /** Average minutes until first response */
  avg_first_response_minutes: number;
}

/**
 * Customer satisfaction score distribution and summary.
 */
export interface CsatStats {
  /** Average rating across all evaluations (1-5) */
  average_rating: number;
  /** Total number of evaluations received */
  total_evaluations: number;
  /** Distribution of ratings (1-5 stars) */
  distribution: Record<1 | 2 | 3 | 4 | 5, number>;
}

/**
 * Recent activity entry for the activity feed.
 */
export interface RecentActivity {
  /** Type of activity (e.g., 'negotiation_created', 'ticket_resolved') */
  type: string;
  /** Short title of the activity */
  title: string;
  /** Detailed description of what happened */
  description: string;
  /** ISO 8601 timestamp when the activity occurred */
  created_at: string;
  /** Icon identifier for the activity type */
  icon: string;
}

/**
 * Complete dashboard data envelope returned by the API.
 */
export interface DashboardData {
  /** Key summary metrics */
  summary: DashboardSummary;
  /** Sales funnel stage breakdown */
  funnel: FunnelStep[];
  /** Monthly revenue history */
  revenue: RevenueMonth[];
  /** Negotiation statistics */
  negotiations: NegotiationStats;
  /** Support ticket metrics */
  tickets: TicketStats;
  /** Customer satisfaction data */
  csat: CsatStats;
  /** Recent activity feed entries */
  activities: RecentActivity[];
}

/**
 * Predefined date range filter options.
 */
export type DashboardFilterOption =
  | 'today'
  | 'yesterday'
  | 'last7'
  | 'last15'
  | 'last30'
  | 'custom';

/**
 * Represents a custom date range selection.
 */
export interface DateRange {
  /** Start date in ISO 8601 format (YYYY-MM-DD) */
  from: string;
  /** End date in ISO 8601 format (YYYY-MM-DD) */
  to: string;
  /** The filter option that defined this range */
  option: DashboardFilterOption;
  /** Human-readable label for the range */
  label: string;
}
