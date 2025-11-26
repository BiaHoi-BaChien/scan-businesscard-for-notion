<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    public function test_analyze_normalizes_japanese_keys_from_openai_response(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode([
                            '名前' => '山田 太郎',
                            '役職' => 'CTO',
                        ]),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => $this->createTestImage('front.png'),
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertSame('山田 太郎', session('analysis.name'));
        $this->assertSame('CTO', session('analysis.job_title'));
    }

    public function test_analyze_clears_analysis_session_when_openai_returns_server_error(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);
        Http::fake([
            'https://api.openai.com/*' => Http::response([], 500),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['analysis' => ['name' => '旧データ']])
            ->post(route('cards.analyze'), [
                'front' => $this->createTestImage('front.png'),
            ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('analyze');
        $this->assertNull(session('analysis'));
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

    public function test_push_to_notion_trims_input_overrides_before_sending_request(): void
    {
        $user = $this->createUser();
        config([
            'services.notion.api_key' => 'test-key',
            'services.notion.data_source_id' => 'test-database',
            'services.notion.version' => '2025-09-03',
        ]);

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::response(['id' => 'page_123'], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['analysis' => ['name' => '山田 太郎', 'job_title' => 'Developer']])
            ->post(route('cards.notion'), [
                'job_title' => ' CTO ',
            ]);

        $response->assertStatus(302);
        $this->assertSame('CTO', session('analysis.job_title'));

        Http::assertSent(function ($request) {
            $properties = $request->data()['properties'] ?? [];

            return $request->url() === 'https://api.notion.com/v1/pages'
                && ($properties['役職']['rich_text'][0]['text']['content'] ?? null) === 'CTO';
        });
    }

    public function test_push_to_notion_requires_configuration(): void
    {
        $user = $this->createUser();

        $this->withoutExceptionHandling();

        Http::fake();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Notionの設定が不足しています');

        try {
            $this->actingAs($user)
                ->withSession(['analysis' => ['name' => '山田 太郎']])
                ->post(route('cards.notion'));
        } finally {
            Http::assertNothingSent();
        }
    }

    private function createUser(): User
    {
        $secret = env('AUTH_SECRET', config('app.key')) ?: 'test-secret';

        return User::create([
            'username' => 'user_'.Str::random(8),
            'password' => Hash::make('password'),
            'encrypted_password' => base64_encode(openssl_encrypt(
                'password',
                'AES-256-CBC',
                hash('sha256', $secret),
                0,
                substr(hash('sha256', $secret), 0, 16)
            )),
        ]);
    }
}
