import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { Job } from 'bullmq';
import { BullMQQueueFactory } from '../../../shared/services/queue/bullmq-queue-factory.service';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { ConnectionStatusService } from '../services/connection-status.service';

/**
 * Dados do job de atualizacao de status de conexao de uma instancia WhatsApp.
 */
export interface UpdateConnectionStatusJobData {
  /** ID da instancia de chat a atualizar. */
  instanceId: string;
  /** Novo status de conexao normalizado. */
  status: string;
  /** Timestamp ISO do momento da conexao. */
  connectedAt: string;
  /** Telefone do proprietario da instancia quando disponivel. */
  owner?: string;
  /** Token do webhook para invalidacao de cache. */
  webhookToken?: string;
}

/**
 * Processa jobs de atualização de status de conexão de instâncias WhatsApp.
 * Chama PATCH /api/internal/chat/instances/{id}/connection-status via api/ HTTP.
 */
@Injectable()
export class UpdateConnectionStatusProcessor implements OnModuleInit {
  private readonly logger = new Logger(UpdateConnectionStatusProcessor.name);

  constructor(
    private readonly queueFactory: BullMQQueueFactory,
    private readonly internalApiClient: InternalApiClientService,
  ) {}

  /**
   * Registra o worker BullMQ para processar jobs de atualizacao de status de conexao.
   */
  onModuleInit(): void {
    this.queueFactory.createWorker<UpdateConnectionStatusJobData, void>(
      ConnectionStatusService.CONNECTION_STATUS_QUEUE,
      (job) => this.process(job),
    );
  }

  /**
   * Processa um job de atualizacao de status de conexao chamando PATCH na api/.
   *
   * @param job Job BullMQ com dados da instancia e novo status
   * @throws Error Quando a chamada HTTP para api/ falha
   */
  private async process(
    job: Job<UpdateConnectionStatusJobData>,
  ): Promise<void> {
    const { instanceId, status, connectedAt, owner } = job.data;

    try {
      await this.internalApiClient.patch(
        `/api/internal/chat/instances/${encodeURIComponent(instanceId)}/connection-status`,
        { status, connected_at: connectedAt, owner },
        'connection_status_update',
      );
    } catch (error) {
      this.logger.error(
        `Failed to update connection status for instance ${instanceId}: ${
          error instanceof Error ? error.message : String(error)
        }`,
      );
      throw error;
    }
  }
}
