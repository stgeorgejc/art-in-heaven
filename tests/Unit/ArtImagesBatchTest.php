<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Art_Images;
use AIH_Database;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/class-aih-art-images.php';

class ArtImagesBatchTest extends TestCase
{
    private object $wpdb;
    /** @var mixed */
    private mixed $previousWpdb = null;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Save previous $wpdb so we can restore it in tearDown
        $this->previousWpdb = $GLOBALS['wpdb'] ?? null;

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
                $this->queries[] = $query;
                return $query;
            }

            /** @return list<object> */
            public function get_results(string $sql = ''): array
            {
                $this->queries[] = $sql;
                return array_shift($this->get_results_queue) ?? [];
            }
        };
        $GLOBALS['wpdb'] = $this->wpdb;

        Functions\stubs([
            'get_option' => function ($key, $default = false) {
                $options = [
                    'aih_auction_year' => '2026',
                ];
                return $options[$key] ?? $default;
            },
            'wp_date' => fn() => '2026',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->previousWpdb !== null) {
            $GLOBALS['wpdb'] = $this->previousWpdb;
        } else {
            unset($GLOBALS['wpdb']);
        }
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testGetImagesBatchReturnsEmptyArrayForEmptyInput(): void
    {
        $images = new AIH_Art_Images();
        $result = $images->get_images_batch([]);

        $this->assertSame([], $result);
    }

    public function testGetImagesBatchGroupsByArtPieceId(): void
    {
        $this->wpdb->get_results_queue[] = [
            (object) ['id' => '1', 'art_piece_id' => '10', 'image_url' => 'a.jpg', 'watermarked_url' => 'a-wm.jpg', 'is_primary' => '1', 'sort_order' => '0'],
            (object) ['id' => '2', 'art_piece_id' => '10', 'image_url' => 'b.jpg', 'watermarked_url' => 'b-wm.jpg', 'is_primary' => '0', 'sort_order' => '1'],
            (object) ['id' => '3', 'art_piece_id' => '20', 'image_url' => 'c.jpg', 'watermarked_url' => 'c-wm.jpg', 'is_primary' => '1', 'sort_order' => '0'],
        ];

        $images = new AIH_Art_Images();
        $result = $images->get_images_batch([10, 20, 30]);

        $this->assertCount(2, $result[10]);
        $this->assertCount(1, $result[20]);
        $this->assertCount(0, $result[30]);
        $this->assertSame('a.jpg', $result[10][0]->image_url);
        $this->assertSame('c.jpg', $result[20][0]->image_url);
    }

    public function testGetImagesBatchInitializesAllRequestedIds(): void
    {
        // Return no rows from DB
        $this->wpdb->get_results_queue[] = [];

        $images = new AIH_Art_Images();
        $result = $images->get_images_batch([5, 10, 15]);

        $this->assertArrayHasKey(5, $result);
        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(15, $result);
        $this->assertSame([], $result[5]);
        $this->assertSame([], $result[10]);
        $this->assertSame([], $result[15]);
    }
}
