import { TestBed } from '@angular/core/testing';
import { MessageComposerComponent } from './message-composer.component';

describe('MessageComposerComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MessageComposerComponent],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(MessageComposerComponent);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
