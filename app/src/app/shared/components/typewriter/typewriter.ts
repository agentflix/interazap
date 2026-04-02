import {
  type OnInit,
  type OnDestroy,
  Component,
  ChangeDetectionStrategy,
  input,
  signal,
} from '@angular/core';

/**
 * AfTypewriterComponent — Typewriter text animation.
 *
 * @example
 * ```html
 * <af-typewriter [texts]="['Hello World', 'Welcome to AgentFlix']" [speed]="80" />
 * ```
 */
@Component({
  selector: 'af-typewriter',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './typewriter.html',
})
export class AfTypewriterComponent implements OnInit, OnDestroy {
  /** Array of texts to cycle through */
  readonly texts = input<string[]>(['']);

  /** Typing speed in ms per character */
  readonly speed = input(80);

  /** Pause between texts in ms */
  readonly pauseDuration = input(1500);

  /** Loop animation */
  readonly loop = input(true);

  protected readonly displayText = signal('');

  private timeoutId: ReturnType<typeof setTimeout> | null = null;
  private currentTextIndex = 0;
  private currentCharIndex = 0;
  private isDeleting = false;

  ngOnInit(): void {
    this.animate();
  }

  ngOnDestroy(): void {
    if (this.timeoutId) clearTimeout(this.timeoutId);
  }

  private animate(): void {
    const allTexts = this.texts();
    if (allTexts.length === 0) return;

    const currentText = allTexts[this.currentTextIndex];
    const speed = this.speed();

    if (!this.isDeleting) {
      this.displayText.set(currentText.slice(0, this.currentCharIndex + 1));
      this.currentCharIndex++;

      if (this.currentCharIndex === currentText.length) {
        if (!this.loop() && this.currentTextIndex === allTexts.length - 1) return;
        this.timeoutId = setTimeout(() => {
          this.isDeleting = true;
          this.animate();
        }, this.pauseDuration());
        return;
      }
    } else {
      this.displayText.set(currentText.slice(0, this.currentCharIndex - 1));
      this.currentCharIndex--;

      if (this.currentCharIndex === 0) {
        this.isDeleting = false;
        this.currentTextIndex = (this.currentTextIndex + 1) % allTexts.length;
      }
    }

    this.timeoutId = setTimeout(() => this.animate(), this.isDeleting ? speed / 2 : speed);
  }
}
