<?php

namespace PhpMiddlewareTest\HttpAuthentication;

use Exception;
use PhpMiddleware\HttpAuthentication\AuthenticationMiddleware;
use PhpMiddleware\HttpAuthentication\AuthorizationResultInterface;
use PhpMiddleware\HttpAuthentication\AuthorizationServiceInterface;
use PHPUnit_Framework_TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response;


class AuthenticationMiddlewareTest extends PHPUnit_Framework_TestCase
{
    protected $middleware;
    protected $result;
    protected $request;

    protected function setUp()
    {
        $this->result = $this->getMock(AuthorizationResultInterface::class);
        $service = $this->getMock(AuthorizationServiceInterface::class);
        $service->expects($this->once())->method('authorize')->willReturn($this->result);
        $this->middleware = new AuthenticationMiddleware($service);
        $this->request = $this->getMock(ServerRequestInterface::class);
    }

    public function testAuthenticated()
    {
        $this->result->expects($this->once())->method('isAuthorized')->willReturn(true);

        $this->make200Request($this->request);
    }

    public function testNotAuthenticatedWithoutChallange()
    {
        $this->result->expects($this->once())->method('isAuthorized')->willReturn(false);
        $this->result->expects($this->once())->method('getChallenge')->willReturn([]);
        $this->result->expects($this->once())->method('getScheme')->willReturn('Boo');

        $result = $this->make401Request($this->request);

        $value = $result->getHeaderLine('WWW-Authenticate');
        $this->assertSame('Boo', $value);
    }

    public function testNotAuthenticatedWithOneChallange()
    {
        $this->result->expects($this->once())->method('isAuthorized')->willReturn(false);
        $this->result->expects($this->once())->method('getChallenge')->willReturn([
            'test' => 'boo',
        ]);
        $this->result->expects($this->once())->method('getScheme')->willReturn('Boo');

        $result = $this->make401Request($this->request);

        $value = $result->getHeaderLine('WWW-Authenticate');
        $this->assertSame('Boo test="boo"', $value);
    }


    public function testNotAuthenticatedWithMultipleChallange()
    {
        $this->result->expects($this->once())->method('isAuthorized')->willReturn(false);
        $this->result->expects($this->once())->method('getChallenge')->willReturn([
            'test' => 'boo',
            'boo' => 'bar',
            'baz' => 'bar'
        ]);
        $this->result->expects($this->once())->method('getScheme')->willReturn('Goo');

        $result = $this->make401Request($this->request);

        $value = $result->getHeaderLine('WWW-Authenticate');
        $this->assertSame('Goo test="boo", boo="bar", baz="bar"', $value);
    }

    protected function make200Request(ServerRequestInterface $request)
    {
        $response = new Response();
        $called = false;

        $next = function ($request, $response) use (&$called) {
            $called = true;

            return $response;
        };

        /* @var $result ResponseInterface */
        $result = call_user_func($this->middleware, $request, $response, $next);

        $this->assertTrue($called, 'Next middleware not called');

        return $result;
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function make401Request(ServerRequestInterface $request)
    {
        $response = new Response();

        $next = function () {
            throw new Exception('next should not be called');
        };

        /* @var $result ResponseInterface */
        $result = call_user_func($this->middleware, $request, $response, $next);

        $this->assertSame(401, $result->getStatusCode());
        $this->assertNotNull($result->getHeaderLine('WWW-Authenticate'));

        return $result;
    }
}
