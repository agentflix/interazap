import { type ComponentFixture, TestBed } from '@angular/core/testing';
import { provideHttpClient, withInterceptorsFromDi } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import ChatEvaluationComponent from './chat-evaluation';

describe('ChatEvaluationComponent', () => {
  let component: ChatEvaluationComponent;
  let fixture: ComponentFixture<ChatEvaluationComponent>;
  let httpMock: HttpTestingController;

  const mockShowResponse = {
    data: { submitted: false, token: 'test-token' },
  };

  const mockSubmitResponse = {
    data: { message: 'Avaliação registrada com sucesso.' },
  };

  function configureTestingModule(routeParams: { token: string }) {
    TestBed.configureTestingModule({
      imports: [ChatEvaluationComponent],
      providers: [
        provideHttpClient(withInterceptorsFromDi()),
        provideHttpClientTesting(),
        {
          provide: ActivatedRoute,
          useValue: {
            paramMap: of(new Map(Object.entries(routeParams))),
          },
        },
      ],
    });

    fixture = TestBed.createComponent(ChatEvaluationComponent);
    component = fixture.componentInstance;
    httpMock = TestBed.inject(HttpTestingController);
  }

  describe('initial state', () => {
    beforeEach(() => configureTestingModule({ token: 'test-token' }));

    it('should create', () => {
      expect(component).toBeTruthy();
    });

    it('should start in loading state', () => {
      expect(component.isLoading()).toBe(true);
    });

    it('should not show notFound initially', () => {
      expect(component.notFound()).toBe(false);
    });

    it('should not show alreadySubmitted initially', () => {
      expect(component.alreadySubmitted()).toBe(false);
    });

    it('should not show submitted initially', () => {
      expect(component.submitted()).toBe(false);
    });

    it('should have selectedRating of 0', () => {
      expect(component.selectedRating()).toBe(0);
    });
  });

  describe('loading evaluation', () => {
    it('should set isLoading to false and show form on successful load', () => {
      configureTestingModule({ token: 'test-token' });
      fixture.detectChanges();

      const req = httpMock.expectOne('/api/public/chat/evaluations/test-token');
      expect(req.request.method).toBe('GET');
      req.flush(mockShowResponse);

      fixture.detectChanges();
      expect(component.isLoading()).toBe(false);
      expect(component.notFound()).toBe(false);
      expect(component.alreadySubmitted()).toBe(false);
    });

    it('should set alreadySubmitted when evaluation was already submitted', () => {
      configureTestingModule({ token: 'test-token' });
      fixture.detectChanges();

      const req = httpMock.expectOne('/api/public/chat/evaluations/test-token');
      req.flush({ data: { submitted: true } });

      fixture.detectChanges();
      expect(component.isLoading()).toBe(false);
      expect(component.alreadySubmitted()).toBe(true);
    });

    it('should set notFound on error', () => {
      configureTestingModule({ token: 'test-token' });
      fixture.detectChanges();

      const req = httpMock.expectOne('/api/public/chat/evaluations/test-token');
      req.error(new ProgressEvent('error'), { status: 404, statusText: 'Not Found' });

      fixture.detectChanges();
      expect(component.isLoading()).toBe(false);
      expect(component.notFound()).toBe(true);
    });
  });

  describe('selectRating', () => {
    beforeEach(() => configureTestingModule({ token: 'test-token' }));

    it('should update selectedRating', () => {
      component.selectRating(3);
      expect(component.selectedRating()).toBe(3);
    });

    it('should clear ratingError when selecting', () => {
      (component as any).ratingError.set('Select a rating');
      component.selectRating(4);
      expect(component.ratingError()).toBeNull();
    });
  });

  describe('submitEvaluation', () => {
    beforeEach(() => configureTestingModule({ token: 'test-token' }));

    it('should set ratingError if no rating selected', () => {
      component.submitEvaluation();
      expect(component.ratingError()).toBeTruthy();
    });

    it('should submit evaluation with correct payload', () => {
      // Flush the initial GET request from the constructor
      httpMock.expectOne('/api/public/chat/evaluations/test-token').flush(mockShowResponse);

      component.selectRating(5);
      (component as any).comment.set('Ótimo atendimento!');
      component.submitEvaluation();

      const req = httpMock.expectOne('/api/public/chat/evaluations/test-token');
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual({
        rating: 5,
        comment: 'Ótimo atendimento!',
      });
      req.flush(mockSubmitResponse);

      fixture.detectChanges();
      expect(component.isSubmitting()).toBe(false);
      expect(component.submitted()).toBe(true);
    });

    it('should set errorMessage on submit failure', () => {
      // Flush the initial GET request from the constructor
      httpMock.expectOne('/api/public/chat/evaluations/test-token').flush(mockShowResponse);

      component.selectRating(4);
      component.submitEvaluation();

      const req = httpMock.expectOne('/api/public/chat/evaluations/test-token');
      req.error(new ProgressEvent('error'), { status: 500, statusText: 'Server Error' });

      fixture.detectChanges();
      expect(component.isSubmitting()).toBe(false);
      expect(component.errorMessage()).toBeTruthy();
    });

    it('should trim empty comment to null', () => {
      // Flush the initial GET request from the constructor
      httpMock.expectOne('/api/public/chat/evaluations/test-token').flush(mockShowResponse);

      component.selectRating(3);
      (component as any).comment.set('   ');
      component.submitEvaluation();

      const req = httpMock.expectOne('/api/public/chat/evaluations/test-token');
      expect(req.request.body.comment).toBeNull();
      req.flush(mockSubmitResponse);
    });
  });

  describe('accessibility', () => {
    beforeEach(() => configureTestingModule({ token: 'test-token' }));

    it('should have starIndexes array of length 5', () => {
      expect(component.starIndexes).toEqual([1, 2, 3, 4, 5]);
    });
  });
});
