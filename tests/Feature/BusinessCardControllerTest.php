<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTestImage;
use Tests\TestCase;

class BusinessCardControllerTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTestImage;

    public function test_analyze_requires_at_least_one_image(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('cards.analyze'));

        $response->assertStatus(302);
        $response->assertSessionHasErrors('analyze');
        $this->assertNull(session('analysis'));
    }

    public function test_analyze_returns_error_when_openai_key_is_missing(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => null]);
        Http::fake();

        $response = $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => $this->createTestImage('front.png'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('analyze');
        Http::assertNothingSent();
    }

    public function test_push_to_notion_builds_properties_from_analysis_and_skips_unmapped_fields(): void
    {
        $user = $this->createUser();
        config([
            'services.notion.api_key' => 'test-key',
            'services.notion.data_source_id' => 'test-database',
            'services.notion.version' => '2025-09-03',
        ]);

        $analysis = [
            'name' => '山田 太郎',
            'company' => 'ACME Inc.',
            'job_title' => null,
            'custom_field' => 'should be ignored',
        ];

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::response(['id' => 'page_123'], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['analysis' => $analysis])
            ->post(route('cards.notion'), [
                'job_title' => ' CTO ',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'Notionへの登録が完了しました');

        Http::assertSent(function ($request) {
            $data = $request->data();
            $properties = $data['properties'] ?? [];

            return $request->url() === 'https://api.notion.com/v1/pages'
                && ($data['parent']['data_source_id'] ?? null) === 'test-database'
                && ($properties['名前']['title'][0]['text']['content'] ?? null) === '山田 太郎'
                && ($properties['会社名']['rich_text'][0]['text']['content'] ?? null) === 'ACME Inc.'
                && ($properties['役職']['rich_text'][0]['text']['content'] ?? null) === 'CTO'
                && ! array_key_exists('custom_field', $properties);
        });
    }

    private function createUser(): User
    {
        return User::create([
            'username' => 'user_'.Str::random(8),
            'password' => Hash::make('password'),
            'encrypted_password' => 'encrypted_'.Str::random(32),
        ]);
    }
}
