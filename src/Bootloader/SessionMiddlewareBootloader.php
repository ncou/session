<?php

declare(strict_types=1);

namespace Chiron\Session\Bootloader;

use Chiron\Core\Container\Bootloader\AbstractBootloader;
use Chiron\Session\Middleware\SessionMiddleware;
use Chiron\Http\Http;

final class SessionMiddlewareBootloader extends AbstractBootloader
{
    public function boot(Http $http): void
    {
        $http->addMiddleware(SessionMiddleware::class); // TODO : vérifier quelle est la priorité à utiliser pour ce middleware
    }
}
