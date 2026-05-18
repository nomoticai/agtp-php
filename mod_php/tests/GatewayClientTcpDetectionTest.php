<?php

declare(strict_types=1);

namespace Agtp\ModPhp\Tests;

use Agtp\ModPhp\GatewayClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GatewayClient::isTcpTarget().
 *
 * The earlier implementation conflated "starts with 127.0.0.1:" with
 * TCP detection. The current implementation is general: anything of
 * the shape ``host:port`` (no slashes, valid port) is TCP; everything
 * else is treated as a Unix socket path.
 */
final class GatewayClientTcpDetectionTest extends TestCase
{
    /**
     * @dataProvider tcpTargets
     */
    public function testRecognizesTcpTargets(string $target): void
    {
        $this->assertTrue(
            GatewayClient::isTcpTarget($target),
            "expected TCP for: {$target}"
        );
    }

    public static function tcpTargets(): array
    {
        return [
            'loopback ipv4'         => ['127.0.0.1:4481'],
            'localhost name'        => ['localhost:4481'],
            'remote ipv4'           => ['10.0.0.5:4481'],
            'remote hostname'       => ['agtpd.internal:4481'],
            'explicit tcp scheme'   => ['tcp://127.0.0.1:4481'],
            'high port'             => ['localhost:65535'],
            'low port'              => ['localhost:1'],
        ];
    }

    /**
     * @dataProvider unixTargets
     */
    public function testRecognizesUnixTargets(string $target): void
    {
        $this->assertFalse(
            GatewayClient::isTcpTarget($target),
            "expected Unix socket for: {$target}"
        );
    }

    public static function unixTargets(): array
    {
        return [
            'absolute path'                  => ['/var/run/agtpd/gateway.sock'],
            'relative path'                  => ['./gateway.sock'],
            'tmp path'                       => ['/tmp/agtpd.sock'],
            'explicit unix scheme'           => ['unix:///var/run/agtpd/gateway.sock'],
            'path containing colon and slash'=> ['/var/run/with:colon/gateway.sock'],
            'bare filename'                  => ['gateway.sock'],
            'host with non-numeric port'     => ['localhost:abc'],
            'port out of range high'         => ['localhost:99999'],
            'port out of range zero'         => ['localhost:0'],
            'no colon at all'                => ['localhost'],
            'windows style path'             => ['C:\\agtpd\\gateway.sock'],
        ];
    }
}
