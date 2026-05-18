<?php

declare(strict_types=1);

namespace Agtp\Tests;

use Agtp\AgtpEndpoint;
use Agtp\EndpointContext;
use Agtp\EndpointResponse;
use Agtp\HandlerRegistry;
use Agtp\ManifestExporter;
use PHPUnit\Framework\TestCase;

final class ManifestExporterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        HandlerRegistry::resetDefault();
        $this->tmpDir = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'agtp-export-test-' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }
        HandlerRegistry::resetDefault();
    }

    public function testRendersClassMethodHandler(): void
    {
        $registry = HandlerRegistry::default();
        $registry->registerInstance(new ManifestExporterTestFixtureHandlers());
        $exporter = new ManifestExporter($registry);

        $all = $registry->all();
        $this->assertCount(2, $all);
        $toml = $exporter->render($all[0]);

        // The order returned from registry is registration order;
        // the BOOK /room handler is first in the fixture class.
        $this->assertStringContainsString('[endpoint]', $toml);
        $this->assertStringContainsString('method = "BOOK"', $toml);
        $this->assertStringContainsString('path = "/room"', $toml);
        $this->assertStringContainsString('errors = ["room_unavailable"]', $toml);
        $this->assertStringContainsString('required_scopes = ["booking:write"]', $toml);
        $this->assertStringContainsString('[endpoint.handler]', $toml);
        $this->assertStringContainsString('type = "registered_function"', $toml);
        $this->assertStringContainsString(
            'function = "Agtp\\\\Tests\\\\ManifestExporterTestFixtureHandlers::book"',
            $toml
        );
    }

    public function testRendersGlobalFunctionHandler(): void
    {
        require_once __DIR__ . '/_fixtures/decorated_function.php';
        $registry = HandlerRegistry::default();
        $registry->registerFunction('Agtp\\Tests\\_decoratedEcho');
        $exporter = new ManifestExporter($registry);

        $toml = $exporter->render($registry->all()[0]);
        $this->assertStringContainsString('method = "QUERY"', $toml);
        $this->assertStringContainsString('path = "/echo"', $toml);
        // Global function reference — no class.
        $this->assertStringContainsString('function = "Agtp\\\\Tests\\\\_decoratedEcho"', $toml);
    }

    public function testRendersAnonymousClosureWithMarker(): void
    {
        $registry = HandlerRegistry::default();
        $registry->register(
            handler: function (EndpointContext $ctx): EndpointResponse {
                return new EndpointResponse(body: []);
            },
            method: 'QUERY',
            path: '/anon',
        );
        $exporter = new ManifestExporter($registry);

        $toml = $exporter->render($registry->all()[0]);
        $this->assertStringContainsString('function = "__closure__"', $toml);
        $this->assertStringContainsString('anonymous closure', $toml);
    }

    public function testFilenameConvention(): void
    {
        $registry = HandlerRegistry::default();
        $registry->register(
            handler: fn(EndpointContext $ctx) => new EndpointResponse(body: []),
            method: 'BOOK',
            path: '/room/double/king',
        );
        $exporter = new ManifestExporter($registry);

        $this->assertSame('book_room-double-king.toml', $exporter->filenameFor($registry->all()[0]));
    }

    public function testFilenameForRootPath(): void
    {
        $registry = HandlerRegistry::default();
        $registry->register(
            handler: fn(EndpointContext $ctx) => new EndpointResponse(body: []),
            method: 'DESCRIBE',
            path: '/',
        );
        $exporter = new ManifestExporter($registry);

        $this->assertSame('describe_root.toml', $exporter->filenameFor($registry->all()[0]));
    }

    public function testWriteToDirectoryCreatesFiles(): void
    {
        $registry = HandlerRegistry::default();
        $registry->registerInstance(new ManifestExporterTestFixtureHandlers());
        $exporter = new ManifestExporter($registry);

        $written = $exporter->writeToDirectory($this->tmpDir);

        $this->assertCount(2, $written);
        foreach ($written as $path) {
            $this->assertFileExists($path);
            $this->assertStringStartsWith($this->tmpDir, $path);
            $this->assertStringContainsString('[endpoint]', file_get_contents($path) ?: '');
        }

        // Files use the expected naming convention.
        $names = array_map('basename', $written);
        sort($names);
        $this->assertSame(['book_room.toml', 'describe_room.toml'], $names);
    }

    public function testRenderAllConcatenates(): void
    {
        $registry = HandlerRegistry::default();
        $registry->registerInstance(new ManifestExporterTestFixtureHandlers());
        $exporter = new ManifestExporter($registry);

        $combined = $exporter->renderAll();
        $this->assertStringContainsString('method = "BOOK"', $combined);
        $this->assertStringContainsString('method = "DESCRIBE"', $combined);
        $this->assertStringContainsString('# book_room.toml', $combined);
        $this->assertStringContainsString('# describe_room.toml', $combined);
    }

    public function testTomlEscapingHandlesQuotesAndBackslashes(): void
    {
        $registry = HandlerRegistry::default();
        $registry->register(
            handler: fn(EndpointContext $ctx) => new EndpointResponse(body: []),
            method: 'QUERY',
            path: '/x',
            description: 'has "quotes" and \\ backslashes',
        );
        $exporter = new ManifestExporter($registry);

        $toml = $exporter->render($registry->all()[0]);
        $this->assertStringContainsString(
            'description = "has \"quotes\" and \\\\ backslashes"',
            $toml
        );
    }
}

/**
 * Fixture used by ManifestExporterTest to exercise class-method
 * reflection. Lives in this file so the test's expected FQCN string
 * is unambiguous.
 */
final class ManifestExporterTestFixtureHandlers
{
    #[AgtpEndpoint(
        method: 'BOOK',
        path: '/room',
        errors: ['room_unavailable'],
        requiredScopes: ['booking:write'],
        description: 'Books a room for the named guest.',
    )]
    public function book(EndpointContext $ctx): EndpointResponse
    {
        return new EndpointResponse(body: ['reservation_id' => 'res-1']);
    }

    #[AgtpEndpoint(method: 'DESCRIBE', path: '/room')]
    public function describe(EndpointContext $ctx): EndpointResponse
    {
        return new EndpointResponse(body: ['available_types' => ['double', 'king']]);
    }
}
