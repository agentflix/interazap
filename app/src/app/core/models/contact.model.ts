import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * Representa um contato do CRM.
 *
 * Contexto: entidade central do módulo CRM. Contatos são vinculados a tickets,
 * negociações e empresas. Podem ser importados via CSV ou criados manualmente.
 */
export interface Contact {
  /** Identificador único do contato. */
  id: string | number;
  /** Nome completo do contato. */
  name: string;
  /** Número de telefone principal. */
  phone?: string;
  /** Número de WhatsApp. */
  whatsapp?: string;
  /** Endereço de e-mail. */
  email?: string;
  /** URL do avatar do contato (alias legado). */
  avatar?: string;
  /** URL do avatar do contato. */
  avatar_url?: string | null;
  /** Jabber ID para mensagens. */
  jid?: string;
  /** Line ID para mensagens. */
  lid?: string;
  /** Documento fiscal (CPF/CNPJ). */
  document?: string;
  /** Origem do cadastro do contato. */
  source?: string;
  /** Indica se o contato está ativo. */
  is_active: boolean;
  /** ID da empresa (tenant) ao qual o contato pertence. */
  company_id?: string;
  /** ID da empresa CRM vinculada ao contato. */
  crm_company_id?: string;
  /** Dados da empresa CRM vinculada. */
  company?: {
    id: string;
    name: string;
    document?: string;
  };
  /** Campos personalizados dinâmicos (chave-valor). */
  custom_fields?: Record<string, unknown>;
  /** Notas internas sobre o contato. */
  notes?: string;
  /** Data do último contato realizado. */
  last_contact_at?: string;
  /** Etiquetas associadas ao contato. */
  tags?: { id: string; name: string }[] | string[];
  /** Quantidade de tickets abertos com este contato. */
  calleds_count?: number;
  /** Data de criação do registro. */
  created_at: string;
  /** Data da última atualização do registro. */
  updated_at: string;
}

/**
 * Filtros para listagem de contatos.
 *
 * Contexto: parâmetros aceitos pelo endpoint de listagem de contatos do CRM.
 */
export interface ContactFilters {
  search?: string;
  is_active?: boolean;
  tag?: string;
  crm_company_id?: string;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

/**
 * Resposta paginada da API para listagem de contatos.
 */
export interface ContactListResponse extends PaginatedResponse<Contact> {
  /** Indicador de sucesso da requisição. */
  success: boolean;
}

/**
 * Resposta da API para um único contato.
 */
export interface ContactResponse {
  /** Indicador de sucesso da requisição. */
  success: boolean;
  /** Mensagem descritiva retornada pela API. */
  message: string;
  /** Dados do contato. */
  data: Contact;
}

/**
 * Resposta do upload de arquivo CSV para importação de contatos.
 *
 * Contém metadados sobre a estrutura do arquivo para prévia antes da confirmação.
 */
export interface ContactImportUploadResponse {
  data: {
    /** ID temporário da importação para confirmar na etapa seguinte. */
    import_id: string;
    /** Cabeçalhos detectados no arquivo CSV. */
    headers: string[];
    /** Amostra das primeiras linhas do arquivo. */
    sample: string[][];
    /** Delimitador detectado no arquivo CSV. */
    delimiter: ',' | ';';
    /** Indica se o arquivo possui linha de cabeçalho. */
    has_header: boolean;
  };
}

/**
 * Resumo do resultado de uma operação de importação de contatos.
 *
 * Contexto: retornado após a confirmação da importação CSV,
 * com contagem de registros processados, importados, ignorados e com falha.
 */
export interface ContactImportSummary {
  processed: number;
  imported: number;
  skipped: number;
  failed: number;
  /** Lista de erros por linha do arquivo. */
  errors?: { line?: number; message: string }[];
  /** Indica se a importação foi enfileirada para processamento assíncrono. */
  queued?: boolean;
  rows?: number;
}

/**
 * Resposta da API após conclusão de uma operação de importação de contatos.
 */
export interface ContactImportResponse {
  data: ContactImportSummary;
}
