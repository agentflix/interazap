import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Bolha de mensagem com streaming — renderiza texto montado progressivamente com indicador de digitação.
 *
 * Contexto: utilizado no chat e no simulador para exibir respostas da IA conforme chegam via stream.
 */
@Component({
  selector: 'app-streaming-message-bubble',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './streaming-message-bubble.html',
})
export class StreamingMessageBubbleComponent {
  /** Texto completo ou acumulado a ser exibido. */
  readonly text = input<string>('');

  /** Indica se a mensagem é do usuário (vs. IA). */
  readonly isUser = input(false);

  /** Indica se o texto ainda está sendo transmitido via stream. */
  readonly isStreaming = input(false);

  /** Indica se o stream foi finalizado (último chunk recebido). */
  readonly isFinal = input(false);

  /** URL de áudio opcional para respostas por voz. */
  readonly audioUrl = input<string | null>(null);

  readonly displayText = computed(() => this.text() || '');
}
