# Changelog

## [Unreleased]

### Added
- PHPUnit test suite for `FrameCodec` and `GatewayClient::isTcpTarget()`.
- `phpunit.xml` configuration.
- `composer test` script.

### Fixed
- TCP/Unix socket auto-detection in `GatewayClient::connect()` now correctly handles any `host:port` form. The previous regex effectively only matched targets starting with `127.0.0.1:`. The new `GatewayClient::isTcpTarget()` recognizes any `host:port` shape (no slashes, valid 1-65535 port), plus explicit `tcp://` / `unix://` scheme prefixes.

## [0.1.0]

Initial release. Gateway protocol v1 client. Connects to `agtpd` over Unix socket or TCP loopback, performs handshake, receives endpoint registration, dispatches request frames to handlers in the `agtp-php` `HandlerRegistry`.
