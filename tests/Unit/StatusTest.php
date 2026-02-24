<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use AIH_Status;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use DateTime;
use DateTimeZone;

class StatusTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->tz = new DateTimeZone('America/New_York');

        Functions\stubs([
            'wp_timezone' => function () {
                return $this->tz;
            },
            'current_time' => function ($format) {
                return (new DateTime('now', $this->tz))->format(
                    $format === 'mysql' ? 'Y-m-d H:i:s' : $format
                );
            },
            '__' => function ($text) {
                return $text;
            },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Generate a future datetime string relative to now.
     */
    private function futureDate(string $offset = '+1 year'): string
    {
        return (new DateTime($offset, $this->tz))->format('Y-m-d H:i:s');
    }

    /**
     * Generate a past datetime string relative to now.
     */
    private function pastDate(string $offset = '-1 year'): string
    {
        return (new DateTime($offset, $this->tz))->format('Y-m-d H:i:s');
    }

    // ── is_valid_status() ──

    public function testIsValidStatusAcceptsAllValidStatuses(): void
    {
        $this->assertTrue(AIH_Status::is_valid_status('active'));
        $this->assertTrue(AIH_Status::is_valid_status('draft'));
        $this->assertTrue(AIH_Status::is_valid_status('ended'));
        $this->assertTrue(AIH_Status::is_valid_status('paused'));
        $this->assertTrue(AIH_Status::is_valid_status('canceled'));
    }

    public function testIsValidStatusRejectsInvalid(): void
    {
        $this->assertFalse(AIH_Status::is_valid_status('unknown'));
        $this->assertFalse(AIH_Status::is_valid_status(''));
        $this->assertFalse(AIH_Status::is_valid_status('Active')); // case-sensitive
    }

    // ── is_closed_status() ──

    public function testIsClosedStatus(): void
    {
        $this->assertTrue(AIH_Status::is_closed_status('ended'));
        $this->assertTrue(AIH_Status::is_closed_status('paused'));
        $this->assertTrue(AIH_Status::is_closed_status('canceled'));
        $this->assertFalse(AIH_Status::is_closed_status('active'));
        $this->assertFalse(AIH_Status::is_closed_status('draft'));
    }

    // ── format_date() ──

    public function testFormatDateWithDateTime(): void
    {
        $dt = new DateTime('2025-06-15 14:30:00');
        $this->assertSame('Jun 15, 2025 2:30 PM', AIH_Status::format_date($dt));
    }

    public function testFormatDateWithCustomFormat(): void
    {
        $dt = new DateTime('2025-12-25 00:00:00');
        $this->assertSame('2025-12-25', AIH_Status::format_date($dt, 'Y-m-d'));
    }

    public function testFormatDateWithNullReturnsDash(): void
    {
        $this->assertSame('—', AIH_Status::format_date(null));
    }

    // ── validate_art_piece() ──

    public function testValidateArtPieceNull(): void
    {
        $result = AIH_Status::validate_art_piece(null);
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
    }

    public function testValidateArtPieceNonObject(): void
    {
        $result = AIH_Status::validate_art_piece('not an object');
        $this->assertFalse($result['valid']);
    }

    public function testValidateArtPieceMissingProperties(): void
    {
        $piece = (object) ['id' => 1]; // missing status, auction_start, auction_end
        $result = AIH_Status::validate_art_piece($piece);
        $this->assertFalse($result['valid']);
        $this->assertCount(3, $result['errors']); // 3 missing properties
    }

    public function testValidateArtPieceInvalidStatus(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'bogus',
            'auction_start' => '2025-01-01 00:00:00',
            'auction_end' => '2025-12-31 23:59:59',
        ];
        $result = AIH_Status::validate_art_piece($piece);
        $this->assertFalse($result['valid']);
    }

    public function testValidateArtPieceValid(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'active',
            'auction_start' => '2025-01-01 00:00:00',
            'auction_end' => '2025-12-31 23:59:59',
        ];
        $result = AIH_Status::validate_art_piece($piece);
        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
    }

    // ── compute_status() ──

    public function testComputeStatusEndedOverrides(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'ended',
            'auction_start' => '2025-01-01 00:00:00',
            'auction_end' => $this->futureDate(), // future end, but status says ended
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('ended', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusPausedOverrides(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'paused',
            'auction_start' => $this->pastDate(),
            'auction_end' => $this->futureDate(),
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('paused', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusCanceledOverrides(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'canceled',
            'auction_start' => $this->pastDate(),
            'auction_end' => $this->futureDate(),
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('canceled', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusDraft(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'draft',
            'auction_start' => $this->pastDate(),
            'auction_end' => $this->futureDate(),
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('draft', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusActiveWithinWindow(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'active',
            'auction_start' => $this->pastDate(),
            'auction_end' => $this->futureDate(),
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['can_bid']);
    }

    public function testComputeStatusActiveButExpired(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'active',
            'auction_start' => $this->pastDate('-2 years'),
            'auction_end' => $this->pastDate(), // past
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('ended', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusActiveButNotStarted(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'active',
            'auction_start' => $this->futureDate('+6 months'), // future
            'auction_end' => $this->futureDate('+1 year'),
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('draft', $result['status']); // upcoming = draft
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusActiveNoDates(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'active',
            'auction_start' => null,
            'auction_end' => null,
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('active', $result['status']);
        $this->assertTrue($result['can_bid']);
    }

    public function testComputeStatusActiveInvalidDateRange(): void
    {
        $piece = (object) [
            'id' => 1,
            'status' => 'active',
            'auction_start' => '2025-12-31 23:59:59',
            'auction_end' => '2025-01-01 00:00:00', // end before start
        ];
        $result = AIH_Status::compute_status($piece);
        $this->assertSame('invalid', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    public function testComputeStatusInvalidData(): void
    {
        $result = AIH_Status::compute_status(null);
        $this->assertSame('invalid', $result['status']);
        $this->assertFalse($result['can_bid']);
    }

    // ── get_status_sql() ──

    public function testGetStatusSqlReturnsString(): void
    {
        $sql = AIH_Status::get_status_sql('a', '%s');
        $this->assertIsString($sql);
        $this->assertStringContainsString('CASE', $sql);
        $this->assertStringContainsString('a.status', $sql);
    }

    // ── get_status_options() ──

    public function testGetStatusOptionsReturnsAllStatuses(): void
    {
        $options = AIH_Status::get_status_options();
        $this->assertArrayHasKey('active', $options);
        $this->assertArrayHasKey('draft', $options);
        $this->assertArrayHasKey('ended', $options);
        $this->assertArrayHasKey('paused', $options);
        $this->assertArrayHasKey('canceled', $options);
        $this->assertCount(5, $options);
    }
}
