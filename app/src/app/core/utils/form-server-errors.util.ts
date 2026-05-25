import type { FormGroup } from '@angular/forms';

/**
 * Distributes Laravel 422 field-level errors to their corresponding FormControls.
 * Sets `{ server: '<message>' }` as the control error and marks the control as touched
 * so error components render immediately.
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
