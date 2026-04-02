import { TestBed } from '@angular/core/testing';
import { DetailPanelComponent } from './detail-panel.component';

describe('DetailPanelComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [DetailPanelComponent],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(DetailPanelComponent);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
