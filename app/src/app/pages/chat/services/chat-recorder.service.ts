import { Injectable, signal, type OnDestroy } from '@angular/core';
import { toast } from 'ngx-sonner';
import { Subject } from 'rxjs';
import { type RecordingState, type RecordedAudio } from '../models/chat-recorder.model';

// Re-export for backwards compatibility
export type { RecordingState, RecordedAudio } from '../models/chat-recorder.model';

@Injectable({ providedIn: 'root' })
export class ChatRecorderService implements OnDestroy {
  /**
   * Estado atual da gravação.
   */
  readonly state = signal<RecordingState>('text');

  /**
   * Duração atual da gravação em milissegundos.
   */
  readonly duration = signal(0);

  /**
   * Dados simulados de onda sonora para visualização.
   */
  readonly waveforms = signal<number[]>([]);

  /**
   * Stream de eventos quando uma gravação é finalizada com sucesso.
   */
  private readonly recordingCompletedSubject = new Subject<RecordedAudio>();
  readonly recordingCompleted$ = this.recordingCompletedSubject.asObservable();

  private mediaRecorder: MediaRecorder | null = null;
  private mediaStream: MediaStream | null = null;
  private recordedChunks: Blob[] = [];
  private recordingInterval: ReturnType<typeof setInterval> | null = null;
  private readonly MAX_RECORDING_TIME = 5 * 60 * 1000; // 5 minutos

  ngOnDestroy(): void {
    this.recordingCompletedSubject.complete();
  }

  /**
   * Inicia a gravação de áudio.
   * Solicita permissão ao usuário se necessário.
   */
  async start(): Promise<boolean> {
    if (this.state() !== 'text') return false;

    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      toast.error('Microfone indisponível neste navegador.');
      return false;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      this.mediaStream = stream;

      const mimeType = this.pickAudioMimeType();
      this.mediaRecorder = mimeType
        ? new MediaRecorder(stream, { mimeType })
        : new MediaRecorder(stream);

      this.recordedChunks = [];

      this.mediaRecorder.ondataavailable = (event: BlobEvent): void => {
        if (event.data && event.data.size > 0) {
          this.recordedChunks.push(event.data);
        }
      };

      this.mediaRecorder.onstop = (): void => {
        this.handleStop();
      };

      this.mediaRecorder.start();
      this.state.set('recording');
      this.duration.set(0);
      this.startTimer();

      return true;
    } catch {
      toast.error('Não foi possível acessar o microfone.');
      this.reset();
      return false;
    }
  }

  /**
   * Pausa a gravação atual.
   */
  pause(): void {
    if (this.state() !== 'recording' || !this.mediaRecorder) return;

    if (this.mediaRecorder.state === 'recording') {
      this.mediaRecorder.pause();
    }

    this.stopTimer();
    this.state.set('paused');
  }

  /**
   * Retoma a gravação pausada.
   */
  resume(): void {
    if (this.state() !== 'paused' || !this.mediaRecorder) return;

    if (this.mediaRecorder.state === 'paused') {
      this.mediaRecorder.resume();
    }

    this.startTimer();
    this.state.set('recording');
  }

  /**
   * Finaliza a gravação e processa o áudio.
   * O resultado será emitido no Observable `recordingCompleted$`.
   */
  stop(): void {
    if ((this.state() !== 'recording' && this.state() !== 'paused') || !this.mediaRecorder) return;

    if (this.mediaRecorder.state !== 'inactive') {
      this.mediaRecorder.stop();
    }
  }

  /**
   * Cancela a gravação atual e descarta os dados.
   */
  cancel(): void {
    this.reset();
  }

  /**
   * Verifica se o navegador suporta gravação.
   */
  isSupported(): boolean {
    return typeof navigator !== 'undefined' && !!navigator.mediaDevices?.getUserMedia;
  }

  private handleStop(): void {
    const mime = this.mediaRecorder?.mimeType || 'audio/webm';
    const blob = this.recordedChunks.length ? new Blob(this.recordedChunks, { type: mime }) : null;

    const finalDuration = this.duration();

    this.cleanup();
    this.resetState();

    if (blob) {
      this.recordingCompletedSubject.next({ blob, duration: finalDuration });
    }
  }

  private reset(): void {
    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
      // Remover listener para não disparar handleStop com blob válido se for cancelamento
      this.mediaRecorder.onstop = null;
      this.mediaRecorder.stop();
    }
    this.cleanup();
    this.resetState();
  }

  private cleanup(): void {
    if (this.mediaRecorder) {
      this.mediaRecorder.ondataavailable = null;
      this.mediaRecorder.onstop = null;
      this.mediaRecorder = null;
    }

    if (this.mediaStream) {
      this.mediaStream.getTracks().forEach((track) => track.stop());
      this.mediaStream = null;
    }

    this.recordedChunks = [];
    this.stopTimer();
  }

  private resetState(): void {
    this.state.set('text');
    this.duration.set(0);
    this.waveforms.set([]);
  }

  private startTimer(): void {
    this.stopTimer();
    this.recordingInterval = setInterval(() => {
      const current = this.duration();
      if (current >= this.MAX_RECORDING_TIME) {
        this.pause(); // Limite atingido
        return;
      }

      this.duration.set(current + 100);

      // Simula visualização de onda sonora
      const bars = Array.from({ length: 30 }, () => Math.random() * 100);
      this.waveforms.set(bars);
    }, 100);
  }

  private stopTimer(): void {
    if (this.recordingInterval) {
      clearInterval(this.recordingInterval);
      this.recordingInterval = null;
    }
  }

  private pickAudioMimeType(): string | null {
    const candidates = ['audio/ogg;codecs=opus', 'audio/webm;codecs=opus', 'audio/webm'];
    for (const candidate of candidates) {
      if (MediaRecorder.isTypeSupported(candidate)) {
        return candidate;
      }
    }
    return null;
  }
}
