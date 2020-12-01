<?php

declare(strict_types=1);

namespace Chiron\Session;

use Chiron\Security\Security;
use Chiron\Session\Handler\FileSessionHandler;

//https://github.com/illuminate/session/blob/ac3f515d966c9d70065bb057db41b310aee772c8/Store.php
//https://github.com/symfony/http-foundation/blob/5.x/Session/Session.php
//https://github.com/spiral/session/blob/master/src/SessionSection.php

//https://tideways.com/profiler/blog/php-session-garbage-collection-the-unknown-performance-bottleneck

// EXEMPLE POUR UN FICHIER DE CONFIG :      https://github.com/laravel-shift/laravel-5.7/blob/master/config/session.php

// TODO : passer la classe en final et virer les attributs/méthodes protected !!!!!
// TODO : créer une classe SessionInterface ????
class Session
{
    // request attribute
    public const ATTRIBUTE = '__Session__';

    /**
     * Length of the session id.
     */
    public const SESSION_ID_LENGTH = 40;

    /**
     * The session ID.
     *
     * @var string
     */
    private $id;

    /**
     * The session name.
     *
     * @var string
     */
    private $name;

    /**
     * The session attributes.
     *
     * @var array
     */
    private $attributes = [];

    /**
     * The session handler implementation.
     *
     * @var \SessionHandlerInterface
     */
    private $handler;

    /**
     * Session store started status.
     *
     * @var bool
     */
    private $started = false;

    /**
     * Create a new session instance.
     *
     * @param  string  $name
     * @param  \SessionHandlerInterface  $handler
     * @param  string|null  $id
     *
     * @return void
     */
    //public function __construct($name, FileSessionHandler $handler, $id = null)
    public function __construct()
    {
        // TODO : à virer c'est un test !!!!
        $name = 'SID';
        $handler = new FileSessionHandler(directory('@runtime/session/'), 120);
        $id = null;

        $this->setId($id);
        $this->name = $name;
        $this->handler = $handler;
    }

    /**
     * Start the session, reading the data from a handler.
     *
     * @return bool
     */
    public function start(): bool
    {
        $this->loadSession();

        return $this->started = true;
    }

    /**
     * Load the session data from the handler.
     *
     * @return void
     */
    protected function loadSession(): void
    {
        $this->attributes = array_merge($this->attributes, $this->readFromHandler());
    }

    /**
     * Read the session data from the handler.
     *
     * @return array
     */
    protected function readFromHandler(): array
    {
        if ($data = $this->handler->read($this->getId())) {
            $data = @unserialize($this->prepareForUnserialize($data));

            if ($data !== false && ! is_null($data) && is_array($data)) {
                return $data;
            }
        }

        return [];
    }

    /**
     * Prepare the raw string data from the session for unserialization.
     *
     * @param  string $data
     *
     * @return string
     */
    protected function prepareForUnserialize(string $data): string
    {
        return $data;
    }

    /**
     * Save the session data to storage.
     *
     * @return void
     */
    public function save(): void
    {
        $this->handler->write($this->getId(), $this->prepareForStorage(
            serialize($this->attributes)
        ));

        $this->started = false;
    }

    /**
     * Prepare the serialized session data for storage.
     *
     * @param  string $data
     *
     * @return string
     */
    protected function prepareForStorage(string $data): string
    {
        return $data;
    }

    /**
     * Get all of the session data.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->attributes;
    }

    /**
     * Checks if a key is present and not null.
     *
     * @param  string|array $key
     *
     * @return bool
     */
    public function has($key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Get an item from the session.
     *
     * @param  string $key
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        if (! $this->has($key)) {
            return $default;
        }

        return $this->attributes[$name];
    }

    /**
     * Put a key / value pair or array of key / value pairs in the session.
     *
     * @param  string $key
     * @param  mixed  $value
     *
     * @return void
     */
    public function set(string $key, $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Remove an item from the session, returning its value.
     *
     * @param  string $key
     *
     * @return void
     */
    public function remove(string $key): void
    {
        unset($this->attributes[$key]);
    }

    /**
     * Remove all of the items from the session.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->attributes = [];
    }

    /**
     * Flush the session data and regenerate the ID.
     *
     * @return bool
     */
    public function invalidate(): bool
    {
        $this->clear();

        return $this->migrate(true);
    }

    /**
     * Generate a new session identifier.
     *
     * @param  bool $destroy
     *
     * @return bool
     */
    // TODO : virer cette fonction qui devait servir pour regenerer le csrf token !!!
    public function regenerate(bool $destroy = false): bool
    {
        return tap($this->migrate($destroy), function () {
            $this->regenerateToken();
        });
    }

    /**
     * Generate a new session ID for the session.
     *
     * @param  bool $destroy
     *
     * @return bool
     */
    // TODO : renommer la méthode en "regenerate($destroy = false)" c'est plus logique car le nom migrate ne veut rien dire !!!!
    public function migrate(bool $destroy = false): bool
    {
        if ($destroy) {
            $this->handler->destroy($this->getId());
        }

        $this->setId($this->generateSessionId());

        return true;
    }

    /**
     * Get the underlying session handler implementation.
     *
     * @return \SessionHandlerInterface
     */
    // TODO : ajouter le return typehint
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * Determine if the session has been started.
     *
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->started;
    }

    /**
     * Get the name of the session.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the name of the session.
     *
     * @param  string $name
     *
     * @return void
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * Get the current session ID.
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the session ID.
     *
     * @param  null|string $id
     *
     * @return void
     */
    // TODO : c'est quoi l'utilité de lui passer null en paramétre au lieu d'une string ???
    public function setId(?string $id): void
    {
        $this->id = $this->isValidId($id) ? $id : $this->generateSessionId();
    }

    /**
     * Determine if this is a valid session ID.
     *
     * @param  null|string $id
     *
     * @return bool
     */
    // TODO : c'est quoi l'utilité de lui passer null en paramétre au lieu d'une string ??? d'ailleur il faudrait pouvoir lui passer un mixed en paramétre
    public function isValidId(?string $id): bool
    {
        return is_string($id) && ctype_alnum($id) && strlen($id) === self::SESSION_ID_LENGTH;
        //return preg_match('/^[-,a-zA-Z0-9]{1,128}$/', $id) !== false;
    }

    /**
     * Get a new, random session ID.
     *
     * @return string
     */
    protected function generateSessionId(): string
    {
        return Security::generateId(self::SESSION_ID_LENGTH);
    }
}
