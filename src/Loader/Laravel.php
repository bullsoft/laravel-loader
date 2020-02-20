<?php
namespace BullSoft\Loader;

use Laravel\Passport\ApiTokenCookieFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Laravel 
{
    /**
     * @var \Illuminate\Foundation\Application
     */
    public $app;

    /**
     * @var \Illuminate\Foundation\Http\Kernel
     */
    public $kernel;

    /**
     * @var string
     */
    protected $sessionId;

    /**
     * @var \Illuminate\Http\Request
     */
    public $request; // ->duplicate()

    public static function autoload(string $basePath)
    {
        require_once $basePath . '/bootstrap/autoload.php';
    }

    public static function bootstrap(string $basePath)
    {
        $laravel = new self($basePath);
        $laravel->app->instance('request', $laravel->request->duplicate());
        $laravel->kernel->bootstrap();
        return $laravel;
    }

    private function __construct(string $basePath)
    {
        self::autoload($basePath);
        $this->app = require_once $basePath. '/bootstrap/app.php';
        $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class); 
        $this->request = Request::capture();
    }

    public function getSessionId($request) : string
    {
        // SessionId
        if(empty($this->sessionId)) {
            $sessionId = $request->cookies->get(env('COOKIE_NAME'));
            if(empty($sessionId)) {
                session()->regenerate();
                $sessionId = session()->getId(); // \Illuminate\Support\Str::random(40);
            }
            $this->sessionId = $sessionId;
        }
        return $this->sessionId;
    }

    protected function syncToken($request) : string
    {
        $sessionId = $this->getSessionId($request);
        $handler = session()->getHandler();
        $sesionArr = unserialize($handler->read($sessionId));
        $token = $sesionArr['_token'] ?? null;
        if(is_null($token)) {
            session()->regenerateToken();
            $token = session()->token();
            $sesionArr['_token'] = $token;
            $handler->write($sessionId, serialize($sesionArr));
        }
        return $token;
    }

    public function login(array $info, ?string $redirectUrl = null) : string
    {
        $request = $this->newRequest('/login', 'POST');
        $response = $this->sendRequest($request);
        // fill POST data
        foreach($info as $k => $v) {
            $request->request->set($k, $v);
        }
        return $this->finishWithCookies($request, $response, $redirectUrl);
    }

    public function checkUserLogin(string $uri = '/', $method = 'GET') : bool
    {
        $request = $this->newRequest($uri, $method);
        $response = $this->sendRequest($request);
        $this->finish($request, $response);
        return Auth::check();
    }

    public function newApiRequest(string $uri, string $method = 'GET') : Request
    {
        $request = $this->request->duplicate();
        $request->server->set('REQUEST_METHOD', strtoupper($method));
        $request->server->set('REQUEST_URI', $uri);
        $token = $this->syncToken($request);
        $apiToken = new ApiTokenCookieFactory(app('config'), app('encrypter'));
        $apiTokenCookie = $apiToken->make(Auth::id(), $token);
        $request->headers->set('X-CSRF-TOKEN', $token);
        $request->cookies->set($apiTokenCookie->getName(), encrypt($apiTokenCookie->getValue()));
        $request->headers->set("X-Requested-With", 'XMLHttpRequest');
        $sessionId = $this->getSessionId($request);
        $request->cookies->set(env('COOKIE_NAME'), $sessionId);
        return $request;
    }

    public function newRequest(string $uri, string $method = 'GET') : Request
    {
        $request = $this->request->duplicate();
        $request->server->set('REQUEST_METHOD', strtoupper($method));
        $request->server->set('REQUEST_URI', $uri);
        $token = $this->syncToken($request);
        $request->request->set('_token', $token);
        $sessionId = $this->getSessionId($request);
        $request->cookies->set(env('COOKIE_NAME'), $sessionId);
        return $request;
    }

    public function sendRequest($request) : Response
    {
        return $this->kernel->handle($request);
    }

    public function finish($request, $response) : string
    {
        $cont = $response->getContent();
        $this->kernel->terminate($request, $response);
        return $cont;
    }

    public function finishWithCookies($request, $response, ?string $redirectUrl = null) : string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if($cookie->getName() == env('COOKIE_NAME')) {
                $this->sessionId = $cookie->getValue();
            }
            header('Set-Cookie: '.$cookie, false, 200);
            $this->request->cookies->set($cookie->getName(), $cookie->getValue());
        }
        if(!empty($redirectUrl)) {
            header("Location: {$redirectUrl}", true, 302);
        } else {
            header(sprintf('HTTP/%s %s %s', '1.1', "200", "OK"), true, 200);
        }
        $cont = $response->getContent();
        $this->kernel->terminate($request, $response);
        return $cont;	
    }
}
