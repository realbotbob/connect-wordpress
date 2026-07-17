<?php

declare(strict_types=1);

namespace Tropikal\Connect\WordPress\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Tropikal\Connect\WordPress\Exception\OAuthException;
use Tropikal\Connect\WordPress\Setup\WpHttp;

/**
 * The OAuth/control-plane client must never send the authorization code, bearer
 * token, or refresh token over cleartext.
 */
final class WpHttpTest extends TestCase
{
    public function testRejectsPlainHttpEndpoint(): void
    {
        $this->expectException(OAuthException::class);
        (new WpHttp)->postForm('http://id.example.test/oauth/token', ['grant_type' => 'authorization_code']);
    }

    public function testAllowsHttpsEndpoint(): void
    {
        // wp_remote_post is stubbed to return a 200 JSON body; https must pass
        // the scheme guard and reach it.
        $result = (new WpHttp)->postForm('https://id.example.test/oauth/token', ['grant_type' => 'authorization_code']);
        $this->assertIsArray($result);
    }

    public function testAllowsHttpOnlyForLoopback(): void
    {
        $result = (new WpHttp)->postForm('http://127.0.0.1:8899/token', ['grant_type' => 'authorization_code']);
        $this->assertIsArray($result);
    }
}
