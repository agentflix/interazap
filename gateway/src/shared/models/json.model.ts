/**
 * Modelos de utilitários JSON do gateway.
 * Define tipos auxiliares para manipulação segura de objetos JSON.
 */

/**
 * Representa um objeto JSON genérico com chaves string e valores desconhecidos.
 * Utilizado para tipagem segura de payloads dinâmicos.
 */
export type JsonRecord = Record<string, unknown>;
