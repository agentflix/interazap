<?php

declare(strict_types=1);

use Domain\Platform\Console\Listeners\WorkerGracefulShutdownListener;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;

beforeEach(function (): void {
    WorkerGracefulShutdownListener::reset();
});

describe('WorkerGracefulShutdownListener', function (): void {
    describe('state management', function (): void {
        it('starts with shutdown not requested', function (): void {
            expect(WorkerGracefulShutdownListener::isShutdownRequested())->toBeFalse();
        });

        it('starts with no job processing', function (): void {
            expect(WorkerGracefulShutdownListener::isProcessing())->toBeFalse();
        });

        it('can request shutdown', function (): void {
            WorkerGracefulShutdownListener::requestShutdown();

            expect(WorkerGracefulShutdownListener::isShutdownRequested())->toBeTrue();
        });

        it('can reset state', function (): void {
            WorkerGracefulShutdownListener::requestShutdown();

            WorkerGracefulShutdownListener::reset();

            expect(WorkerGracefulShutdownListener::isShutdownRequested())->toBeFalse();
            expect(WorkerGracefulShutdownListener::isProcessing())->toBeFalse();
        });
    });

    describe('handleJobProcessing', function (): void {
        it('marks job as processing', function (): void {
            $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $job->shouldReceive('resolveName')->andReturn('TestJob');
            $job->shouldReceive('getQueue')->andReturn('default');

            $event = new JobProcessing('redis', $job);

            $listener = new WorkerGracefulShutdownListener;
            $listener->handleJobProcessing($event);

            expect(WorkerGracefulShutdownListener::isProcessing())->toBeTrue();
        });
    });

    describe('handleJobProcessed', function (): void {
        it('marks job as not processing', function (): void {
            $job = Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
            $job->shouldReceive('resolveName')->andReturn('TestJob');
            $job->shouldReceive('getQueue')->andReturn('default');

            $processingEvent = new JobProcessing('redis', $job);
            $processedEvent = new JobProcessed('redis', $job);

            $listener = new WorkerGracefulShutdownListener;
            $listener->handleJobProcessing($processingEvent);
            $listener->handleJobProcessed($processedEvent);

            expect(WorkerGracefulShutdownListener::isProcessing())->toBeFalse();
        });
    });

    describe('handleLooping', function (): void {
        it('does not stop worker when shutdown not requested', function (): void {
            $event = new Looping('redis', 'default');

            $listener = new WorkerGracefulShutdownListener;
            $listener->handleLooping($event);

            // Worker should continue (no shouldQuit set)
            expect(WorkerGracefulShutdownListener::isShutdownRequested())->toBeFalse();
        });
    });

    describe('handleWorkerStopping', function (): void {
        it('handles worker stopping event', function (): void {
            $event = new WorkerStopping(0);

            $listener = new WorkerGracefulShutdownListener;

            // Should not throw
            $listener->handleWorkerStopping($event);

            expect(true)->toBeTrue();
        });
    });
});
