<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CreatesTestImage;
use Tests\TestCase;

class BusinessCardControllerTest extends TestCase
{
    use CreatesTestImage;
    use RefreshDatabase;

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

    public function test_analyze_accepts_uppercase_image_extensions(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['name' => '山田 太郎']),
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => $this->createTestImage('front.PNG'),
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame('山田 太郎', session('analysis.name'));
    }

    public function test_analyze_rejects_disallowed_extensions(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);
        Http::fake();

        $response = $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => $this->createTestImage('front.gif'),
        ]);

        $response->assertSessionHasErrors('front');
    }

    public function test_analyze_handles_non_file_input_without_crashing(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => 'not-a-file',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('front');
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

    public function test_analyze_returns_friendly_message_when_openai_rate_limited(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'error' => [
                    'message' => 'Rate limit reached for requests per min',
                ],
            ], 429, ['Retry-After' => '12']),
        ]);

        $response = $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => $this->createTestImage('front.png'),
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors([
            'analyze' => '解析リクエストが集中しています（429）。しばらく待ってから再実行してください。 目安: 12秒 後に再試行。 詳細: Rate limit reached for requests per min',
        ]);
    }

    public function test_analyze_rate_limit_prevents_unbounded_openai_requests(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);

        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => json_encode(['name' => '山田 太郎'])],
                ]],
            ], 200),
        ]);

        foreach (range(1, 3) as $attempt) {
            $this->actingAs($user)->post(route('cards.analyze'), [
                'front' => $this->createTestImage("front-{$attempt}.png"),
            ])->assertRedirect(route('dashboard'));
        }

        $this->actingAs($user)->post(route('cards.analyze'), [
            'front' => $this->createTestImage('front-rate-limited.png'),
        ])->assertTooManyRequests();

        Http::assertSentCount(3);
    }

    public function test_analyze_rejects_a_concurrent_request_without_calling_openai(): void
    {
        $user = $this->createUser();
        config(['services.openai.api_key' => 'test-key']);
        Http::fake();

        $lock = Cache::lock('openai-analysis:'.$user->getAuthIdentifier(), 60);
        $this->assertTrue($lock->get());

        try {
            $this->actingAs($user)->post(route('cards.analyze'), [
                'front' => $this->createTestImage('front.png'),
            ])->assertSessionHasErrors('analyze');
        } finally {
            $lock->release();
        }

        Http::assertNothingSent();
    }

    public function test_push_to_notion_builds_properties_from_analysis_and_skips_unmapped_fields(): void
    {
        $user = $this->createUser();
        config([
            'services.notion.api_key' => 'test-key',
            'services.notion.data_source_id' => 'test-data-source',
            'services.notion.version' => '2026-03-11',
        ]);

        $analysis = [
            'name' => '山田 太郎',
            'company' => 'ACME Inc.',
            'job_title' => null,
            'custom_field' => 'should be ignored',
        ];

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::response([
                'id' => 'page_123',
                'url' => 'https://www.notion.so/page_123',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'analysis' => $analysis,
                'analysis_submission_id' => 'submission-123',
            ])
            ->post(route('cards.notion'), [
                'job_title' => ' CTO ',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status', 'Notionへの登録が完了しました');
        $response->assertSessionHas('notion_url', 'https://www.notion.so/page_123');

        Http::assertSent(function ($request) {
            $data = $request->data();
            $properties = $data['properties'] ?? [];

            return $request->url() === 'https://api.notion.com/v1/pages'
                && $request->hasHeader('Notion-Version', '2026-03-11')
                && ($data['parent']['type'] ?? null) === 'data_source_id'
                && ($data['parent']['data_source_id'] ?? null) === 'test-data-source'
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
            'services.notion.data_source_id' => 'test-data-source',
            'services.notion.version' => '2026-03-11',
        ]);

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::response([
                'id' => 'page_123',
                'url' => 'https://www.notion.so/page_123',
            ], 200),
        ]);

        $response = $this->actingAs($user)
            ->withSession([
                'analysis' => ['name' => '山田 太郎', 'job_title' => 'Developer'],
                'analysis_submission_id' => 'submission-123',
            ])
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

    public function test_push_to_notion_creates_only_one_page_for_the_same_analysis(): void
    {
        $user = $this->createUser();
        config([
            'services.notion.api_key' => 'test-key',
            'services.notion.data_source_id' => 'test-data-source',
            'services.notion.version' => '2026-03-11',
        ]);

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::response([
                'id' => 'page_123',
                'url' => 'https://www.notion.so/page_123',
            ], 200),
        ]);

        $session = [
            'analysis' => ['name' => '山田 太郎'],
            'analysis_submission_id' => 'submission-123',
        ];

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('cards.notion'))
            ->assertSessionHas('notion_url', 'https://www.notion.so/page_123');

        Cache::flush();

        $this->actingAs($user)
            ->post(route('cards.notion'))
            ->assertSessionHas('notion_url', 'https://www.notion.so/page_123');

        Http::assertSentCount(1);
    }

    public function test_push_to_notion_can_retry_after_an_explicit_api_failure(): void
    {
        $user = $this->createUser();
        config([
            'services.notion.api_key' => 'test-key',
            'services.notion.data_source_id' => 'test-data-source',
            'services.notion.version' => '2026-03-11',
        ]);

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::sequence()
                ->push([], 500)
                ->push([
                    'id' => 'page_123',
                    'url' => 'https://www.notion.so/page_123',
                ], 200),
        ]);

        $session = [
            'analysis' => ['name' => '山田 太郎'],
            'analysis_submission_id' => 'submission-123',
        ];

        $this->actingAs($user)
            ->withSession($session)
            ->post(route('cards.notion'))
            ->assertSessionHasErrors('notion');

        $this->actingAs($user)
            ->post(route('cards.notion'))
            ->assertSessionHas('notion_url', 'https://www.notion.so/page_123');

        Http::assertSentCount(2);
    }

    public function test_push_to_notion_rate_limit_prevents_unbounded_page_creation(): void
    {
        $user = $this->createUser();
        config([
            'services.notion.api_key' => 'test-key',
            'services.notion.data_source_id' => 'test-data-source',
            'services.notion.version' => '2026-03-11',
        ]);

        Http::fake([
            'https://api.notion.com/v1/pages' => Http::response([
                'id' => 'page_123',
                'url' => 'https://www.notion.so/page_123',
            ], 200),
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)
                ->withSession([
                    'analysis' => ['name' => '山田 太郎'],
                    'analysis_submission_id' => "submission-{$attempt}",
                ])
                ->post(route('cards.notion'))
                ->assertRedirect();
        }

        $this->actingAs($user)
            ->withSession([
                'analysis' => ['name' => '山田 太郎'],
                'analysis_submission_id' => 'submission-rate-limited',
            ])
            ->post(route('cards.notion'))
            ->assertTooManyRequests();

        Http::assertSentCount(5);
    }

    public function test_push_to_notion_requires_configuration(): void
    {
        $user = $this->createUser();

        $this->withoutExceptionHandling();

        config([
            'services.notion.api_key' => null,
            'services.notion.data_source_id' => null,
        ]);

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
        return User::create([
            'username' => 'user_'.Str::random(8),
            'password' => Hash::make('password'),
        ]);
    }
}
