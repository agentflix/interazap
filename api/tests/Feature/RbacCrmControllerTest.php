<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMContact;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class RbacCrmControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_contact_show_returns_404_for_cross_tenant_access(): void
    {
        $user = AuthUser::factory()->create();
        $contact = CRMContact::factory()->create();

        Sanctum::actingAs($user, abilities: []);

        $this->getJson('/api/crm/contacts/'.$contact->id)->assertStatus(404);
    }

    public function test_contacts_index_returns_200_for_tenant_user(): void
    {
        $user = AuthUser::factory()->create();

        Sanctum::actingAs($user, abilities: []);

        $this->getJson('/api/crm/contacts')->assertStatus(200);
    }

    public function test_products_all_returns_200_for_tenant_user(): void
    {
        $user = AuthUser::factory()->create();

        Sanctum::actingAs($user, abilities: []);

        $this->getJson('/api/crm/products-all')->assertStatus(200);
    }

    public function test_products_all_returns_401_for_unauthenticated(): void
    {
        $this->getJson('/api/crm/products-all')->assertStatus(401);
    }
}
