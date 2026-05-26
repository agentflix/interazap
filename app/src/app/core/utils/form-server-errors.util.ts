import type { FormGroup } from '@angular/forms';

/**
 * Distribui erros de campo do Laravel 422 nos FormControls correspondentes.
 * Define `{ server: '<mensagem>' }` como erro do controle e marca como tocado
 * para que os componentes de erro renderizem imediatamente.
 *
 * @param form Formulário reativo que receberá os erros
 * @param errors Mapa de campo para mensagem(ns) de erro retornado pela API
 */
export function applyServerErrors(
  form: FormGroup,
  errors: Record<string, string | string[]>,
): void {
  Object.entries(errors).forEach(([field, messages]) => {
    const control = form.get(field);
    if (!control) return;
    const message = Array.isArray(messages) ? messages[0] : messages;
    control.setErrors({ server: message });
    control.markAsTouched();
  });
}
