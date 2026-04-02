import { TestBed } from '@angular/core/testing';
import { ConversationPanelComponent } from './conversation-panel.component';

describe('ConversationPanelComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ConversationPanelComponent],
    }).compileComponents();
  });

  it('should create the component', () => {
    const fixture = TestBed.createComponent(ConversationPanelComponent);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });
});
