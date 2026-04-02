import { Injectable, Logger } from '@nestjs/common';
import { WhatsAppProvider } from '../contracts/provider.interface';
import { UazapiAdapter } from './uazapi/uazapi.adapter';
import { ZapiAdapter } from './zapi/zapi.adapter';
import { ProviderName } from '../models/provider.model';

export type { ProviderName };

/**
 * Fabrica de adaptadores de provedores de WhatsApp suportados pelo gateway.
 */
@Injectable()
export class ProviderFactory {
  private readonly logger = new Logger(ProviderFactory.name);
  private readonly providers: Map<ProviderName, WhatsAppProvider>;

  constructor(
    private readonly uazapiAdapter: UazapiAdapter,
    private readonly zapiAdapter: ZapiAdapter,
  ) {
    this.providers = new Map<ProviderName, WhatsAppProvider>();

    // Register providers
    this.providers.set('uazapi', uazapiAdapter);
    this.providers.set('zapi', zapiAdapter);
  }

  /**
   * Retorna o adaptador associado ao nome do provedor informado.
   *
   * @param name - Nome do provedor registrado na fabrica
   * @returns Instancia do adaptador correspondente
   * @throws Error quando o provedor nao esta registrado
   */
  getProvider(name: ProviderName): WhatsAppProvider {
    const provider = this.providers.get(name);
    if (!provider) {
      this.logger.error(`Provider not found: ${name}`);
      throw new Error(`Provider not found: ${name}`);
    }
    return provider;
  }

  /**
   * Verifica se um nome de provedor esta registrado na fabrica.
   *
   * @param name - Nome a verificar
   * @returns true quando o provedor esta registrado
   */
  hasProvider(name: string): name is ProviderName {
    return this.providers.has(name as ProviderName);
  }

  /**
   * Lista os nomes de todos os provedores registrados.
   *
   * @returns Array com os nomes canonicos dos provedores
   */
  getProviderNames(): ProviderName[] {
    return Array.from(this.providers.keys());
  }
}
