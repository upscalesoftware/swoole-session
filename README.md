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
$server->set([
    // Disable coroutines to safely access $_SESSION
    'enable_coroutine' => false,
]);
$server->on('request', new SessionMiddleware(function ($request, $response) {
    $_SESSION['data'] = $_SESSION['data'] ?? rand();
    $response->end($_SESSION['data']);
}));

$server->start();
```

## Limitations

### Coroutines

PHP sessions rely on the superglobal variable `$_SESSION` making them incompatible with the Swoole [coroutines](https://www.swoole.co.uk/coroutine).
When a request idles for an asynchronous I/O operation, its worker process is reused to handle other request(s).
Swoole switches the call stack context, but the superglobals stay in memory shared across coroutines/requests.
Session data loaded for one request leaks to other requests causing all sorts of data integrity issues.

Disable coroutines to safely use the PHP sessions:
```php
$server->set([
    'enable_coroutine' => false,
]);
```

### Output 

Direct output bypassing the response instance `\Swoole\Http\Response` is prohibited in the Swoole environment.
Writing to the standard output stream violates the [headers_sent](http://us3.php.net/headers_sent) requirement of the PHP session functions:
> PHP Warning:  session_start(): Cannot start session when headers already sent

Statements that "send headers" and hinder the sessions:
- `echo/print`
- `fwrite(STDOUT)`
- `file_put_contents('php://stdout')`
- `include 'template.phtml'`
- `header()`
- `setcookie/setrawcookie()`
- etc.

[Output buffering](https://www.php.net/manual/en/book.outcontrol.php) commonly used by template engines avoids this pitfall, for example:
```php
ob_start();
include $templatePhtml;
$output = ob_get_clean();

$response->end($output);
```

**Warning!** Coroutines used to "send headers" despite the output buffering until this has been [fixed](https://github.com/swoole/swoole-src/pull/3571) in Swoole 4.5.3.
This is not a problem since coroutines have to be disabled for the data integrity reasons discussed above. 

### Blocking

Concurrent requests are prone to the session write race conditions.
The default file-based session storage of PHP employs the filesystem locking to avoid the data corruption.
Requests of the same session ID execute sequentially blocking their respective worker processes from `session_start()` until `session_write_close()`. 

Asynchronous coroutine-aware libraries built specifically for Swoole:
- [itxiao6/session](https://github.com/itxiao6/session)
- [swoft-cloud/swoft-session](https://github.com/swoft-cloud/swoft-session)

## License

Licensed under the [Apache License, Version 2.0](https://github.com/upscalesoftware/swoole-session/blob/master/LICENSE.txt).