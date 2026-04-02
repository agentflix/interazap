import { TestBed } from '@angular/core/testing';
import { TicketListPanelComponent } from './ticket-list-panel.component';

describe('TicketListPanelComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [TicketListPanelComponent],
    }).compileComponents();
  });

  it('should create the component', () => {
    const fixture = TestBed.createComponent(TicketListPanelComponent);
    const component = fixture.componentInstance;
    expect(component).toBeTruthy();
  });
});
