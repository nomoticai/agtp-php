<?php

declare(strict_types=1);

namespace Agtp\ModPhp\Tests;

use Agtp\ModPhp\FrameCodec;
use Agtp\ModPhp\FrameDecodeException;
use Agtp\ModPhp\FrameTooLargeException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for the gateway-protocol framing codec. Mirrors the Python
 * test suite at tests/test_gateway_protocol.py.
 *
 * Uses php://memory streams for read/write so we exercise the same
 * code paths a real socket would, without the OS in the way.
 */
final class FrameCodecTest extends TestCase
{
    /**
     * @return resource
     */
    private function memStream()
    {
        $stream = fopen('php://memory', 'r+b');
        if ($stream === false) {
            throw new RuntimeException('failed to open php://memory');
        }
        return $stream;
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $stream = $this->memStream();
        FrameCodec::writeFrame($stream, ['type' => 'hello', 'version' => '1.0']);
        rewind($stream);
        $frame = FrameCodec::readFrame($stream);

        $this->assertSame('hello', $frame['type']);
        $this->assertSame('1.0', $frame['version']);
    }

    public function testWriteFrameUses4ByteBigEndianLengthPrefix(): void
    {
        $stream = $this->memStream();
        FrameCodec::writeFrame($stream, ['type' => 'ping']);
        rewind($stream);

        $raw = stream_get_contents($stream);
        $this->assertNotFalse($raw);
        $this->assertGreaterThan(4, strlen($raw));

        $lengthHeader = substr($raw, 0, 4);
        $unpacked = unpack('N', $lengthHeader);
        $this->assertIsArray($unpacked);
        $announcedLength = $unpacked[1];

        $body = substr($raw, 4);
        $this->assertSame($announcedLength, strlen($body));

        $decoded = json_decode($body, true);
        $this->assertSame('ping', $decoded['type']);
    }

    public function testReadRefusesEmptyFrame(): void
    {
        $stream = $this->memStream();
        // Length prefix 0 with no body.
        fwrite($stream, pack('N', 0));
        rewind($stream);

        $this->expectException(FrameDecodeException::class);
        $this->expectExceptionMessage('empty frame');
        FrameCodec::readFrame($stream);
    }

    public function testReadRefusesNonObjectPayload(): void
    {
        $stream = $this->memStream();
        $body = '[]';
        fwrite($stream, pack('N', strlen($body)) . $body);
        rewind($stream);

        $this->expectException(FrameDecodeException::class);
        $this->expectExceptionMessage('JSON object');
        FrameCodec::readFrame($stream);
    }

    public function testReadRefusesPayloadWithoutTypeField(): void
    {
        $stream = $this->memStream();
        $body = '{"foo": "bar"}';
        fwrite($stream, pack('N', strlen($body)) . $body);
        rewind($stream);

        $this->expectException(FrameDecodeException::class);
        $this->expectExceptionMessage("'type'");
        FrameCodec::readFrame($stream);
    }

    public function testReadRefusesMalformedJson(): void
    {
        $stream = $this->memStream();
        $body = '{"type": "hello"';  // truncated
        fwrite($stream, pack('N', strlen($body)) . $body);
        rewind($stream);

        $this->expectException(FrameDecodeException::class);
        $this->expectExceptionMessage('valid JSON');
        FrameCodec::readFrame($stream);
    }

    public function testReadRefusesOversizedFrame(): void
    {
        $stream = $this->memStream();
        // Announce a length larger than MAX_FRAME_SIZE.
        fwrite($stream, pack('N', FrameCodec::MAX_FRAME_SIZE + 1));
        rewind($stream);

        $this->expectException(FrameTooLargeException::class);
        FrameCodec::readFrame($stream);
    }

    public function testWriteRefusesPayloadWithoutType(): void
    {
        $stream = $this->memStream();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("'type'");
        FrameCodec::writeFrame($stream, ['no_type_field' => true]);
    }

    public function testReadDetectsTruncationMidFrame(): void
    {
        $stream = $this->memStream();
        // Announce 100 bytes, supply 10.
        fwrite($stream, pack('N', 100) . '{partial');
        rewind($stream);

        $this->expectException(FrameDecodeException::class);
        $this->expectExceptionMessage('mid-frame');
        FrameCodec::readFrame($stream);
    }

    public function testWriteHandlesUtf8MultibyteContent(): void
    {
        $stream = $this->memStream();
        $payload = ['type' => 'echo', 'value' => 'héllo 世界 🚀'];
        FrameCodec::writeFrame($stream, $payload);
        rewind($stream);

        $frame = FrameCodec::readFrame($stream);
        $this->assertSame('héllo 世界 🚀', $frame['value']);
    }

    public function testMultipleFramesAreReadableInOrder(): void
    {
        $stream = $this->memStream();
        FrameCodec::writeFrame($stream, ['type' => 'first', 'n' => 1]);
        FrameCodec::writeFrame($stream, ['type' => 'second', 'n' => 2]);
        FrameCodec::writeFrame($stream, ['type' => 'third', 'n' => 3]);
        rewind($stream);

        $this->assertSame(1, FrameCodec::readFrame($stream)['n']);
        $this->assertSame(2, FrameCodec::readFrame($stream)['n']);
        $this->assertSame(3, FrameCodec::readFrame($stream)['n']);
    }
}
