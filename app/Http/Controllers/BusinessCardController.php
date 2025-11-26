<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BusinessCardController extends Controller
{
    // 画像を保存せず、そのままOpenAIに渡して解析する
    public function analyze(Request $request)
    {
        $request->validate([
            'front' => 'nullable|image|max:4096',
            'back' => 'nullable|image|max:4096',
        ]);

        if (! $request->file('front') && ! $request->file('back')) {
            return back()->withErrors(['analyze' => '表面または裏面の画像を選択してください'])->withInput();
        }

        $images = array_filter([
            $request->file('front')?->getRealPath(),
            $request->file('back')?->getRealPath(),
        ]);

        $prompt = '名刺画像から以下をJSONで回答してください（省略可の値は null を許容）。'
            .' {"name":string,"job_title":string|null,"company":string|null,"address":string|null,"website":string|null,"email":string|null,"phone_number_1":string|null,"phone_number_2":string|null,"industry":string|null}';
        $apiKey = config('services.openai.api_key');

        if (! $apiKey) {
            return back()->withErrors(['analyze' => 'OpenAI APIキーが未設定です']);
        }

        $analysis = [
            'name' => null,
            'job_title' => null,
            'company' => null,
            'address' => null,
            'website' => null,
            'email' => null,
            'phone_number_1' => null,
            'phone_number_2' => null,
            'industry' => null,
        ];

        $encodedImages = array_map(fn ($path) => base64_encode(file_get_contents($path)), $images);
        $response = Http::withToken($apiKey)->post('https://api.openai.com/v1/chat/completions', [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Extract business card fields and answer in JSON.'],
                ['role' => 'user', 'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    ...array_map(fn ($img) => ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,'.$img]], $encodedImages),
                ]],
            ],
            'response_format' => ['type' => 'json_object'],
        ]);

        if (! $response->ok()) {
            return back()->withErrors(['analyze' => '解析に失敗しました: '.$response->status()]);
        }

        $content = $response->json('choices.0.message.content');
        if (config('app.debug')) {
            Log::debug('openai.completions.response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        $decoded = is_string($content) ? json_decode($content, true) : $content;

        if (! is_array($decoded)) {
            return back()->withErrors(['analyze' => '解析結果の形式が不正です']);
        }

        $normalized = [];
        $aliases = [
            'name' => ['氏名', '名前', 'name'],
            'job_title' => ['役職', 'title', 'job_title'],
            'company' => ['会社名', 'company'],
            'address' => ['住所', 'address'],
            'website' => ['会社ホームページURL', '会社サイトURL', 'website'],
            'email' => ['メールアドレス', 'email'],
            'phone_number_1' => ['電話番号1', 'phone', 'mobile', 'phone_number_1'],
            'phone_number_2' => ['電話番号2', 'phone_number_2'],
            'industry' => ['業種', 'industry'],
        ];

        foreach ($aliases as $canonical => $keys) {
            foreach ($keys as $alias) {
                if (array_key_exists($alias, $decoded)) {
                    $normalized[$canonical] = $decoded[$alias];
                    break;
                }
            }
        }

        $decoded = array_merge($decoded, $normalized);
        $analysis = array_merge($analysis, $decoded);

        session()->put('analysis', $analysis);

        return redirect()->route('dashboard')
            ->with('status', '解析が完了しました')
            ->with('toast', 'analysis_complete');
    }

    public function pushToNotion(Request $request)
    {
        $analysis = session('analysis') ?? [];
        if (! $analysis) {
            return back()->withErrors(['notion' => '解析結果がありません']);
        }

        $apiKey = config('services.notion.api_key');
        $dataSourceId = config('services.notion.data_source_id');
        $notionVersion = config('services.notion.version');

        if (blank($apiKey) || blank($dataSourceId) || blank($notionVersion)) {
            throw ValidationException::withMessages([
                'notion' => 'Notionの設定が不足しています',
            ]);
        }

        $fields = [
            'name',
            'job_title',
            'company',
            'address',
            'website',
            'email',
            'phone_number_1',
            'phone_number_2',
            'industry',
        ];

        $inputOverrides = $request->only($fields);
        foreach ($inputOverrides as $key => $value) {
            if (! array_key_exists($key, $analysis)) {
                continue;
            }
            $analysis[$key] = is_string($value) ? trim($value) : $value;
        }
        session()->put('analysis', $analysis);

        $defaultMapping = [
            'name' => ['name' => '名前', 'type' => 'title'],
            'job_title' => ['name' => '役職', 'type' => 'rich_text'],
            'company' => ['name' => '会社名', 'type' => 'rich_text'],
            'address' => ['name' => '住所', 'type' => 'rich_text'],
            'website' => ['name' => '会社サイトURL', 'type' => 'url'],
            'email' => ['name' => 'メールアドレス', 'type' => 'email'],
            'phone_number_1' => ['name' => '電話番号1', 'type' => 'phone_number'],
            'phone_number_2' => ['name' => '電話番号2', 'type' => 'phone_number'],
            'industry' => ['name' => '業種', 'type' => 'rich_text'],
        ];

        $mapping = array_replace_recursive(
            $defaultMapping,
            json_decode(config('services.notion.property_mapping'), true) ?? []
        );

        $payloadProperties = [];
        foreach ($analysis as $key => $value) {
            if (! isset($mapping[$key])) {
                continue;
            }

            $config = $mapping[$key];
            $name = $config['name'] ?? $key;
            $type = $config['type'] ?? 'rich_text';

            if (is_string($value) && trim($value) === '') {
                $value = null;
            }

            $payloadProperties[$name] = $this->mapNotionProperty($type, $value);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$apiKey,
            'Notion-Version' => $notionVersion,
            'Content-Type' => 'application/json',
        ])->post('https://api.notion.com/v1/pages', [
            'parent' => [
                'type' => 'data_source_id',
                'data_source_id' => $dataSourceId,
            ],
            'properties' => $payloadProperties,
        ]);

        if (config('app.debug')) {
            Log::debug('notion.pages.create.response', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
        }

        if (! $response->ok()) {
            return back()->withErrors(['notion' => 'Notion登録に失敗しました: '.$response->body()]);
        }

        return back()->with('status', 'Notionへの登録が完了しました')
            ->with('toast', 'notion_complete');
    }

    public function clear()
    {
        session()->forget('analysis');
        return redirect()->route('dashboard')->with('status', '選択画像と解析結果をクリアしました');
    }

    private function mapNotionProperty(string $type, $value): array
    {
        return match ($type) {
            'title' => ['title' => [['text' => ['content' => (string) ($value ?? '')]]]],
            'select' => ['select' => $value ? ['name' => (string) $value] : null],
            'url' => ['url' => $value ?: null],
            'email' => ['email' => $value ?: null],
            'phone_number' => ['phone_number' => $value ?: null],
            default => ['rich_text' => [['text' => ['content' => (string) ($value ?? '')]]]],
        };
    }
}
