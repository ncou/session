<?php

declare(strict_types=1);

namespace Chiron\Session;

use Chiron\Container\SingletonInterface;
use Chiron\Core\Exception\ScopeException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

//https://github.com/spiral/framework/blob/e63b9218501ce882e661acac284b7167b79da30a/src/Framework/Session/SessionScope.php

// TODO : passer la classe en final et virer les attributs/méthodes protected !!!!!
// TODO : faire des méthodes proxy pour accéder aux méthodes de la session du genre set/push/remove/get...etc ca sera plus facile !!!!
// TODO : renommer la classe en SessionManager ????
class SessionScope implements SingletonInterface
{
    /** @var Session */
    private $session = null;
    /** @var ContainerInterface */
    private $container = null;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get active instance of Session.
     *
     * @return Session
     */
    public function getSession(): Session
    {
        // ensure the session instance is fresh (get it from the container).
        $this->refreshSessionInstance();

        return $this->session;
    }

    /**
     * Grab the latest Session instance 'existing' in the container.
     *
     * @throws ScopeException In case the Session is not found in the container.
     */
    private function refreshSessionInstance(): void
    {
        // TODO : si on fait plutot un $this->container->isBound(XXXX) et on léve une erreur si ce n'est pas le cas, ca permet de gérer le cas ou le provider n'est pas executer, et il est possible que le container fasse un autowire lors du get() et donc on aura bien un objet de retourné mais ca ne sera pas la bonne instance !!!!
        try {
            $this->session = $this->container->get(Session::class);
        } catch (NotFoundExceptionInterface $e) {
            throw new ScopeException(
                'Unable to get "Session" in active container scope.',
                $e->getCode(),
                $e
            );
        }
    }
}
