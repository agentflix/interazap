import { Injectable } from '@nestjs/common';
import { RedisService } from '../../infrastructure/redis/redis.service';

/**
 * Registro em Redis responsável por rastrear runs de AI que foram canceladas.
 *
 * Contexto: utiliza chaves com prefixo `ai:run:cancelled` e TTL de 5 minutos
 * para sinalizar o cancelamento entre o loop de tool calls do orquestrador
 * e o listener de eventos Redis Pub/Sub.
 */
@Injectable()
export class AiCancellationRegistry {
  private static readonly KEY_PREFIX = 'ai:run:cancelled';
  private static readonly TTL_SECONDS = 300;

  constructor(private readonly redisService: RedisService) {}

  /**
   * Marca uma run como cancelada persistindo a flag no Redis com TTL.
   * @param runId - Identificador da run a ser marcada como cancelada.
   * @returns Promise resolvida após a persistência da flag de cancelamento.
   */
  async markCancelled(runId: string): Promise<void> {
    if (runId.trim() === '') {
      return;
    }

    await this.redisService.set(
      this.cacheKey(runId),
      '1',
      AiCancellationRegistry.TTL_SECONDS,
    );
  }

  /**
   * Verifica se uma run foi marcada como cancelada no Redis.
   * @param runId - Identificador da run a ser verificada.
   * @returns `true` quando a flag de cancelamento estiver ativa, `false` caso contrário.
   */
  async isCancelled(runId: string): Promise<boolean> {
    if (runId.trim() === '') {
      return false;
    }

    return (await this.redisService.get(this.cacheKey(runId))) !== null;
  }

  /**
   * Remove a flag de cancelamento de uma run após sua conclusão.
   * @param runId - Identificador da run cuja flag será removida.
   * @returns Promise resolvida após a remoção da chave no Redis.
   */
  async clear(runId: string): Promise<void> {
    if (runId.trim() === '') {
      return;
    }

    await this.redisService.delete(this.cacheKey(runId));
  }

  /** Monta a chave Redis com prefixo padrão para a run informada. */
  private cacheKey(runId: string): string {
    return `${AiCancellationRegistry.KEY_PREFIX}:${runId}`;
  }
}
