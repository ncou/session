<?php

declare(strict_types=1);

namespace Chiron\Session\Bootloader;

use Chiron\Core\Container\Bootloader\AbstractBootloader;
use Chiron\Core\Directories;
use Chiron\PublishableCollection;

final class PublishSessionBootloader extends AbstractBootloader
{
    public function boot(PublishableCollection $publishable, Directories $directories): void
    {
        // copy the configuration file template from the package "config" folder to the user "config" folder.
        $publishable->add(__DIR__ . '/../../config/session.php.dist', $directories->get('@config/session.php'));
    }
}
