<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Art_Piece;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class ArtPieceTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset AIH_Database cached_year
        $ref = new \ReflectionClass(AIH_Database::class);
        $prop = $ref->getProperty('cached_year');
        $prop->setValue(null, null);

        // Mock $wpdb
        $this->wpdb = new class {
            public string $prefix = 'wp_';

            /** @var list<string> Captured SQL queries */
            public array $queries = [];

            /** @var list<array<int, object>> Queued return values for get_results() */
            public array $get_results_queue = [];

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            /** @return list<object> */
            public function get_results(string $sql = ''): array
            {
                $this->queries[] = $sql;
                return array_shift($this->get_results_queue) ?? [];
            }

            public function esc_like(string $text): string
            {
                return addcslashes($text, '_%\\');
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        // Stub WordPress functions
        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                ];
                return $options[$key] ?? $default;
            },
            'wp_date' => fn() => '2026',
            'current_time' => fn($type = 'mysql') => '2026-01-15 10:00:00',
            'wp_parse_args' => function ($args, $defaults) {
                return array_merge($defaults, $args);
            },
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── get_all_with_stats ──

    public function testGetAllWithStatsDefaultsReturnArray(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $result = $model->get_all_with_stats();

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function testGetAllWithStatsBuildsSearchClause(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats(array('search' => 'sunset'));

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString('a.title LIKE', $sql);
        $this->assertStringContainsString('a.artist LIKE', $sql);
        $this->assertStringContainsString('a.art_id LIKE', $sql);
    }

    public function testGetAllWithStatsNoSearchClauseWithoutParam(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats();

        $sql = end($this->wpdb->queries);
        $this->assertStringNotContainsString('a.title LIKE', $sql);
    }

    public function testGetAllWithStatsBuildsStatusFilter(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats(array('status' => 'draft'));

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString("a.status", $sql);
    }

    public function testGetAllWithStatsActiveStatusUsesTimeRange(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats(array('status' => 'active'));

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString('a.auction_start', $sql);
        $this->assertStringContainsString('a.auction_end', $sql);
    }

    public function testGetAllWithStatsSearchAndStatusCombined(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats(array('search' => 'AIH-001', 'status' => 'ended'));

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString('a.art_id LIKE', $sql);
        $this->assertStringContainsString('ended', $sql);
    }

    public function testGetAllWithStatsHasBidsFilter(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats(array('has_bids' => true));

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString('HAVING total_bids > 0', $sql);
    }

    public function testGetAllWithStatsNoBidsFilter(): void
    {
        $this->wpdb->get_results_queue[] = [];

        $model = new AIH_Art_Piece();
        $model->get_all_with_stats(array('has_bids' => false));

        $sql = end($this->wpdb->queries);
        $this->assertStringContainsString('HAVING total_bids = 0', $sql);
    }
}
