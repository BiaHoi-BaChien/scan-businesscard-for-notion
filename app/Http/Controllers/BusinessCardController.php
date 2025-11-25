<?php

namespace App\Http\Controllers;

use App\Models\BusinessCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class BusinessCardController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'front' => 'nullable|image|max:4096',
            'back' => 'nullable|image|max:4096',
        ]);

        $user = Auth::user();
        $card = BusinessCard::updateOrCreate(
            ['user_id' => $user->id],
            []
        );

        if ($request->file('front')) {
            $card->front_path = $request->file('front')->store('cards/'.$user->id, 'public');
        }

        if ($request->file('back')) {
            $card->back_path = $request->file('back')->store('cards/'.$user->id, 'public');
        }

        $card->save();

        return back()->with('status', '画像をアップロードしました');
    }

    public function analyze(Request $request)
    {
        $user = Auth::user();
        $card = BusinessCard::where('user_id', $user->id)->latest()->first();

        if (! $card || (! $card->front_path && ! $card->back_path)) {
            return back()->withErrors(['card' => '先に画像をアップロードしてください']);
        }

        $images = array_filter([
            $card->front_path ? Storage::disk('public')->path($card->front_path) : null,
            $card->back_path ? Storage::disk('public')->path($card->back_path) : null,
        ]);

        $prompt = '名刺画像から氏名、会社名、会社ホームページURL、メールアドレス、電話番号1、電話番号2、業種をJSONで返して下さい。業種は会社名からWEB検索した体で簡潔にまとめてください。';
        $apiKey = config('services.openai.api_key');

        $analysis = [
            'name' => null,
            'company' => null,
            'website' => null,
            'email' => null,
            'phone_number_1' => null,
            'phone_number_2' => null,
            'industry' => null,
        ];

        if ($apiKey) {
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

            if ($response->ok()) {
                $content = $response->json('choices.0.message.content');
                $decoded = is_string($content) ? json_decode($content, true) : $content;

                if (is_array($decoded)) {
                    $analysis = array_merge($analysis, $decoded);
                }
            }
        }

        $card->update(['analysis' => $analysis]);

        return back()->with('status', '解析が完了しました');
    }

    public function pushToNotion(Request $request)
    {
        $user = Auth::user();
        $card = BusinessCard::where('user_id', $user->id)->latest()->first();

        if (! $card || ! $card->analysis) {
            return back()->withErrors(['notion' => '解析結果がありません']);
        }

        $mapping = json_decode(config('services.notion.property_mapping'), true) ?? [];
        $payloadProperties = [];

        foreach ($card->analysis as $key => $value) {
            if (! isset($mapping[$key])) {
                continue;
            }

            $config = $mapping[$key];
            $name = $config['name'] ?? $key;
            $type = $config['type'] ?? 'rich_text';

            $payloadProperties[$name] = $this->mapNotionProperty($type, $value);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.notion.api_key'),
            'Notion-Version' => config('services.notion.version'),
            'Content-Type' => 'application/json',
        ])->post('https://api.notion.com/v1/pages', [
            'parent' => ['database_id' => config('services.notion.database_id')],
            'properties' => $payloadProperties,
        ]);

        if (! $response->ok()) {
            return back()->withErrors(['notion' => 'Notion登録に失敗しました: '.$response->body()]);
        }

        return back()->with('status', 'Notionに登録しました');
    }

    private function mapNotionProperty(string $type, $value): array
    {
        return match ($type) {
            'title' => ['title' => [['text' => ['content' => (string) $value]]]],
            'select' => ['select' => ['name' => (string) $value]],
            'url' => ['url' => $value],
            'email' => ['email' => $value],
            'phone_number' => ['phone_number' => $value],
            default => ['rich_text' => [['text' => ['content' => (string) $value]]]],
        };
    }
}
