/**
 * Context shared across AI tool execution flows.
 */
export interface ToolExecutionContext {
  tenantId: string;
  runId: string;
  correlationId?: string;
  agentId: string;
  traceId?: string;
  ticketId?: string;
  targetAgentId?: string;
  agentRole?: string;
  delegationDepth?: number;
  delegationStack?: string[];
}
