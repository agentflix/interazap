import { TestBed } from '@angular/core/testing';
import { MessageListComponent } from './message-list.component';

describe('MessageListComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [MessageListComponent],
    }).compileComponents();
  });

  it('should create', () => {
    const fixture = TestBed.createComponent(MessageListComponent);
    expect(fixture.componentInstance).toBeTruthy();
  });
});
