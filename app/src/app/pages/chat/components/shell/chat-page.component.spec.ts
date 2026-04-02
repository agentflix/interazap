import { TestBed } from '@angular/core/testing';
import { ChatPageComponent } from './chat-page.component';

describe('ChatPageComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [ChatPageComponent],
    }).compileComponents();
  });

  it('should create the chat shell page', () => {
    const fixture = TestBed.createComponent(ChatPageComponent);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });
});
