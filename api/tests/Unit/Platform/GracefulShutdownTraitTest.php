<?php

declare(strict_types=1);

use Domain\Platform\Console\Traits\GracefulShutdownTrait;

describe('GracefulShutdownTrait', function (): void {
    beforeEach(function (): void {
        $this->traitObject = new class
        {
            use GracefulShutdownTrait;

            public bool $shutdownPerformed = false;

            // Override to prevent exit
            protected function performShutdown(): void
            {
                $this->shutdownPerformed = true;
            }

            // Expose protected methods for testing
            public function publicRegisterShutdownHandlers(): void
            {
                $this->registerShutdownHandlers();
            }

            public function publicMarkJobStart(): void
            {
                $this->markJobStart();
            }

            public function publicMarkJobComplete(): void
            {
                $this->markJobComplete();
            }

            public function publicShouldShutdown(): bool
            {
                return $this->shouldShutdown();
            }

            public function publicExecuteWithGracefulShutdown(callable $callback): mixed
            {
                return $this->executeWithGracefulShutdown($callback);
            }

            public function setShouldShutdown(bool $value): void
            {
                $this->shouldShutdown = $value;
            }

            public function getIsProcessing(): bool
            {
                return $this->isProcessing;
            }
        };
    });

    describe('initial state', function (): void {
        it('starts with shouldShutdown as false', function (): void {
            expect($this->traitObject->publicShouldShutdown())->toBeFalse();
        });

        it('starts with isProcessing as false', function (): void {
            expect($this->traitObject->getIsProcessing())->toBeFalse();
        });
    });

    describe('markJobStart', function (): void {
        it('sets isProcessing to true', function (): void {
            $this->traitObject->publicMarkJobStart();

            expect($this->traitObject->getIsProcessing())->toBeTrue();
        });
    });

    describe('markJobComplete', function (): void {
        it('sets isProcessing to false', function (): void {
            $this->traitObject->publicMarkJobStart();
            $this->traitObject->publicMarkJobComplete();

            expect($this->traitObject->getIsProcessing())->toBeFalse();
        });

        it('performs shutdown if shutdown was requested during processing', function (): void {
            $this->traitObject->publicMarkJobStart();
            $this->traitObject->setShouldShutdown(true);
            $this->traitObject->publicMarkJobComplete();

            expect($this->traitObject->shutdownPerformed)->toBeTrue();
        });

        it('does not perform shutdown if not requested', function (): void {
            $this->traitObject->publicMarkJobStart();
            $this->traitObject->publicMarkJobComplete();

            expect($this->traitObject->shutdownPerformed)->toBeFalse();
        });
    });

    describe('executeWithGracefulShutdown', function (): void {
        it('executes callback and returns result', function (): void {
            $result = $this->traitObject->publicExecuteWithGracefulShutdown(
                fn (): string => 'test-result'
            );

            expect($result)->toBe('test-result');
        });

        it('returns null if shutdown is requested before execution', function (): void {
            $this->traitObject->setShouldShutdown(true);

            $result = $this->traitObject->publicExecuteWithGracefulShutdown(
                fn (): string => 'should-not-run'
            );

            expect($result)->toBeNull();
        });

        it('marks job as processing during callback execution', function (): void {
            $wasProcessing = false;

            $this->traitObject->publicExecuteWithGracefulShutdown(
                function () use (&$wasProcessing): string {
                    $wasProcessing = $this->traitObject->getIsProcessing();

                    return 'done';
                }
            );

            expect($wasProcessing)->toBeTrue();
        });

        it('marks job as complete after callback execution', function (): void {
            $this->traitObject->publicExecuteWithGracefulShutdown(fn (): string => 'done');

            expect($this->traitObject->getIsProcessing())->toBeFalse();
        });

        it('marks job as complete even if callback throws', function (): void {
            try {
                $this->traitObject->publicExecuteWithGracefulShutdown(
                    fn () => throw new \Exception('Test error')
                );
            } catch (\Exception) {
                // Expected
            }

            expect($this->traitObject->getIsProcessing())->toBeFalse();
        });
    });

    describe('handleShutdownSignal', function (): void {
        it('sets shouldShutdown to true', function (): void {
            if (! defined('SIGTERM')) {
                $this->markTestSkipped('SIGTERM not defined');
            }

            $this->traitObject->handleShutdownSignal(SIGTERM);

            expect($this->traitObject->publicShouldShutdown())->toBeTrue();
        });

        it('performs immediate shutdown if not processing', function (): void {
            if (! defined('SIGTERM')) {
                $this->markTestSkipped('SIGTERM not defined');
            }

            $this->traitObject->handleShutdownSignal(SIGTERM);

            expect($this->traitObject->shutdownPerformed)->toBeTrue();
        });

        it('delays shutdown if currently processing', function (): void {
            if (! defined('SIGTERM')) {
                $this->markTestSkipped('SIGTERM not defined');
            }

            $this->traitObject->publicMarkJobStart();
            $this->traitObject->handleShutdownSignal(SIGTERM);

            expect($this->traitObject->shutdownPerformed)->toBeFalse();
            expect($this->traitObject->publicShouldShutdown())->toBeTrue();
        });
    });
});
