<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use Domain\Billing\Services\WebhookSignatureValidator;
use Illuminate\Http\Request;
use Tests\TestCase;

final class WebhookSignatureValidatorTest extends TestCase
{
    public function test_returns_true_when_token_matches_for_asaas(): void
    {
        config()->set('services.asaas.webhook_token', 'token-123');

        $request = Request::create('/webhook', 'POST');
        $request->headers->set('asaas-access-token', 'token-123');

        $validator = new WebhookSignatureValidator;

        $this->assertTrue($validator->validate($request, 'ASAAS'));
    }

    public function test_returns_false_when_token_does_not_match_for_asaas(): void
    {
        config()->set('services.asaas.webhook_token', 'token-123');

        $request = Request::create('/webhook', 'POST');
        $request->headers->set('asaas-access-token', 'invalid');

        $validator = new WebhookSignatureValidator;

        $this->assertFalse($validator->validate($request, 'asaas'));
    }

    public function test_returns_true_when_asaas_token_is_not_configured(): void
    {
        config()->set('services.asaas.webhook_token', null);

        $request = Request::create('/webhook', 'POST');
        $validator = new WebhookSignatureValidator;

        $this->assertTrue($validator->validate($request, 'asaas'));
    }
}
