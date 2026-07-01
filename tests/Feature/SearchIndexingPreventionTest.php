<?php

namespace Tests\Feature;

use Tests\TestCase;

class SearchIndexingPreventionTest extends TestCase
{
    public function test_public_robots_txt_disallows_crawling(): void
    {
        $this->assertSame(
            "User-agent: *\nDisallow: /\n",
            file_get_contents(public_path('robots.txt')),
        );
    }

    public function test_login_page_prevents_search_indexing(): void
    {
        $this->get(route('login.form'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex')
            ->assertSee(
                '<meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex">',
                false,
            );
    }

    public function test_json_endpoint_prevents_search_indexing(): void
    {
        $this->getJson(route('csrf.token'))
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex');
    }
}
