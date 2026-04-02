import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';
import { TagsModalComponent } from './tags-modal';
import { TagService } from 'src/app/core/services/tag.service';

class TagServiceStub {
  all = vi.fn();
  create = vi.fn();
  attachToContact = vi.fn();
  detachFromContact = vi.fn();
}

describe('TagsModalComponent', (): void => {
  let component: TagsModalComponent;
  let tagService: TagServiceStub;

  beforeEach((): void => {
    vi.useFakeTimers();
    TestBed.configureTestingModule({
      imports: [TagsModalComponent],
      providers: [{ provide: TagService, useClass: TagServiceStub }],
    });

    tagService = TestBed.inject(TagService) as unknown as TagServiceStub;
    tagService.all.mockReturnValue(of({ success: true, data: { tags: [] } }));
    tagService.create.mockReturnValue(
      of({ success: true, data: { id: 'tag-1', name: 'VIP', color: '#000', is_active: true } }),
    );
    tagService.attachToContact.mockReturnValue(of({ success: true }));

    component = TestBed.createComponent(TagsModalComponent).componentInstance;
    component.contactId = 'contact-1';
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('creates tag on Enter and attaches to contact', async (): Promise<void> => {
    component.isOpen = true;
    component.tagInput.setValue('VIP');

    component.onInputKeydown(new KeyboardEvent('keydown', { key: 'Enter' }));
    await vi.runAllTimersAsync();

    expect(tagService.create).toHaveBeenCalled();
    expect(tagService.attachToContact).toHaveBeenCalledWith('contact-1', 'tag-1');
  });
});
