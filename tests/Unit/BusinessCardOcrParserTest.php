<?php

namespace Tests\Unit;

use App\Services\Ocr\BusinessCardOcrParser;
use PHPUnit\Framework\TestCase;

class BusinessCardOcrParserTest extends TestCase
{
    public function test_address_with_trailing_company_is_split_and_normalized(): void
    {
        $text = <<<TEXT
田中 太郎
マーケティング部 部長
〒150-0001 東京都渋谷区神宮前1-2-3 株式会社ブルースカイ
TEL: 03-1234-5678  Fax: 03-9876-5432
Email: tanaka@example.jp
https://blue-sky.example.jp
TEXT;

        $parser = new BusinessCardOcrParser();
        $result = $parser->parse($text);

        $this->assertSame('田中 太郎', $result['name']);
        $this->assertSame('マーケティング部 部長', $result['job_title']);
        $this->assertSame('株式会社ブルースカイ', $result['company']);
        $this->assertSame('〒150-0001 東京都渋谷区神宮前1-2-3', $result['address']);
        $this->assertSame('https://blue-sky.example.jp', $result['website']);
        $this->assertSame('tanaka@example.jp', $result['email']);
        $this->assertSame('03-1234-5678', $result['phone_number_1']);
        $this->assertSame('03-9876-5432', $result['phone_number_2']);
    }

    public function test_company_on_separate_line_keeps_address_intact(): void
    {
        $text = <<<TEXT
株式会社アローリンク
山田 花子
営業企画グループ
〒160-0022 東京都新宿区新宿3-4-5
TEL 03-5555-6666
info@arrow.co.jp
www.arrow.co.jp
TEXT;

        $parser = new BusinessCardOcrParser();
        $result = $parser->parse($text);

        $this->assertSame('山田 花子', $result['name']);
        $this->assertSame('営業企画グループ', $result['job_title']);
        $this->assertSame('株式会社アローリンク', $result['company']);
        $this->assertSame('〒160-0022 東京都新宿区新宿3-4-5', $result['address']);
        $this->assertSame('info@arrow.co.jp', $result['email']);
        $this->assertSame('www.arrow.co.jp', $result['website']);
        $this->assertSame('03-5555-6666', $result['phone_number_1']);
    }
}
