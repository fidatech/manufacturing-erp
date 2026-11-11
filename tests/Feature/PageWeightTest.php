<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The system must stay usable on a very slow connection.
 * These tests fail when a page or the shared assets grow past their budget,
 * or when something is loaded from another server.
 */
class PageWeightTest extends TestCase
{
    use RefreshDatabase;

    /** Compressed size allowed for CSS + JavaScript + icons together, in bytes. */
    private const ASSET_BUDGET = 60 * 1024;

    /** Compressed size allowed for the HTML of one page, in bytes. */
    private const PAGE_BUDGET = 10 * 1024;

    public function test_shared_assets_fit_the_budget(): void
    {
        $total = 0;
        foreach (['css/app.css', 'js/app.js', 'icons.svg'] as $file) {
            $this->assertFileExists(public_path($file));
            $total += strlen(gzencode(file_get_contents(public_path($file)), 9));
        }

        $this->assertLessThan(self::ASSET_BUDGET, $total, 'CSS + JS + icons are too heavy.');
    }

    public function test_pages_are_small_and_load_nothing_from_other_servers(): void
    {
        $pages = [
            'login' => $this->get(route('login'))->assertOk()->getContent(),
            'dashboard' => $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()->getContent(),
        ];

        foreach ($pages as $name => $html) {
            $this->assertLessThan(self::PAGE_BUDGET, strlen(gzencode($html, 9)), "The $name page is too heavy.");

            preg_match_all('/<(?:script|link|img|use)\b[^>]*\b(?:src|href)="([^"]+)"/', $html, $matches);
            $this->assertNotEmpty($matches[1]);
            foreach ($matches[1] as $url) {
                $this->assertStringStartsWith(url('/'), $url, "The $name page loads $url from another server.");
            }
        }
    }
}
