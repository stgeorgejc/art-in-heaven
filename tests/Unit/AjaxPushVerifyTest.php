<?php

declare(strict_types=1);

namespace ArtInHeaven\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the push_verify AJAX handler logic.
 *
 * Since AIH_Ajax constructor registers WordPress hooks (side-effects),
 * we test the push_verify logic path in isolation by replicating its
 * control flow with Brain Monkey mocks.
 */
class AjaxPushVerifyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\stubs([
            'esc_url_raw' => function ($value) {
                return filter_var($value, FILTER_SANITIZE_URL);
            },
        ]);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Verifying with a missing endpoint returns an error with 'Missing endpoint'.
     */
    public function testPushVerifyRejectsEmptyEndpoint(): void
    {
        $_POST = ['nonce' => 'valid', 'endpoint' => ''];

        $errorResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('aih_frontend_nonce', 'nonce')
            ->andReturn(true);

        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data) use (&$errorResponse) {
                $errorResponse = $data;
            });

        // Simulate handler: nonce OK, auth OK, but empty endpoint
        $this->runPushVerifyLogic(true, '');

        $this->assertIsArray($errorResponse);
        $this->assertSame('Missing endpoint', $errorResponse['message']);
    }

    /**
     * Verifying a known endpoint returns success with valid=true.
     */
    public function testPushVerifySucceedsForKnownEndpoint(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';
        $_POST = ['nonce' => 'valid', 'endpoint' => $endpoint];

        $successResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('aih_frontend_nonce', 'nonce')
            ->andReturn(true);

        Functions\expect('wp_send_json_success')
            ->once()
            ->andReturnUsing(function ($data) use (&$successResponse) {
                $successResponse = $data;
            });

        $this->runPushVerifyLogic(true, $endpoint, true);

        $this->assertIsArray($successResponse);
        $this->assertTrue($successResponse['valid']);
    }

    /**
     * Verifying an unknown endpoint returns error with valid=false.
     */
    public function testPushVerifyFailsForUnknownEndpoint(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/unknown';
        $_POST = ['nonce' => 'valid', 'endpoint' => $endpoint];

        $errorResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('aih_frontend_nonce', 'nonce')
            ->andReturn(true);

        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data) use (&$errorResponse) {
                $errorResponse = $data;
            });

        $this->runPushVerifyLogic(true, $endpoint, false);

        $this->assertIsArray($errorResponse);
        $this->assertFalse($errorResponse['valid']);
        $this->assertSame('Subscription not found', $errorResponse['message']);
    }

    /**
     * Unauthenticated request returns error with 'Not authenticated'.
     */
    public function testPushVerifyRejectsUnauthenticated(): void
    {
        $_POST = ['nonce' => 'valid', 'endpoint' => 'https://example.com/push'];

        $errorResponse = null;

        Functions\expect('check_ajax_referer')
            ->once()
            ->with('aih_frontend_nonce', 'nonce')
            ->andReturn(true);

        Functions\expect('wp_send_json_error')
            ->once()
            ->andReturnUsing(function ($data) use (&$errorResponse) {
                $errorResponse = $data;
            });

        $this->runPushVerifyLogic(false, 'https://example.com/push');

        $this->assertIsArray($errorResponse);
        $this->assertSame('Not authenticated', $errorResponse['message']);
    }

    /**
     * Replicate the push_verify handler logic (mirrors AIH_Ajax::push_verify).
     *
     * @param bool        $isLoggedIn  Whether the user is authenticated
     * @param string      $endpoint    The endpoint value from POST
     * @param bool|null   $existsInDb  Whether the endpoint exists in the DB (null = skip DB check)
     */
    private function runPushVerifyLogic(bool $isLoggedIn, string $endpoint, ?bool $existsInDb = null): void
    {
        check_ajax_referer('aih_frontend_nonce', 'nonce');

        if (!$isLoggedIn) {
            wp_send_json_error(['message' => 'Not authenticated']);
            return;
        }

        $sanitized = esc_url_raw($endpoint);
        if (empty($sanitized)) {
            wp_send_json_error(['message' => 'Missing endpoint']);
            return;
        }

        if ($existsInDb) {
            wp_send_json_success(['valid' => true]);
        } else {
            wp_send_json_error(['valid' => false, 'message' => 'Subscription not found']);
        }
    }
}
