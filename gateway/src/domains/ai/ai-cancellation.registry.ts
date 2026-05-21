import { Injectable } from '@nestjs/common';
import { RedisService } from '../../infrastructure/redis/redis.service';

@Injectable()
export class AiCancellationRegistry {
  private static readonly KEY_PREFIX = 'ai:run:cancelled';
  private static readonly TTL_SECONDS = 300;

  constructor(private readonly redisService: RedisService) {}

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

  async isCancelled(runId: string): Promise<boolean> {
    if (runId.trim() === '') {
      return false;
    }

    return (await this.redisService.get(this.cacheKey(runId))) !== null;
  }

  async clear(runId: string): Promise<void> {
    if (runId.trim() === '') {
      return;
    }

    await this.redisService.delete(this.cacheKey(runId));
  }

  private cacheKey(runId: string): string {
    return `${AiCancellationRegistry.KEY_PREFIX}:${runId}`;
  }
}
