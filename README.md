# agtp-php

The PHP stack for the [Agent Transfer Protocol][agtp] — handler-author
library plus the gateway runtime module that connects to `agtpd`.

Two Composer packages live side-by-side in this repository:

| Directory  | Composer name      | What it is                                       |
|------------|--------------------|--------------------------------------------------|
| [`agtp-php/`](agtp-php/) | `agtp/agtp-php` | The handler-author API: `EndpointContext`, `EndpointResponse`, `EndpointError`, `#[AgtpEndpoint]`, `HandlerRegistry`, `Testing`. |
| [`mod_php/`](mod_php/)   | `agtp/mod-php`  | The gateway runtime module. Connects to `agtpd` over a TCP/Unix socket and dispatches AGTP requests to handlers registered through `agtp-php`. |

They version together. Pinning `agtp/agtp-php: ^x.y` and
`agtp/mod-php: ^x.y` to matching minors is the safe default.

## Who uses which package

- **You write a handler** → depend on `agtp/agtp-php` only. Your
  framework integration (Drupal / Symfony / Laravel / WordPress)
  pulls in `agtp/mod-php` transitively.
- **You run the gateway worker** → `vendor/bin/run.php` from
  `agtp/mod-php` (the framework integrations wrap this in their own
  CLI command — `drush agtp:serve`, `bin/console agtp:serve`,
  `php artisan agtp:serve`, `wp agtp serve`).
- **You build a framework integration that doesn't exist yet** →
  depend on both packages and use them directly.

## Framework integrations

Each integration lives in its own repository so a site only pulls
the framework it actually uses:

- [agtp-drupal][drupal] — Drupal 10.2+ / 11 module
- [agtp-symfony][symfony] — Symfony 6.4 / 7 bundle
- [agtp-laravel][laravel] — Laravel 10 / 11 package
- [agtp-wordpress][wp] — WordPress 6.4+ plugin

## Quick start (no framework)

```bash
composer require agtp/agtp-php agtp/mod-php
```

```php
// bootstrap.php
require __DIR__ . '/vendor/autoload.php';

use Agtp\AgtpEndpoint;
use Agtp\EndpointContext;
use Agtp\EndpointResponse;

#[AgtpEndpoint(method: 'QUERY', path: '/echo')]
function echoHandler(EndpointContext $ctx): EndpointResponse
{
    return new EndpointResponse(body: ['echo' => $ctx->input['value'] ?? '']);
}

\Agtp\HandlerRegistry::default()->registerFunction('echoHandler');
```

```bash
# Start agtpd separately (TCP/4480), then run the worker:
vendor/bin/run.php \
    --gateway-socket /tmp/agtpd.sock \
    --bootstrap bootstrap.php
```

`agtpd` itself lives in the [AGTP spec repo][agtp] (Python reference
implementation).

## Repository layout

```
agtp-php/                  agtp/agtp-php  (handler SDK)
├── src/                   Public PHP API
├── tests/                 PHPUnit unit tests
├── composer.json
└── README.md              ← detailed authoring guide

mod_php/                   agtp/mod-php   (runtime module)
├── src/                   Frame codec + GatewayClient
├── bin/run.php            CLI entry point (composer-published as a bin)
└── composer.json
```

## Development

```bash
git clone https://github.com/nomoticai/agtp-php
cd agtp-php

composer install --working-dir=agtp-php
composer install --working-dir=mod_php

# Unit tests for the SDK
(cd agtp-php && composer test)
```

End-to-end coverage (agtpd ↔ mod_php ↔ handler) lives in the spec
repo's `tests/test_gateway_e2e_php.py`. Point it at this checkout
with:

```bash
export AGTP_MOD_PHP_DIR=$(pwd)/mod_php
# or place this repo as a sibling of the spec repo as ../agtp-php
```

## Versioning

The two packages publish on the same release cadence and share a
single [`CHANGELOG.md`](agtp-php/CHANGELOG.md) in `agtp-php/`. The
public API is versioned independently of the AGTP wire format and
the gateway protocol — breaking changes wait for gateway protocol
v2; additive minor bumps land freely.

## Related

- [AGTP spec repo][agtp] — protocol drafts (IETF), `agtpd` reference
  daemon, cross-language conformance tests
- [Gateway protocol v1][gateway] — wire-level contract between
  `agtpd` and `mod_php`
- [Python equivalent][agtp-python] — `agtp` package on PyPI; same
  shape as `agtp/agtp-php`

[agtp]: https://github.com/nomoticai/agtp
[agtp-python]: https://github.com/nomoticai/agtp/tree/main/agtp
[gateway]: https://github.com/nomoticai/agtp/blob/main/docs/architecture/gateway-protocol-v1.md
[drupal]: https://github.com/nomoticai/agtp-drupal
[symfony]: https://github.com/nomoticai/agtp-symfony
[laravel]: https://github.com/nomoticai/agtp-laravel
[wp]: https://github.com/nomoticai/agtp-wordpress
