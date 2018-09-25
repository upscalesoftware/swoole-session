<?php
namespace Upscale\Swoole\Session;

class SessionMiddleware
{
    /**
     * @var callable
     */
    protected $middleware;

    /**
     * @var bool
     */
    protected $useCookies;

    /**
     * @var bool
     */
    protected $useOnlyCookies;

    /**
     * Inject dependencies
     *
     * @param callable $middleware function (\Swoole\Http\Request $request, \Swoole\Http\Response $response)
     * @param bool|null $useCookies
     * @param bool|null $useOnlyCookies
     */
    public function __construct(callable $middleware, $useCookies = null, $useOnlyCookies = null)
    {
        $this->middleware = $middleware;
        $this->useCookies = is_null($useCookies) ? (bool)ini_get('session.use_cookies') : $useCookies;
        $this->useOnlyCookies = is_null($useOnlyCookies) ? (bool)ini_get('session.use_only_cookies') : $useOnlyCookies;
    }

    /**
     * Delegate execution to the underlying middleware wrapping it into the session start/stop calls
     *
     * @param \Swoole\Http\Request $request
     * @param \Swoole\Http\Response $response
     */
    public function __invoke(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        $sessionName = session_name();
        if ($this->useCookies && isset($request->cookie[$sessionName])) {
            $sessionId = $request->cookie[$sessionName];
        } else if (!$this->useOnlyCookies && isset($request->get[$sessionName])) {
            $sessionId = $request->get[$sessionName];
        } else {
            $sessionId = session_create_id();
        }
        session_id($sessionId);
        session_start();
        if ($this->useCookies) {
            $cookie = session_get_cookie_params();
            $response->rawcookie(
                $sessionName,
                $sessionId,
                $cookie['lifetime'] ? time() + $cookie['lifetime'] : null,
                $cookie['path'],
                $cookie['domain'],
                $cookie['secure'],
                $cookie['httponly']
            );
        }
        try {
            call_user_func($this->middleware, $request, $response);
        } finally {
            session_write_close();
        }
    }
}