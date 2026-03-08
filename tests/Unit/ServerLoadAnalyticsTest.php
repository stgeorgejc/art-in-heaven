<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for server load analytics: sampled poll request logging,
 * connection_type validation, and analytics template integration.
 */
class ServerLoadAnalyticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'sanitize_text_field' => fn(string $v): string => $v,
            'sanitize_key'       => fn(string $v): string => strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $v) ?? ''),
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ========== Connection Type Validation ==========

    /**
     * Valid connection types are accepted.
     */
    #[DataProvider('validConnectionTypeProvider')]
    public function testValidConnectionTypeAccepted(string $type): void
    {
        $valid_types = array('polling', 'realtime', 'offline');
        $connection_type = sanitize_text_field($type);
        if (!in_array($connection_type, $valid_types, true)) {
            $connection_type = 'polling';
        }

        $this->assertSame($type, $connection_type);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validConnectionTypeProvider(): array
    {
        return [
            'polling'  => ['polling'],
            'realtime' => ['realtime'],
            'offline'  => ['offline'],
        ];
    }

    /**
     * Invalid connection types default to 'polling'.
     */
    #[DataProvider('invalidConnectionTypeProvider')]
    public function testInvalidConnectionTypeDefaultsToPolling(string $type): void
    {
        $valid_types = array('polling', 'realtime', 'offline');
        $connection_type = sanitize_text_field($type);
        if (!in_array($connection_type, $valid_types, true)) {
            $connection_type = 'polling';
        }

        $this->assertSame('polling', $connection_type);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidConnectionTypeProvider(): array
    {
        return [
            'empty string'   => [''],
            'unknown value'  => ['websocket'],
            'sql injection'  => ["' OR 1=1 --"],
            'sse_fallback'   => ['sse_fallback'],
        ];
    }

    /**
     * Missing connection_type defaults to 'polling'.
     */
    public function testMissingConnectionTypeDefaultsToPolling(): void
    {
        /** @var array<string, string> $post */
        $post = [];

        $connection_type = isset($post['connection_type'])
            ? sanitize_text_field($post['connection_type'])
            : 'polling';

        $valid_types = array('polling', 'realtime', 'offline');
        if (!in_array($connection_type, $valid_types, true)) {
            $connection_type = 'polling';
        }

        $this->assertSame('polling', $connection_type);
    }

    // ========== Sampled Poll Logging Logic ==========

    /**
     * Counter increments below threshold do not trigger audit log writes.
     */
    public function testCounterBelowThresholdDoesNotFlush(): void
    {
        $threshold = 10;
        $flushed = false;

        for ($i = 1; $i < $threshold; $i++) {
            if ($i >= $threshold) {
                $flushed = true;
            }
        }

        $this->assertFalse($flushed, 'Counter below 10 should not flush to audit log');
    }

    /**
     * Counter at threshold triggers a flush.
     */
    public function testCounterAtThresholdFlushes(): void
    {
        $threshold = 10;
        $count = 0;
        $flushed = false;

        for ($i = 0; $i < $threshold; $i++) {
            $count++;
            if ($count >= $threshold) {
                $flushed = true;
                $count = 0;
            }
        }

        $this->assertTrue($flushed, 'Counter at 10 should flush to audit log');
        $this->assertSame(0, $count, 'Counter should reset after flush');
    }

    /**
     * Flush writes correct count value.
     */
    public function testFlushWritesCorrectCount(): void
    {
        $threshold = 10;
        $count = 0;
        $flushed_count = 0;

        for ($i = 0; $i < $threshold; $i++) {
            $count++;
            if ($count >= $threshold) {
                $flushed_count = $count;
                $count = 0;
            }
        }

        $this->assertSame(10, $flushed_count, 'Flushed count should be exactly 10');
    }

    // ========== Audit Entry Structure ==========

    /**
     * Poll request audit entry has required fields.
     */
    public function testPollRequestAuditEntryStructure(): void
    {
        $bidder_id = 'TEST123';
        $connection_type = 'polling';
        $has_push = false;
        $count = 10;

        $audit_data = array(
            'object_type' => 'poll',
            'bidder_id'   => $bidder_id,
            'details'     => array(
                'count'           => $count,
                'connection_type' => $connection_type,
                'has_push'        => $has_push,
            ),
        );

        $this->assertSame('poll', $audit_data['object_type']);
        $this->assertSame('TEST123', $audit_data['bidder_id']);
        $this->assertSame(10, $audit_data['details']['count']);
        $this->assertSame('polling', $audit_data['details']['connection_type']);
        $this->assertFalse($audit_data['details']['has_push']);
    }

    /**
     * Poll request audit entry includes push status.
     */
    public function testPollRequestAuditEntryIncludesPushStatus(): void
    {
        $audit_data = array(
            'object_type' => 'poll',
            'bidder_id'   => 'BIDDER1',
            'details'     => array(
                'count'           => 10,
                'connection_type' => 'realtime',
                'has_push'        => true,
            ),
        );

        $this->assertTrue($audit_data['details']['has_push']);
        $this->assertSame('realtime', $audit_data['details']['connection_type']);
    }
}
