PHP Sessions for Swoole
=======================

This library implements compatibility of the native [PHP sessions](http://us3.php.net/manual/en/book.session.php) with the [Swoole](https://www.swoole.co.uk/) web-server.

**Features:**
- Transparent session start/stop
- Session ID in cookies or query string
- Native or custom session ID generator
- Automatic session data persistence
- Compliance with PHP [session configuration](http://us3.php.net/manual/en/session.configuration.php)

## Installation

The library is to be installed via [Composer](https://getcomposer.org/) as a dependency:
```bash
composer require upscale/swoole-session
```
## Usage

Wrap your Swoole request handler into the session middleware:
```php
require 'vendor/autoload.php';

use Upscale\Swoole\Session\SessionMiddleware;

$server = new \Swoole\Http\Server('127.0.0.1', 8080);

$server->on('request', new SessionMiddleware(function ($request, $response) {
    $_SESSION['data'] = $_SESSION['data'] ?? rand();
    $response->end($_SESSION['data']);
}));

$server->start();
```

## Caveats

Direct output bypassing Swoole response is prohibited.
Writing to the standard output stream violates the [headers_sent()](http://us3.php.net/headers_sent) requirement of the PHP session functions.

PHP sessions are synchronous and blocking by design as [explained](https://github.com/swoole/swoole-src/issues/1828#issuecomment-407611525) by the Swoole team.
Asynchronous libraries, such as [itxiao6/session](https://github.com/itxiao6/session) or [swoft-cloud/swoft-session](https://github.com/swoft-cloud/swoft-session), built specifically for Swoole are recommended instead.

## License

Licensed under the [Apache License, Version 2.0](http://www.apache.org/licenses/LICENSE-2.0).