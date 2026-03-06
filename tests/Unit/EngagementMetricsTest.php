<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for engagement metrics AJAX handlers (push_permission, push_clicked)
 * and bid_source attribution logic.
 *
 * Mirrors the AJAX handler logic with Brain Monkey mocks since AIH_Ajax
 * has side effects in its constructor.
 */
class EngagementMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'sanitize_text_field' => fn(string $v): string => $v,
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // ========== push_permission handler ==========

    /**
     * push_permission logs push_permission_granted for 'granted' permission.
     */
    public function testPushPermissionLogsGranted(): void
    {
        $result = $this->runPushPermissionLogic(true, 'granted', 'bell');

        $this->assertNotNull($result['audit']);
        $this->assertSame('push_permission_granted', $result['audit']['event_type']);
        $this->assertSame('bell', $result['audit']['data']['details']['source']);
        $this->assertTrue($result['success']);
    }

    /**
     * push_permission logs push_permission_denied for 'denied' permission.
     */
    public function testPushPermissionLogsDenied(): void
    {
        $result = $this->runPushPermissionLogic(true, 'denied', 'after_bid');

        $this->assertNotNull($result['audit']);
        $this->assertSame('push_permission_denied', $result['audit']['event_type']);
        $this->assertSame('after_bid', $result['audit']['data']['details']['source']);
        $this->assertTrue($result['success']);
    }

    /**
     * push_permission rejects invalid permission values.
     */
    public function testPushPermissionRejectsInvalidValue(): void
    {
        $result = $this->runPushPermissionLogic(true, 'invalid_value', 'bell');

        $this->assertNull($result['audit']);
        $this->assertFalse($result['success']);
        $this->assertSame('Invalid permission value', $result['error']);
    }

    /**
     * push_permission rejects unauthenticated requests.
     */
    public function testPushPermissionRejectsUnauthenticated(): void
    {
        $result = $this->runPushPermissionLogic(false, 'granted', 'bell');

        $this->assertNull($result['audit']);
        $this->assertFalse($result['success']);
        $this->assertSame('Please sign in.', $result['error']);
    }

    // ========== push_clicked handler ==========

    /**
     * push_clicked logs the click event with art_piece_id and notification_type.
     */
    public function testPushClickedLogsEvent(): void
    {
        $result = $this->runPushClickedLogic(true, 'outbid', 42);

        $this->assertNotNull($result['audit']);
        $this->assertSame('push_clicked', $result['audit']['event_type']);
        $this->assertSame(42, $result['audit']['data']['object_id']);
        $this->assertSame('outbid', $result['audit']['data']['details']['notification_type']);
        $this->assertTrue($result['success']);
    }

    /**
     * push_clicked logs winner notification type correctly.
     */
    public function testPushClickedLogsWinnerType(): void
    {
        $result = $this->runPushClickedLogic(true, 'winner', 99);

        $this->assertNotNull($result['audit']);
        $this->assertSame('winner', $result['audit']['data']['details']['notification_type']);
        $this->assertSame(99, $result['audit']['data']['details']['art_piece_id']);
    }

    /**
     * push_clicked rejects unauthenticated requests.
     */
    public function testPushClickedRejectsUnauthenticated(): void
    {
        $result = $this->runPushClickedLogic(false, 'outbid', 1);

        $this->assertNull($result['audit']);
        $this->assertFalse($result['success']);
        $this->assertSame('Please sign in.', $result['error']);
    }

    // ========== bid_source attribution ==========

    /**
     * Bid source defaults to 'organic' when not provided.
     */
    public function testBidSourceDefaultsToOrganic(): void
    {
        /** @var array<string, string> $post */
        $post = [];

        $source = \array_key_exists('bid_source', $post) ? sanitize_text_field($post['bid_source']) : 'organic';

        $this->assertSame('organic', $source);
    }

    /**
     * Bid source is captured from POST when provided.
     */
    public function testBidSourceCapturedFromPost(): void
    {
        /** @var array<string, string> $post */
        $post = ['bid_source' => 'push'];

        $source = \array_key_exists('bid_source', $post) ? sanitize_text_field($post['bid_source']) : 'organic';

        $this->assertSame('push', $source);
    }

    /**
     * Bid source is whitelisted: unknown values default to 'organic'.
     */
    public function testBidSourceWhitelistsUnknownValues(): void
    {
        /** @var array<string, string> $post */
        $post = ['bid_source' => 'evil_value'];

        $source = \array_key_exists('bid_source', $post) ? sanitize_text_field($post['bid_source']) : 'organic';
        if (!\in_array($source, ['organic', 'push'], true)) {
            $source = 'organic';
        }

        $this->assertSame('organic', $source);
    }

    // ========== push_permission source variants ==========

    /**
     * Permission source defaults to 'bell' when not provided.
     */
    public function testPermissionSourceDefaultsToBell(): void
    {
        $result = $this->runPushPermissionLogic(true, 'granted', '');

        $this->assertNotNull($result['audit']);
        $this->assertSame('bell', $result['audit']['data']['details']['source']);
    }

    /**
     * Permission source whitelists unknown values to 'other'.
     */
    public function testPermissionSourceWhitelistsUnknown(): void
    {
        $result = $this->runPushPermissionLogic(true, 'granted', 'malicious_source');

        $this->assertNotNull($result['audit']);
        $this->assertSame('other', $result['audit']['data']['details']['source']);
    }

    /**
     * push_clicked normalizes unknown notification_type to 'unknown'.
     */
    public function testPushClickedNormalizesUnknownNotificationType(): void
    {
        $result = $this->runPushClickedLogic(true, 'evil_type', 42);

        $this->assertNotNull($result['audit']);
        $this->assertSame('unknown', $result['audit']['data']['details']['notification_type']);
    }

    // ========== HELPERS ==========

    /**
     * Replicate push_permission handler validation and audit logic.
     *
     * Returns structured result instead of calling wp_send_json_* to avoid
     * PHPStan deadCode.unreachable errors (WP stubs declare those as @return never).
     *
     * @return array{success: bool, error: string|null, audit: array{event_type: string, data: array<string, mixed>}|null}
     */
    private function runPushPermissionLogic(
        bool $isLoggedIn,
        string $permission,
        string $source
    ): array {
        if (!$isLoggedIn) {
            return ['success' => false, 'error' => 'Please sign in.', 'audit' => null];
        }

        $sanitizedPermission = sanitize_text_field($permission);
        $sanitizedSource = sanitize_text_field($source);
        if (empty($sanitizedSource)) {
            $sanitizedSource = 'bell';
        }
        if (!\in_array($sanitizedSource, ['bell', 'after_bid'], true)) {
            $sanitizedSource = 'other';
        }

        if (!\in_array($sanitizedPermission, ['granted', 'denied'], true)) {
            return ['success' => false, 'error' => 'Invalid permission value', 'audit' => null];
        }

        $event_type = 'granted' === $sanitizedPermission ? 'push_permission_granted' : 'push_permission_denied';

        return [
            'success' => true,
            'error'   => null,
            'audit'   => [
                'event_type' => $event_type,
                'data'       => [
                    'bidder_id' => 'test-bidder',
                    'details'   => ['source' => $sanitizedSource],
                ],
            ],
        ];
    }

    /**
     * Replicate push_clicked handler validation and audit logic.
     *
     * @return array{success: bool, error: string|null, audit: array{event_type: string, data: array<string, mixed>}|null}
     */
    private function runPushClickedLogic(
        bool $isLoggedIn,
        string $notificationType,
        int $artPieceId
    ): array {
        if (!$isLoggedIn) {
            return ['success' => false, 'error' => 'Please sign in.', 'audit' => null];
        }

        $sanitizedType = sanitize_text_field($notificationType);
        if (!\in_array($sanitizedType, ['outbid', 'winner'], true)) {
            $sanitizedType = 'unknown';
        }

        return [
            'success' => true,
            'error'   => null,
            'audit'   => [
                'event_type' => 'push_clicked',
                'data'       => [
                    'bidder_id' => 'test-bidder',
                    'object_id' => $artPieceId,
                    'details'   => [
                        'notification_type' => $sanitizedType,
                        'art_piece_id'      => $artPieceId,
                    ],
                ],
            ],
        ];
    }
}
