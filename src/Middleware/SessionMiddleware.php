<?php

declare(strict_types=1);

namespace Chiron\Session\Middleware;

use Chiron\Http\Message\Cookie;
use Chiron\Session\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
     * @return SessionInterface
     */
    private function startSession(ServerRequestInterface $request)
    {
        $session = $this->getSession($request);

        $session->start();

        return $session;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return SessionInterface
     */
    private function getSession(ServerRequestInterface $request)
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
     * @param  \Illuminate\Session\SessionInterface $session
     *
     * @return void
     */
    private function collectGarbage(Session $session)
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
            // TODO : ligne de code à décommenter une fois qu'on aura corrigé la classe FileSessionHandler
            //$session->getHandler()->gc($this->getLifetimeSeconds());
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array $config
     *
     * @return bool
     */
    private function configHitsLottery(array $config)
    {
        return mt_rand(1, $config['lottery'][1]) <= $config['lottery'][0];
    }

    /**
     * @param ResponseInterface $response
     * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
     *
     * @return ResponseInterface
     */
    // TODO : code à virer !!!!
    private function addCookieToResponse(ResponseInterface $response, $session)
    {
        $s = $session;

        if ($this->sessionIsPersistent($c = $this->manager->getSessionConfig())) {
            $secure = array_get($c, 'secure', false);

            $setCookie = SetCookie::create($s->getName())
                ->withValue($s->getId())
                ->withExpires($this->getCookieLifetime())
                ->withDomain($c['domain'])
                ->withPath($c['path'])
                ->withSecure($secure);

            $response = FigResponseCookies::set($response, $setCookie);
        }

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
    private function getLifetimeSeconds()
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
    private function getCookieLifetime()
    {
        $config = $this->manager->getSessionConfig();

        return $config['expire_on_close'] ? 0 : Carbon::now()->addMinutes($config['lifetime']);
    }

    /**
     * Determine if the configured session driver is persistent.
     *
     * @param  array|null $config
     *
     * @return bool
     */
    private function sessionIsPersistent(?array $config = null)
    {
        // Some session drivers are not persistent, such as the test array driver or even
        // when the developer don't have a session driver configured at all, which the
        // session cookies will not need to get set on any responses in those cases.
        $config = $config ?: $this->manager->getSessionConfig();

        return ! in_array($config['driver'], [null, 'array']);
    }
}
