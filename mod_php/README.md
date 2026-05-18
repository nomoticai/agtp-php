# mod_php

The PHP runtime module for AGTP. Connects to `agtpd` over the gateway
socket (TCP or Unix domain) and dispatches AGTP requests to handlers
registered in [`agtp/agtp-php`][sdk].

This is the PHP counterpart to `mod_python`, `mod_go`, `mod_node`, and
`mod_rust` in the [spec repo][spec-repo]. They all speak the same
[gateway protocol v1][gateway] — picking PHP is a matter of which
language your handlers are written in.

## Install

```bash
composer require agtp/mod-php
```

`agtp/mod-php` pulls in `agtp/agtp-php` automatically — you only
depend on `mod-php` directly when you're operating the gateway worker
yourself. Framework integrations (Drupal, Symfony, Laravel, WordPress)
already depend on it.

## Running

```bash
vendor/bin/run.php \
    --gateway-socket /var/run/agtpd/gateway.sock \
    --bootstrap /path/to/your/bootstrap.php
```

`bootstrap.php` is your responsibility: it requires the Composer
autoloader and registers your handlers via
`\Agtp\HandlerRegistry::default()->...`. See the [SDK README][sdk-readme]
for handler-authoring patterns.

### Flags

| Flag                 | Required? | Default     | What                                                |
|----------------------|-----------|-------------|-----------------------------------------------------|
| `--gateway-socket`   | yes       | —           | TCP host:port or Unix socket path agtpd is listening on |
| `--bootstrap`        | recommended | —         | PHP file to load before serving (your registrations) |
| `--module-id`        | no        | `mod_php`   | Identifier announced during the gateway handshake   |

### Production deployment

Run under a process supervisor (systemd, supervisord, Docker
`restart: always`). Multiple workers can connect to the same `agtpd`
and traffic distributes across them.

```ini
[Service]
Type=simple
User=www-data
ExecStart=/usr/bin/php /var/www/example.com/vendor/bin/run.php \
    --gateway-socket=/var/run/agtpd/gateway.sock \
    --bootstrap=/var/www/example.com/agtp-bootstrap.php
Restart=on-failure
RestartSec=5s
```

## What's inside

- `src/FrameCodec.php` — length-prefixed JSON framing on top of a
  raw byte stream
- `src/GatewayClient.php` — handshake, registration, request
  dispatch, graceful shutdown; the read-loop that ports
  `mod_python.client.GatewayClient` value-for-value
- `src/ModuleException.php`, `FrameDecodeException.php`,
  `FrameTooLargeException.php` — typed errors so callers can
  distinguish protocol breakage from handler bugs
- `bin/run.php` — published as a Composer `bin` entry

## Related

- [`agtp/agtp-php`][sdk] — the handler-author library this module
  serves traffic for
- [Gateway protocol v1][gateway] — wire-level spec
- [`agtp` spec repo][spec-repo] — `agtpd` reference daemon and
  cross-language conformance tests

[sdk]: https://packagist.org/packages/agtp/agtp-php
[sdk-readme]: ../agtp-php/README.md
[spec-repo]: https://github.com/nomoticai/agtp
[gateway]: https://github.com/nomoticai/agtp/blob/main/docs/architecture/gateway-protocol-v1.md
