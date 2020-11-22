<?php

declare(strict_types=1);

namespace Chiron\Session\Provider;

use Chiron\Container\BindingInterface;
use Chiron\Core\Container\Provider\ServiceProviderInterface;
use Chiron\Core\Exception\ScopeException;
use Chiron\Session\Session;
use Closure;
use Psr\Http\Message\ServerRequestInterface;

final class SessionServiceProvider implements ServiceProviderInterface
{
    public function register(BindingInterface $container): void
    {
        // This SHOULDN'T BE a singleton(), use a basic bind() to ensure Request instance is fresh !
        $container->bind(Session::class, Closure::fromCallable([$this, 'session']));
    }

    private function session(ServerRequestInterface $request): Session
    {
        $session = $request->getAttribute(Session::ATTRIBUTE);

        if ($session === null) {
            throw new ScopeException('Unable to resolve Session, invalid request scope.');
        }

        return $session;
    }
}
