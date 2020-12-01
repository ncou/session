<?php

declare(strict_types=1);

namespace Chiron\Session\Middleware;

use Chiron\Cookies\Cookie;
use Chiron\Session\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

//https://github.com/nuevephp/laravel-session/blob/master/src/Middleware.php
//https://github.com/odan/session/blob/master/src/Middleware/SessionMiddleware.php

// EXEMPLE sans les cookies => https://github.com/kevinsimard/laravel-cookieless-session/blob/master/src/Middleware/StartSession.php

//https://github.com/illuminate/session

// Exemple node.js sur sign/unsign un cookie.
//https://github.com/balderdashy/sails/blob/53d0473c2876b1925136f777cb51ac9eda5b24aa/lib/hooks/session/index.js#L481
//https://github.com/expressjs/cookie-parser/blob/master/index.js#L129


final class SessionMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->startSession($request);

        $request = $request->withAttribute(Session::ATTRIBUTE, $session);

        $response = $handler->handle($request);

        $this->closeSession($session);
        //$response = $this->addCookieToResponse($response, $session);

        $cookie = $this->createCookie($session->getName(), $session->getId());

        $response = $response->withAddedHeader('Set-Cookie', $cookie->toHeaderValue());

        return $response;
    }

    /**
     * Start the session for the given request.
     *
     * @param ServerRequestInterface $request
     *
     * @return Session
     */
    private function startSession(ServerRequestInterface $request): Session
    {
        $session = $this->getSession($request);

        $session->start();

        return $session;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return Session
     */
    private function getSession(ServerRequestInterface $request): Session
    {
        //$session = $this->manager->driver();
        $session = new Session();

        //$cookieData = FigRequestCookies::get($request, $session->getName());
        //$id = $cookieData->getValue();

        $id = $this->fetchSessionId($request);

        $session->setId($id);

        return $session;
    }

    /**
     * Attempt to locate session ID in request.
     *
     * @param ServerRequestInterface $request
     *
     * @return string|null
     */
    private function fetchSessionId(ServerRequestInterface $request): ?string
    {
        $cookies = $request->getCookieParams();

        if (empty($cookies['SID'])) {
            return null;
        }

        return $cookies['SID'];
    }

    /**
     * Close the session handling for the request.
     *
     * @param  \Illuminate\Session\SessionInterface $session
     *
     * @return void
     */
    private function closeSession(Session $session)
    {
        $session->save();

        $this->collectGarbage($session);
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param  Session $session
     *
     * @return void
     */
    private function collectGarbage(Session $session): void
    {
        //$config = $this->manager->getSessionConfig();

        // We must manually sweep the storage location to get rid of old sessions from storage.
        // Here are the chances that it will happen on a given request.
        // By default, the odds are 2 out of 100.
        $config['lottery'] = [2, 100];

        // Here we will see if this request hits the garbage collection lottery by hitting
        // the odds needed to perform garbage collection on any given request. If we do
        // hit it, we'll call this handler to let it delete all the expired sessions.
        if ($this->configHitsLottery($config)) {
            $session->getHandler()->gc($this->getLifetimeSeconds()); // TODO : eventuellement créer une proxyméthode pour qu'on puisse appeller la méthode gc() directement depuis la classe Session.
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array $config
     *
     * @return bool
     */
    // TODO : remonter cette fonction dans la classe sessionConfig::class, et la renommer en garbageCollectorHitsLotterie()
    private function configHitsLottery(array $config): bool
    {
        return mt_rand(1, $config['lottery'][1]) <= $config['lottery'][0];
    }

    /**
     * @param ResponseInterface $response
     * @param Session $session
     *
     * @return ResponseInterface
     */
    // TODO : code à virer !!!!
    private function addCookieToResponse(ResponseInterface $response, Session $session)
    {
        $s = $session;

        $secure = array_get($c, 'secure', false);

        $setCookie = SetCookie::create($s->getName())
            ->withValue($s->getId())
            ->withExpires($this->getCookieLifetime())
            ->withDomain($c['domain'])
            ->withPath($c['path'])
            ->withSecure($secure);

        $response = FigResponseCookies::set($response, $setCookie);

        return $response;
    }

    /**
     * Create cookie with the session ID.
     *
     * @param string $cookieName
     * @param string $sessionId
     *
     * @return Cookie
     */
    private function createCookie(string $cookieName, string $sessionId): Cookie
    {
        $cookie = Cookie::create(
            $cookieName, // TODO utiliser directement la nom qui est configuré dans le sessionConfig ????
            $sessionId,
            [
                'expires' => time() + 864000, //$this->sessionConfig->getCookieLifetime(),
                //'path' => null, //'path' => $request->getAttribute('webroot'), // https://github.com/cakephp/cakephp/blob/master/src/Http/Middleware/CsrfProtectionMiddleware.php#L331
                //'secure'   => true,//$this->sessionConfig->isCookieSecure(),
                //'samesite' => 'Lax',//$this->sessionConfig->getSameSite(),
                //'httponly' => true,
            ]
        );

        return $cookie;
    }

    /**
     * Get the session lifetime in seconds.
     */
    private function getLifetimeSeconds(): int
    {
        //return array_get($this->manager->getSessionConfig(), 'lifetime') * 60;
        return 120 * 60;
    }

    /**
     * Get the cookie lifetime in seconds.
     *
     * @return int
     */
    // TODO : code à virer !!!!
    private function getCookieLifetime(): int
    {
        $config = $this->manager->getSessionConfig();

        return $config['expire_on_close'] ? 0 : Carbon::now()->addMinutes($config['lifetime']);
    }
}
