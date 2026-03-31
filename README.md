# phpdot/container-swoole

Swoole adapter for [phpdot/container](https://github.com/phpdot/container).

Each Swoole coroutine gets its own isolated service scope via `Coroutine::getContext()`. When the coroutine exits, Swoole destroys the context automatically — no manual cleanup required.

## Installation

```bash
composer require phpdot/container-swoole
```

## Usage

```php
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Swoole\SwooleContextProvider;
use function PHPdot\Container\singleton;
use function PHPdot\Container\scoped;

$container = (new ContainerBuilder())
    ->withContextProvider(new SwooleContextProvider())
    ->addDefinitions([
        // Shared across all coroutines
        Router::class  => singleton(),
        Redis::class   => singleton(),

        // Isolated per coroutine — fresh for each request
        Session::class       => scoped(),
        SignalManager::class => scoped(),
    ])
    ->build();
```

## How It Works

```
┌──────────────────────────────────────────────────────────┐
│ Swoole Worker                                            │
│                                                          │
│  Singletons (shared)                                     │
│  ┌────────────────────────────────────────────────────┐  │
│  │  Router       Redis       Config      LogBridge    │  │
│  └────────────────────────────────────────────────────┘  │
│                                                          │
│  Coroutine 1              Coroutine 2                    │
│  ┌──────────────────┐    ┌──────────────────┐           │
│  │ Session (User A)  │    │ Session (User B)  │           │
│  │ Signal (trace-1)  │    │ Signal (trace-2)  │           │
│  └──────────────────┘    └──────────────────┘           │
│    auto-destroyed           auto-destroyed               │
│    on coroutine exit        on coroutine exit             │
└──────────────────────────────────────────────────────────┘
```

**Singleton** services resolve once and are shared across all coroutines in the worker.

**Scoped** services resolve once per coroutine and are stored in `Swoole\Coroutine::getContext()`. When the coroutine finishes, Swoole's runtime destroys the context and all scoped instances are garbage collected.

**Outside a coroutine** (CLI bootstrap, `onStart` callback), the provider falls back to an in-memory `ArrayContext`.

## Server Example

```php
use Swoole\Http\Server;
use PHPdot\Container\ContainerBuilder;
use PHPdot\Container\Swoole\SwooleContextProvider;
use function PHPdot\Container\singleton;
use function PHPdot\Container\scoped;

$container = (new ContainerBuilder())
    ->withContextProvider(new SwooleContextProvider())
    ->addDefinitions([
        Config::class  => singleton(),
        Session::class => scoped(fn($c) => Session::fromRequest($c->get(Request::class))),
    ])
    ->build();

$server = new Server('0.0.0.0', 8080);

$server->on('request', function ($req, $res) use ($container) {
    // Each request runs in its own coroutine.
    // Scoped services are fresh. Singletons are shared.
    $session = $container->get(Session::class);

    $res->end('Hello ' . $session->user());
    // Coroutine ends here — scoped instances destroyed automatically.
});

$server->start();
```

## Requirements

- PHP >= 8.3
- ext-swoole >= 5.0
- [phpdot/container](https://github.com/phpdot/container) ^1.0

## License

MIT
