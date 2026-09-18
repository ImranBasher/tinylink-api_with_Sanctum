<?php

namespace Tests\Feature;

use App\Models\Url;
use App\Models\User;
use Database\Seeders\UrlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UrlApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_routes_require_authentication(): void
    {
        $this->postJson('/api/urls', ['url' => 'https://example.com'])->assertUnauthorized();
        $this->getJson('/api/urls')->assertUnauthorized();
        $this->getJson('/api/urls/1')->assertUnauthorized();
        $this->deleteJson('/api/urls/1')->assertUnauthorized();
        $this->getJson('/api/urls/1/stats')->assertUnauthorized();
    }

    public function test_creation_custom_codes_redirect_and_statistics(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/urls', ['url' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['url']]);

        $created = $this->postJson('/api/urls', [
            'url' => 'https://example.com/page',
            'custom_code' => 'my-link',
        ]);

        $created->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'URL shortened successfully.')
            ->assertJsonPath('data.short_code', 'my-link')
            ->assertJsonPath('data.click_count', 0);

        $id = $created->json('data.id');

        $this->postJson('/api/urls', [
            'url' => 'https://example.com/other',
            'custom_code' => 'my-link',
        ])->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['custom_code']]);

        $this->get('/my-link')
            ->assertRedirect('https://example.com/page');

        $this->get('/missing-code')
            ->assertNotFound()
            ->assertExactJson(['success' => false, 'message' => 'Not found.']);

        $this->getJson("/api/urls/{$id}/stats")
            ->assertOk()
            ->assertJsonPath('data.url', 'https://example.com/page')
            ->assertJsonPath('data.short_code', 'my-link')
            ->assertJsonPath('data.click_count', 1);

        $generated = $this->postJson('/api/urls', ['url' => 'https://example.org']);
        $generated->assertCreated();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{8}$/', $generated->json('data.short_code'));
    }

    public function test_listing_details_and_deletion_are_owner_only(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownedUrls = Url::factory()->count(3)->for($owner)->create();
        $otherUrl = Url::factory()->for($other)->create();
        Sanctum::actingAs($owner);

        $list = $this->getJson('/api/urls?page=1&per_page=2');
        $list->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 3)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonCount(2, 'data.urls');

        $this->assertNotContains($otherUrl->id, array_column($list->json('data.urls'), 'id'));

        $this->getJson("/api/urls/{$ownedUrls->first()->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $ownedUrls->first()->id);

        $this->getJson("/api/urls/{$otherUrl->id}")->assertForbidden()->assertJsonPath('success', false);
        $this->getJson("/api/urls/{$otherUrl->id}/stats")->assertForbidden()->assertJsonPath('success', false);
        $this->deleteJson("/api/urls/{$otherUrl->id}")->assertForbidden()->assertJsonPath('success', false);

        $this->deleteJson("/api/urls/{$ownedUrls->first()->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('urls', ['id' => $ownedUrls->first()->id]);
        $this->assertDatabaseHas('urls', ['id' => $otherUrl->id]);
    }

    public function test_sample_seeder_creates_users_with_urls(): void
    {
        $this->seed(UrlSeeder::class);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('urls', 6);
        $this->assertSame(3, User::query()->first()->urls()->count());
    }
}
