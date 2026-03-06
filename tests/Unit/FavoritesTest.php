<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Database;
use AIH_Favorites;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class FavoritesTest extends TestCase
{
    private object $wpdb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Reset AIH_Database cached year
        $ref = new \ReflectionClass(AIH_Database::class);
        $prop = $ref->getProperty('cached_year');
        $prop->setValue(null, null);

        // Mock $wpdb
        $this->wpdb = new class {
            public string $prefix = 'wp_';
            public int $insert_id = 1;
            public string $last_error = '';

            /** @var list<mixed> Queued return values for get_var() */
            public array $get_var_queue = [];

            /** @var list<list<string>> Queued return values for get_col() */
            public array $get_col_queue = [];

            /** @var list<list<object>> Queued return values for get_results() */
            public array $get_results_queue = [];

            /** @var list<array{table: string, data: array}> */
            public array $insert_log = [];

            /** @var list<array{table: string, where: array}> */
            public array $delete_log = [];

            public function prepare(string $query, mixed ...$args): string
            {
                return $query;
            }

            public function get_var(string $sql = ''): mixed
            {
                return array_shift($this->get_var_queue);
            }

            /** @return list<string> */
            public function get_col(string $sql = ''): array
            {
                return array_shift($this->get_col_queue) ?? [];
            }

            /** @return list<object> */
            public function get_results(string $sql = ''): array
            {
                return array_shift($this->get_results_queue) ?? [];
            }

            public function insert(string $table, array $data, array|string|null $format = null): int|false
            {
                $this->insert_log[] = ['table' => $table, 'data' => $data];
                return 1;
            }

            public function delete(string $table, array $where, array|string|null $format = null): int|false
            {
                $this->delete_log[] = ['table' => $table, 'where' => $where];
                return 1;
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
            'current_time' => fn() => '2026-01-15 10:00:00',
            'get_transient' => fn() => false,
            'set_transient' => fn() => true,
        ]);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['wpdb']);
        Monkey\tearDown();
        parent::tearDown();
    }

    // ── add() with string bidder_id ──

    public function testAddWithStringBidderId(): void
    {
        // is_favorite check returns null (not yet favorited)
        $this->wpdb->get_var_queue[] = null;

        $favorites = new AIH_Favorites();
        $result = $favorites->add('65199753', 42);

        $this->assertTrue($result);
        $this->assertCount(1, $this->wpdb->insert_log);
        $this->assertSame('65199753', $this->wpdb->insert_log[0]['data']['bidder_id']);
        $this->assertSame(42, $this->wpdb->insert_log[0]['data']['art_piece_id']);
    }

    public function testAddAlreadyFavoritedReturnsTrue(): void
    {
        // is_favorite returns a row ID (already favorited)
        $this->wpdb->get_var_queue[] = '1';

        $favorites = new AIH_Favorites();
        $result = $favorites->add('65199753', 42);

        $this->assertTrue($result);
        $this->assertCount(0, $this->wpdb->insert_log);
    }

    // ── remove() with string bidder_id ──

    public function testRemoveWithStringBidderId(): void
    {
        $favorites = new AIH_Favorites();
        $result = $favorites->remove('65199753', 42);

        $this->assertSame(1, $result);
        $this->assertCount(1, $this->wpdb->delete_log);
        $this->assertSame('65199753', $this->wpdb->delete_log[0]['where']['bidder_id']);
        $this->assertSame(42, $this->wpdb->delete_log[0]['where']['art_piece_id']);
    }

    // ── toggle() with string bidder_id ──

    public function testToggleAddsWhenNotFavorited(): void
    {
        // is_favorite → null (not favorited), then is_favorite inside add → null
        $this->wpdb->get_var_queue[] = null;
        $this->wpdb->get_var_queue[] = null;

        $favorites = new AIH_Favorites();
        $result = $favorites->toggle('65199753', 42);

        $this->assertSame('added', $result['action']);
        $this->assertTrue($result['is_favorite']);
        $this->assertCount(1, $this->wpdb->insert_log);
    }

    public function testToggleRemovesWhenAlreadyFavorited(): void
    {
        // is_favorite → '1' (already favorited)
        $this->wpdb->get_var_queue[] = '1';

        $favorites = new AIH_Favorites();
        $result = $favorites->toggle('65199753', 42);

        $this->assertSame('removed', $result['action']);
        $this->assertFalse($result['is_favorite']);
        $this->assertCount(1, $this->wpdb->delete_log);
    }

    public function testToggleDebouncesRapidCalls(): void
    {
        // Override get_transient to return truthy (lock active)
        Functions\stubs([
            'get_transient' => fn() => 1,
        ]);
        // is_favorite check returns '1' (favorited)
        $this->wpdb->get_var_queue[] = '1';

        $favorites = new AIH_Favorites();
        $result = $favorites->toggle('65199753', 42);

        $this->assertSame('debounced', $result['action']);
        $this->assertTrue($result['is_favorite']);
        // No insert or delete should have happened
        $this->assertCount(0, $this->wpdb->insert_log);
        $this->assertCount(0, $this->wpdb->delete_log);
    }

    // ── is_favorite() with string bidder_id ──

    public function testIsFavoriteWithStringBidderId(): void
    {
        $this->wpdb->get_var_queue[] = '1';

        $favorites = new AIH_Favorites();
        $this->assertTrue($favorites->is_favorite('65199753', 42));
    }

    public function testIsNotFavoriteWithStringBidderId(): void
    {
        $this->wpdb->get_var_queue[] = null;

        $favorites = new AIH_Favorites();
        $this->assertFalse($favorites->is_favorite('65199753', 42));
    }

    // ── get_bidder_favorite_ids() with string bidder_id ──

    public function testGetBidderFavoriteIdsWithStringBidderId(): void
    {
        $this->wpdb->get_col_queue[] = ['1', '5', '12'];

        $favorites = new AIH_Favorites();
        $ids = $favorites->get_bidder_favorite_ids('65199753');

        $this->assertSame(['1', '5', '12'], $ids);
    }

    public function testGetBidderFavoriteIdsEmptyResult(): void
    {
        $this->wpdb->get_col_queue[] = [];

        $favorites = new AIH_Favorites();
        $ids = $favorites->get_bidder_favorite_ids('65199753');

        $this->assertSame([], $ids);
    }

    // ── get_bidder_favorites() with string bidder_id ──

    public function testGetBidderFavoritesWithStringBidderId(): void
    {
        $this->wpdb->get_results_queue[] = [
            (object) ['id' => 1, 'title' => 'Art 1', 'favorited_at' => '2026-01-15'],
            (object) ['id' => 5, 'title' => 'Art 5', 'favorited_at' => '2026-01-14'],
        ];

        $favorites = new AIH_Favorites();
        $results = $favorites->get_bidder_favorites('65199753');

        $this->assertCount(2, $results);
        $this->assertSame('Art 1', $results[0]->title);
    }

    // ── get_favorite_count() ──

    public function testGetFavoriteCountReturnsStringCount(): void
    {
        $this->wpdb->get_var_queue[] = '7';

        $favorites = new AIH_Favorites();
        $count = $favorites->get_favorite_count(42);

        $this->assertSame('7', $count);
    }
}
