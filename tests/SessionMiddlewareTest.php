<?php
/**
 * Copyright © Upscale Software. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Upscale\Swoole\Session\Tests;

use Upscale\Swoole\Session\SessionMiddleware;

class SessionMiddlewareTest extends \Upscale\Swoole\Launchpad\Tests\TestCase
{
    /**
     * @var \Swoole\Http\Server
     */
    protected $server;

    /**
     * @var string[]
     */
    protected $cookieFilenames = [];

    protected function setUp()
    {
        parent::setUp();

        $this->server = new \Swoole\Http\Server('127.0.0.1', 8080);
        $this->server->set([
            'log_file' => '/dev/null',
            'log_level' => 4,
            'worker_num' => 3,
            'dispatch_mode' => 1,
        ]);
        $this->server->on('request', new SessionMiddleware($this));

        $this->spawn($this->server);
    }

    protected function tearDown()
    {
        parent::tearDown();
        
        foreach ($this->cookieFilenames as $filename) {
            unlink($filename);
        }
    }

    public function __invoke(\Swoole\Http\Request $request, \Swoole\Http\Response $response)
    {
        $_SESSION += (array)$request->post;
        $response->end(json_encode($_SESSION, JSON_FORCE_OBJECT));
    }

    public function testSessionCookie()
    {
        $sessionOne = $this->createCookieJar('test');
        $sessionTwo = $this->createCookieJar('test');

        $result = $this->curl('http://127.0.0.1:8080/', $sessionOne + $this->buildPost(['data' => 'FIXTURE1']));
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{"data":"FIXTURE1"}', $result);

        $result = $this->curl('http://127.0.0.1:8080/', $sessionTwo + $this->buildPost(['data' => 'FIXTURE2']));
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{"data":"FIXTURE2"}', $result);

        $result = $this->curl('http://127.0.0.1:8080/', $sessionOne + $this->buildPost(['extra' => 'TEST1']));
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{"data":"FIXTURE1","extra":"TEST1"}', $result);

        $result = $this->curl('http://127.0.0.1:8080/');
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{}', $result);

        $result = $this->curl('http://127.0.0.1:8080/', $sessionOne);
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{"data":"FIXTURE1","extra":"TEST1"}', $result);

        $result = $this->curl('http://127.0.0.1:8080/', $sessionTwo);
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{"data":"FIXTURE2"}', $result);
    }

    public function testSessionQueryString()
    {
        $sidOne = session_create_id('test');
        $sidTwo = session_create_id('test');
        
        $result = $this->curl("http://127.0.0.1:8080/?PHPSESSID=$sidOne", $this->buildPost(['data' => 'FIXTURE1']));
        $this->assertContains("Set-Cookie: PHPSESSID=$sidOne;", $result);
        $this->assertStringEndsWith('{"data":"FIXTURE1"}', $result);

        $result = $this->curl("http://127.0.0.1:8080/?PHPSESSID=$sidTwo", $this->buildPost(['data' => 'FIXTURE2']));
        $this->assertContains("Set-Cookie: PHPSESSID=$sidTwo;", $result);
        $this->assertStringEndsWith('{"data":"FIXTURE2"}', $result);

        $result = $this->curl("http://127.0.0.1:8080/?PHPSESSID=$sidOne", $this->buildPost(['extra' => 'TEST1']));
        $this->assertContains("Set-Cookie: PHPSESSID=$sidOne;", $result);
        $this->assertStringEndsWith('{"data":"FIXTURE1","extra":"TEST1"}', $result);

        $result = $this->curl('http://127.0.0.1:8080/');
        $this->assertContains('Set-Cookie: PHPSESSID=', $result);
        $this->assertStringEndsWith('{}', $result);

        $result = $this->curl("http://127.0.0.1:8080/?PHPSESSID=$sidOne");
        $this->assertContains("Set-Cookie: PHPSESSID=$sidOne;", $result);
        $this->assertStringEndsWith('{"data":"FIXTURE1","extra":"TEST1"}', $result);
        
        $result = $this->curl("http://127.0.0.1:8080/?PHPSESSID=$sidTwo");
        $this->assertContains("Set-Cookie: PHPSESSID=$sidTwo;", $result);
        $this->assertStringEndsWith('{"data":"FIXTURE2"}', $result);
    }

    protected function createCookieJar($prefix)
    {
        $filename = tempnam(sys_get_temp_dir(), $prefix);
        $this->cookieFilenames[] = $filename;
        return [
            CURLOPT_COOKIEJAR => $filename,
            CURLOPT_COOKIEFILE => $filename,
        ];
    }

    protected function buildPost(array $data)
    {
        return [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
        ];
    }
}