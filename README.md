# agtp-php

The PHP foundation for AGTP — two Composer packages, one repository.

| Package | Role |
|---|---|
| [`agtp/agtp-php`](agtp-php/) | The handler-author SDK. Defines `EndpointContext`, `EndpointResponse`, `EndpointError`, the `#[AgtpEndpoint]` attribute, the `HandlerRegistry`, the `ManifestExporter`, and the `Testing` helpers. |
| [`agtp/mod-php`](mod_php/) | The runtime gateway client. Connects to `agtpd` over a Unix socket or TCP loopback, performs the handshake, dispatches request frames to handlers registered in the SDK. |

The SDK has zero AGTP dependencies. `mod-php` depends on the SDK.
Framework connectors ([`agtp-symfony`](https://github.com/nomoticai/agtp-symfony),
[`agtp-drupal`](https://github.com/nomoticai/agtp-drupal), and others)
depend on both.

Both packages target PHP 8.1+ to match Drupal 10's floor.

## What is AGTP?

The Agent Transfer Protocol — an open protocol designed to move
agent traffic the way SMTP moves email and HTTP moves documents.
AGTP runs on its own port (4480) via a daemon called `agtpd`, with
cryptographic identity, scope-based authority, and signed attribution
as protocol-level primitives.

Specifications live at [github.com/nomoticai/agtp](https://github.com/nomoticai/agtp).

This repository is the PHP-side implementation: what application code
imports to author and serve AGTP endpoints.

## Quick links

- **Author handlers** → [agtp-php/README.md](agtp-php/README.md)
- **Run the runtime** → [mod_php/README.md](mod_php/README.md)
- **Drupal integration** → [agtp-drupal](https://github.com/nomoticai/agtp-drupal)
- **Symfony integration** → [agtp-symfony](https://github.com/nomoticai/agtp-symfony)
- **Standalone PHP** → see "Standalone usage" below

## Repository layout

```
agtp-php/
├── agtp-php/        # SDK — composer: agtp/agtp-php
│   ├── src/
│   ├── tests/
│   └── composer.json
├── mod_php/         # Runtime — composer: agtp/mod-php
│   ├── bin/
│   ├── src/
│   ├── tests/
│   └── composer.json
└── README.md        # This file
```

The two packages publish to Packagist independently. Inside the repo
they're wired with Composer path repositories so local development
resolves `mod-php` → `agtp-php` without round-tripping through
Packagist.

## Standalone usage

If you're not on Drupal, Symfony, Laravel, or another framework with
a dedicated connector, you can run AGTP as a standalone PHP process.

```bash
mkdir my-agtp-app && cd my-agtp-app
composer init --name=me/my-agtp-app --no-interaction
composer require agtp/agtp-php agtp/mod-php
```

Write a handler:

```php
// src/EchoHandler.php
namespace MyApp;

use Agtp\AgtpEndpoint;
use Agtp\EndpointContext;
use Agtp\EndpointResponse;

final class EchoHandler {
    #[AgtpEndpoint(method: 'QUERY', path: '/echo')]
    public function echo(EndpointContext $ctx): EndpointResponse {
        return new EndpointResponse(body: ['echo' => $ctx->input['value'] ?? '']);
    }
}
```

Write a bootstrap script:

```php
// bootstrap.php
require __DIR__ . '/vendor/autoload.php';
\Agtp\HandlerRegistry::default()->registerClass(\MyApp\EchoHandler::class);
```

Run:

```bash
php vendor/bin/run.php \
    --gateway-socket /var/run/agtpd/gateway.sock \
    --bootstrap bootstrap.php
```

## Performance: why a long-lived worker?

AGTP handlers run inside a persistent worker process. The Composer
autoloader, your handler classes, your framework's container (if any),
and any state your handler caches at construction time are paid for
**once** at worker startup. Every subsequent AGTP request reuses
them.

This is the FastCGI/PHP-FPM model applied to a different protocol:
nginx terminates HTTP, FPM serves long-lived PHP workers. Here, `agtpd`
terminates AGTP and `mod_php` workers serve handler logic.

For agent traffic — bursty, repetitive, hitting the same endpoints
many times in sequence — the warm-process model is a substantial
performance win over per-request bootstrapping.

## Testing

```bash
# Test the SDK
cd agtp-php && composer install && composer test

# Test the runtime
cd mod_php && composer install && composer test
```

Both packages use PHPUnit 10. The SDK has zero runtime dependencies;
`mod-php` depends only on the SDK.

## License

MIT.
