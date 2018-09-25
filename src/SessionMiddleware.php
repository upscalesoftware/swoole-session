<?php
namespace Upscale\Swoole\Session;

class SessionMiddleware
{
    /**
     * @var callable
     */
    protected $middleware;

    /**
     * Inject dependencies
     * 
     * @param callable $middleware function (\Swoole\Http\Request $request, \Swoole\Http\Response $response)
     */
    public function __construct(callable $middleware)
    {
        $this->middleware = $middleware;
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
        $sessionId = isset($request->cookie[$sessionName]) ? $request->cookie[$sessionName] : session_create_id();
        session_id($sessionId);
        session_start();
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
        try {
            call_user_func($this->middleware, $request, $response);
        } finally {
            session_write_close();
        }
    }
}