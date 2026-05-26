import { type OnInit, Component, ChangeDetectionStrategy, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LucideAngularModule } from 'lucide-angular';
import { ElectronService } from '../../../core/services/electron.service';

/**
 * Barra de título com controles de janela (minimizar, maximizar, fechar).
 * Usada em ambientes Electron para controle da janela da aplicação.
 */
@Component({
  selector: 'app-title-bar',
  standalone: true,
  imports: [CommonModule, LucideAngularModule],
  templateUrl: './title-bar.component.html',
  styleUrl: './title-bar.component.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class TitleBarComponent implements OnInit {
  private readonly electronService = inject(ElectronService);

  isMaximized = signal(false);

  ngOnInit(): void {
    if (this.electronService.isElectron) {
      void this.electronService
        .isMaximized()
        .then((max) => this.isMaximized.set(max))
        .catch(() => this.isMaximized.set(false));

      this.electronService.onMaximizedChange((maximized) => {
        this.isMaximized.set(maximized);
      });
    }
  }

  minimize(): void {
    this.electronService.minimizeWindow();
  }

  toggleMaximize(): void {
    this.electronService.maximizeWindow();
  }

  close(): void {
    this.electronService.closeWindow();
  }
}
