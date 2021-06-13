<?php

declare(strict_types=1);

namespace Chiron\Session\Middleware;

use Chiron\Cookies\Cookie;
use Chiron\Session\Session;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Chiron\Session\Config\SessionConfig;
use Chiron\Cookies\CookieFactory;
use Chiron\Security\Signer;
use Chiron\Support\Random;
use Chiron\Security\Exception\BadSignatureException;

//https://github.com/flarum/core/blob/master/src/Http/Middleware/CollectGarbage.php#L49

//https://github.com/nuevephp/laravel-session/blob/master/src/Middleware.php
//https://github.com/odan/session/blob/master/src/Middleware/SessionMiddleware.php

// EXEMPLE sans les cookies => https://github.com/kevinsimard/laravel-cookieless-session/blob/master/src/Middleware/StartSession.php

//https://github.com/illuminate/session

// Exemple node.js sur sign/unsign un cookie.
//https://github.com/balderdashy/sails/blob/53d0473c2876b1925136f777cb51ac9eda5b24aa/lib/hooks/session/index.js#L481
//https://github.com/expressjs/cookie-parser/blob/master/index.js#L129

final class SessionMiddleware implements MiddlewareInterface
{
    /** @var SessionConfig */
    private $sessionConfig;

    /** @var CookieFactory */
    private $cookieFactory;

    /** @var Signer */
    private $signer;

    /**
     * @param SessionConfig $sessionConfig
     * @param CookieFactory $cookieFactory
     * @param Signer        $signer
     */
    public function __construct(SessionConfig $sessionConfig, CookieFactory $cookieFactory, Signer $signer)
    {
        $this->sessionConfig = $sessionConfig;
        $this->cookieFactory = $cookieFactory;
        // Use the class name as salt to have a different signatures in different application module.
        $this->signer = $signer->withSalt(self::class);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->startSession($request);

        // TODO : attention si on créé une SessionInterface il faudra déplacer cette constante ::ATTRIBUTE dans la classe d'interface, et ne pas la laisser dans la classe Session !!!!
        // TODO : il faudrait peut être stocker dans la classe SessionMiddleware la constante ATTRIBUTE, et non pas la sotcker dans la classe Session !!!!
        $response = $handler->handle($request->withAttribute(Session::ATTRIBUTE, $session));

        $this->closeSession($session);

        // TODO : remplacer ces 2 lignes de code par un $this->addCookieToResponse($response, $session) ????
        $cookie = $this->prepareCookie($session->getId());

        return $response->withAddedHeader('Set-Cookie', (string) $cookie); // TODO : créer une méthode return $this->withSessionCookie($response, $session):ResponseInterface qui se charge d'attacher le cookie à la réponse et à retourner le nouvel objet $response actualisé. (et utiliser le $session->getId() et ->getName() pour alimenter le cookie name et le cookie value.
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
        $name = $this->sessionConfig->getCookieName();
        $id = $this->getSessionIdFromCookie($request->getCookieParams());

        return new Session($name, $id);
    }

    /**
     * Attempt to locate session ID in request.
     *
     * @param array $cookies
     *
     * @return string|null
     */
    private function getSessionIdFromCookie(array $cookies): ?string
    {
        $name = $this->sessionConfig->getCookieName();
        $value = $cookies[$name] ?? '';

        try {
            return $this->signer->unsign($value);
        } catch (BadSignatureException $e){
            // Don't blow up the middleware if the signature is invalid.
            return null;
        }
    }

    /**
     * Close the session handling for the request.
     *
     * @param  SessionInterface $session
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
    // TODO : utiliser un middleware pour collecter la poubelle !!!! => https://github.com/flarum/core/blob/master/src/Http/Middleware/CollectGarbage.php
    // TODO : renommer la méthode en collectGarbageSometimes()
    private function collectGarbage(Session $session): void
    {
        //$config = $this->manager->getSessionConfig();

        // We must manually sweep the storage location to get rid of old sessions from storage.
        // Here are the chances that it will happen on a given request.
        // By default, the odds are 2 out of 100.
        $config['lottery'] = [2, 100]; // TODO : ajouter le systéme de lottery dans le fichier de config session.php.dist

        // Here we will see if this request hits the garbage collection lottery by hitting
        // the odds needed to perform garbage collection on any given request. If we do
        // hit it, we'll call this handler to let it delete all the expired sessions.
        if ($this->configHitsLottery($config)) {
            $lifetime = $this->sessionConfig->getCookieAge();
            $session->getHandler()->gc($lifetime); // TODO : eventuellement créer une proxyméthode pour qu'on puisse appeller la méthode gc() directement depuis la classe Session.
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array $config
     *
     * @return bool
     */
    // TODO : remonter cette fonction dans la classe sessionConfig::class, et la renommer en garbageCollectorHitsLottery()
    // TODO : lui passer en paramétre $probability et $divisor
    //https://github.com/flarum/core/blob/master/src/Http/Middleware/CollectGarbage.php#L49
    // TODO : renommer la méthode en hit()
    private function configHitsLottery(array $config): bool
    {
        return mt_rand(1, $config['lottery'][1]) <= $config['lottery'][0]; // TODO : utiliser des variables $probability et $divisor
    }

    /**
     * Create Session cookie with the signed session ID value.
     * Sign the value stored in the cookie for better security (in case of XSS attack).
     *
     * @param string $sessionId
     *
     * @return Cookie
     */
    // TODO : renommer en createCookie() !!!!
    private function prepareCookie(string $sessionId): Cookie
    {
        $name = $this->sessionConfig->getCookieName();
        $value = $this->signer->sign($sessionId);
        $expires = time() + $this->sessionConfig->getCookieAge();

        return $this->cookieFactory->create($name, $value, $expires);
    }
}
