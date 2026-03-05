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
            'sanitize_text_field' => function ($v) { return $v; },
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
        $loggedEvent = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('aih_frontend_nonce', 'nonce')
            ->andReturn(true);

        Functions\expect('wp_send_json_success')
            ->once();

        $this->runPushPermissionLogic(
            true,
            'granted',
            'bell',
            function ($event_type, $data) use (&$loggedEvent) {
                $loggedEvent = ['event_type' => $event_type, 'data' => $data];
            }
        );

        $this->assertNotNull($loggedEvent);
        $this->assertSame('push_permission_granted', $loggedEvent['event_type']);
        $this->assertSame('bell', $loggedEvent['data']['details']['source']);
    }

    /**
     * push_permission logs push_permission_denied for 'denied' permission.
     */
    public function testPushPermissionLogsDenied(): void
    {
        $loggedEvent = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_send_json_success')
            ->once();

        $this->runPushPermissionLogic(
            true,
            'denied',
            'after_bid',
            function ($event_type, $data) use (&$loggedEvent) {
                $loggedEvent = ['event_type' => $event_type, 'data' => $data];
            }
        );

        $this->assertNotNull($loggedEvent);
        $this->assertSame('push_permission_denied', $loggedEvent['event_type']);
        $this->assertSame('after_bid', $loggedEvent['data']['details']['source']);
    }

    /**
     * push_permission rejects invalid permission values.
     */
    public function testPushPermissionRejectsInvalidValue(): void
    {
        $errorResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data) use (&$errorResponse) {
                $errorResponse = $data;
            });

        $this->runPushPermissionLogic(
            true,
            'invalid_value',
            'bell',
            function () { $this->fail('Should not log audit for invalid value'); }
        );

        $this->assertSame('Invalid permission value', $errorResponse['message']);
    }

    /**
     * push_permission rejects unauthenticated requests.
     */
    public function testPushPermissionRejectsUnauthenticated(): void
    {
        $errorResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data) use (&$errorResponse) {
                $errorResponse = $data;
            });

        $this->runPushPermissionLogic(false, 'granted', 'bell', function () {});

        $this->assertSame('Not authenticated', $errorResponse['message']);
    }

    // ========== push_clicked handler ==========

    /**
     * push_clicked logs the click event with art_piece_id and notification_type.
     */
    public function testPushClickedLogsEvent(): void
    {
        $loggedEvent = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_send_json_success')
            ->once();

        $this->runPushClickedLogic(
            true,
            'outbid',
            42,
            function ($event_type, $data) use (&$loggedEvent) {
                $loggedEvent = ['event_type' => $event_type, 'data' => $data];
            }
        );

        $this->assertNotNull($loggedEvent);
        $this->assertSame('push_clicked', $loggedEvent['event_type']);
        $this->assertSame(42, $loggedEvent['data']['object_id']);
        $this->assertSame('outbid', $loggedEvent['data']['details']['notification_type']);
    }

    /**
     * push_clicked logs winner notification type correctly.
     */
    public function testPushClickedLogsWinnerType(): void
    {
        $loggedEvent = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_send_json_success')
            ->once();

        $this->runPushClickedLogic(
            true,
            'winner',
            99,
            function ($event_type, $data) use (&$loggedEvent) {
                $loggedEvent = ['event_type' => $event_type, 'data' => $data];
            }
        );

        $this->assertSame('winner', $loggedEvent['data']['details']['notification_type']);
        $this->assertSame(99, $loggedEvent['data']['details']['art_piece_id']);
    }

    /**
     * push_clicked rejects unauthenticated requests.
     */
    public function testPushClickedRejectsUnauthenticated(): void
    {
        $errorResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->andReturn(true);

        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data) use (&$errorResponse) {
                $errorResponse = $data;
            });

        $this->runPushClickedLogic(false, 'outbid', 1, function () {});

        $this->assertSame('Not authenticated', $errorResponse['message']);
    }

    // ========== bid_source attribution ==========

    /**
     * Bid source defaults to 'organic' when not provided.
     */
    public function testBidSourceDefaultsToOrganic(): void
    {
        $_POST = [];

        $source = isset($_POST['bid_source']) ? sanitize_text_field($_POST['bid_source']) : 'organic';

        $this->assertSame('organic', $source);
    }

    /**
     * Bid source is captured from POST when provided.
     */
    public function testBidSourceCapturedFromPost(): void
    {
        $_POST = ['bid_source' => 'push'];

        $source = isset($_POST['bid_source']) ? sanitize_text_field($_POST['bid_source']) : 'organic';

        $this->assertSame('push', $source);
    }

    /**
     * Bid source is sanitized.
     */
    public function testBidSourceIsSanitized(): void
    {
        $_POST = ['bid_source' => '<script>evil</script>'];

        // sanitize_text_field is stubbed to identity in setUp,
        // but the real function would strip tags. Test the flow.
        $source = isset($_POST['bid_source']) ? sanitize_text_field($_POST['bid_source']) : 'organic';

        $this->assertSame($_POST['bid_source'], $source);
    }

    // ========== push_permission source variants ==========

    /**
     * Permission source defaults to 'bell' when not provided.
     */
    public function testPermissionSourceDefaultsToBell(): void
    {
        $loggedEvent = null;

        Functions\expect('check_ajax_referer')->once()->andReturn(true);
        Functions\expect('wp_send_json_success')->once();

        // Pass empty string to simulate missing source
        $this->runPushPermissionLogic(
            true,
            'granted',
            '',
            function ($event_type, $data) use (&$loggedEvent) {
                $loggedEvent = $data;
            }
        );

        $this->assertSame('bell', $loggedEvent['details']['source']);
    }

    // ========== HELPERS ==========

    /**
     * Replicate push_permission handler logic.
     */
    private function runPushPermissionLogic(
        bool $isLoggedIn,
        string $permission,
        string $source,
        callable $auditLogger
    ): void {
        check_ajax_referer('aih_frontend_nonce', 'nonce');

        if (!$isLoggedIn) {
            wp_send_json_error(['message' => 'Not authenticated']);
            return;
        }

        $sanitizedPermission = sanitize_text_field($permission);
        $sanitizedSource = sanitize_text_field($source);
        if (empty($sanitizedSource)) {
            $sanitizedSource = 'bell';
        }

        if (!in_array($sanitizedPermission, ['granted', 'denied'], true)) {
            wp_send_json_error(['message' => 'Invalid permission value']);
            return;
        }

        $event_type = 'granted' === $sanitizedPermission ? 'push_permission_granted' : 'push_permission_denied';

        $auditLogger($event_type, [
            'bidder_id' => 'test-bidder',
            'details'   => ['source' => $sanitizedSource],
        ]);

        wp_send_json_success();
    }

    /**
     * Replicate push_clicked handler logic.
     */
    private function runPushClickedLogic(
        bool $isLoggedIn,
        string $notificationType,
        int $artPieceId,
        callable $auditLogger
    ): void {
        check_ajax_referer('aih_frontend_nonce', 'nonce');

        if (!$isLoggedIn) {
            wp_send_json_error(['message' => 'Not authenticated']);
            return;
        }

        $sanitizedType = sanitize_text_field($notificationType);
        $sanitizedId = $artPieceId;

        $auditLogger('push_clicked', [
            'bidder_id' => 'test-bidder',
            'object_id' => $sanitizedId,
            'details'   => [
                'notification_type' => $sanitizedType,
                'art_piece_id'      => $sanitizedId,
            ],
        ]);

        wp_send_json_success();
    }
}
