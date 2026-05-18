import { TestBed } from '@angular/core/testing';
import { MessageBubbleComponent } from './message-bubble.component';

describe('MessageBubbleComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MessageBubbleComponent],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(MessageBubbleComponent);
    expect(fixture.componentInstance).toBeTruthy();
  });

  it('should apply dedicated tokens for internal messages', () => {
    const fixture = TestBed.createComponent(MessageBubbleComponent);
    fixture.componentRef.setInput('isInternal', true);
    fixture.detectChanges();

    const article = fixture.nativeElement.querySelector('article') as HTMLElement;
    expect(article.className).toContain('bg-neutral-800');
    expect(article.className).toContain('border-neutral-700');
  });

  it('should preserve incoming and outgoing styles', () => {
    const incomingFixture = TestBed.createComponent(MessageBubbleComponent);
    incomingFixture.componentRef.setInput('direction', 'incoming');
    incomingFixture.detectChanges();
    const incomingArticle = incomingFixture.nativeElement.querySelector('article') as HTMLElement;
    expect(incomingArticle.className).toContain('bg-neutral-100');

    const outgoingFixture = TestBed.createComponent(MessageBubbleComponent);
    outgoingFixture.componentRef.setInput('direction', 'outgoing');
    outgoingFixture.detectChanges();
    const outgoingArticle = outgoingFixture.nativeElement.querySelector('article') as HTMLElement;
    expect(outgoingArticle.className).toContain('bg-primary-500');
  });
});
