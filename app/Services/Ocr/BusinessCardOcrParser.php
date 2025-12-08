<?php

namespace App\Services\Ocr;

class BusinessCardOcrParser
{
    private const COMPANY_PATTERN = '/(?:株式会社|有限会社|Inc\\.?|LLC|Co\\.?|Company|コーポレーション)/iu';

    public function parse(string $text): array
    {
        $result = [
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

        $lines = $this->tokenize($text);
        $remaining = [];

        foreach ($lines as $line) {
            $line = $this->extractEmail($line, $result);
            $line = $this->extractWebsite($line, $result);
            $line = $this->extractPhoneNumbers($line, $result);

            if ($line !== '') {
                $remaining[] = $line;
            }
        }

        foreach ($remaining as $line) {
            [$addressFromLine, $companyFromLine] = $this->splitAddressAndCompany($line);

            if ($addressFromLine && $result['address'] === null) {
                $result['address'] = $addressFromLine;
            }

            if ($companyFromLine && $result['company'] === null) {
                $result['company'] = $companyFromLine;
                continue;
            }

            if ($result['company'] === null && $this->looksLikeCompany($line)) {
                $result['company'] = $line;
                continue;
            }

            if ($result['address'] === null && $this->looksLikeAddress($line)) {
                $result['address'] = $line;
                continue;
            }

            if ($result['name'] === null) {
                $result['name'] = $line;
                continue;
            }

            if ($result['job_title'] === null) {
                $result['job_title'] = $line;
            }
        }

        return $result;
    }

    private function tokenize(string $text): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];

        return array_values(array_filter(array_map(fn ($line) => trim($line), $lines), fn ($line) => $line !== ''));
    }

    private function extractEmail(string $line, array &$result): string
    {
        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $line, $match)) {
            $result['email'] ??= $match[0];
            $line = trim(str_replace($match[0], '', $line));
        }

        return $line;
    }

    private function extractWebsite(string $line, array &$result): string
    {
        if (preg_match('/(?:https?:\/\/|www\.)[^\s]+/i', $line, $match)) {
            $result['website'] ??= rtrim($match[0], '.,');
            $line = trim(str_replace($match[0], '', $line));
        }

        return $line;
    }

    private function extractPhoneNumbers(string $line, array &$result): string
    {
        if (preg_match_all('/(?:TEL|Tel|tel|電話)?[:：]?\s*(\+?\d[\d\-\s\(\)]{7,}\d)/u', $line, $matches)) {
            foreach ($matches[1] as $number) {
                $normalized = preg_replace('/[^\d\+\-]/', '', $number);
                $normalized = trim(preg_replace('/-{2,}/', '-', $normalized), '-');

                if ($result['phone_number_1'] === null) {
                    $result['phone_number_1'] = $normalized;
                } elseif ($result['phone_number_2'] === null) {
                    $result['phone_number_2'] = $normalized;
                }
            }

            $line = trim(str_replace($matches[0], '', $line));
        }

        return $line;
    }

    private function splitAddressAndCompany(string $line): array
    {
        $companyRegex = self::COMPANY_PATTERN;

        if (preg_match('/^(?<address>〒?\d{3}-\d{4}[^\n]*?)(?:\s+|　)(?<company>.+' . $companyRegex . '.*)$/u', $line, $match)) {
            return [trim($match['address']), trim($match['company'])];
        }

        if (preg_match('/^(?<address>.*?(?:都|道|府|県)[^\n]*?)(?:\s+|　)(?<company>.+' . $companyRegex . '.*)$/u', $line, $match)) {
            return [trim($match['address']), trim($match['company'])];
        }

        return [null, null];
    }

    private function looksLikeAddress(string $line): bool
    {
        return (bool) preg_match('/〒\d{3}-\d{4}/u', $line)
            || (bool) preg_match('/(北海道|東京都|京都府|大阪府|.{1,3}県)/u', $line);
    }

    private function looksLikeCompany(string $line): bool
    {
        return (bool) preg_match(self::COMPANY_PATTERN, $line);
    }
}
