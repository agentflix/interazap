import {
  Injectable,
  Logger,
  ServiceUnavailableException,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';

interface CacheEntry {
  value: string;
  expiresAt: number;
}

@Injectable()
export class SecretsService {
  private readonly cache = new Map<string, CacheEntry>();
  private readonly TTL_MS = 300_000; // 5 minutes
  private readonly logger = new Logger(SecretsService.name);

  constructor(private readonly configService: ConfigService) {}

  /**
   * Get a secret by key. Tries AWS SM → Vault → ENV in order.
   * Results are cached for 5 minutes.
   */
  async getSecret(key: string): Promise<string> {
    const cached = this.getCached(key);
    if (cached !== null) {
      return cached;
    }

    const awsValue = await this.fetchFromAwsSecretsManager(key);
    if (awsValue !== null) {
      this.logger.log(`Secret '${key}' resolved from: AWS_SM`);
      this.setCache(key, awsValue);
      return awsValue;
    }

    const vaultValue = await this.fetchFromVault(key);
    if (vaultValue !== null) {
      this.logger.log(`Secret '${key}' resolved from: VAULT`);
      this.setCache(key, vaultValue);
      return vaultValue;
    }

    const envValue = this.fetchFromEnv(key);
    if (envValue !== null) {
      this.logger.log(`Secret '${key}' resolved from: ENV`);
      this.setCache(key, envValue);
      return envValue;
    }

    throw new ServiceUnavailableException({
      code: 'SECRETS_UNAVAILABLE',
      message: `Unable to resolve secret '${key}' from any source`,
    });
  }

  /**
   * Get a Telegram bot token for a given instance.
   * Key pattern: `telegram/bot-token/{instanceId}`
   */
  async getBotToken(instanceId: string): Promise<string> {
    return this.getSecret(`telegram/bot-token/${instanceId}`);
  }

  /**
   * Invalidate cache for a specific key.
   */
  invalidateCache(key: string): void {
    this.cache.delete(key);
  }

  /**
   * Invalidate all cached secrets.
   */
  invalidateAll(): void {
    this.cache.clear();
  }

  private async fetchFromAwsSecretsManager(
    key: string,
  ): Promise<string | null> {
    const region = this.configService.get<string>('AWS_REGION');
    const accessKeyId = this.configService.get<string>('AWS_ACCESS_KEY_ID');

    if (!region || !accessKeyId) {
      return null;
    }

    try {
      const { SecretsManagerClient, GetSecretValueCommand } =
        await import('@aws-sdk/client-secrets-manager');

      const client = new SecretsManagerClient({ region });
      const command = new GetSecretValueCommand({ SecretId: key });
      const response = await client.send(command);

      return response.SecretString ?? null;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Unknown error';
      this.logger.warn(
        `AWS Secrets Manager unavailable for key '${key}': ${message}`,
      );
      return null;
    }
  }

  private async fetchFromVault(key: string): Promise<string | null> {
    const vaultAddr = this.configService.get<string>('VAULT_ADDR');
    const vaultToken = this.configService.get<string>('VAULT_TOKEN');

    if (!vaultAddr || !vaultToken) {
      return null;
    }

    try {
      const url = `${vaultAddr}/v1/secret/data/${key}`;
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'X-Vault-Token': vaultToken,
        },
      });

      if (!response.ok) {
        this.logger.warn(
          `Vault returned HTTP ${response.status} for key '${key}'`,
        );
        return null;
      }

      const body = (await response.json()) as {
        data?: { data?: Record<string, string> };
      };
      const value = body?.data?.data?.value ?? null;

      return value;
    } catch (error: unknown) {
      const message = error instanceof Error ? error.message : 'Unknown error';
      this.logger.warn(`Vault unavailable for key '${key}': ${message}`);
      return null;
    }
  }

  private fetchFromEnv(key: string): string | null {
    // Try the key directly via ConfigService, then normalized (slashes → underscores, uppercased)
    const direct = this.configService.get<string>(key);
    if (direct !== undefined && direct !== '') {
      return direct;
    }

    const normalized = key.replace(/\//g, '_').toUpperCase();
    const fromNormalized = this.configService.get<string>(normalized);
    if (fromNormalized !== undefined && fromNormalized !== '') {
      return fromNormalized;
    }

    return null;
  }

  private getCached(key: string): string | null {
    const entry = this.cache.get(key);
    if (!entry) {
      return null;
    }

    if (Date.now() > entry.expiresAt) {
      this.cache.delete(key);
      return null;
    }

    return entry.value;
  }

  private setCache(key: string, value: string): void {
    this.cache.set(key, {
      value,
      expiresAt: Date.now() + this.TTL_MS,
    });
  }
}
