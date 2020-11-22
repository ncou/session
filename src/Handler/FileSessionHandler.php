<?php

declare(strict_types=1);

namespace Chiron\Session\Handler;

use Chiron\Filesystem\Filesystem;

// TODO : passer la classe en final et virer les propriétés protected !!!!
// TODO : faire hériter cette classe de l'interface : SessionHandlerInterface qui existe dans PHP !!!  https://www.php.net/manual/fr/class.sessionhandlerinterface.php
class FileSessionHandler
{
    /**
     * The filesystem instance.
     *
     * @var \Chiron\Filesystem\Filesystem
     */
    private $filesystem;

    /**
     * The directory where sessions should be stored.
     *
     * @var string
     */
    private $directory;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    private $minutes;

    /**
     * Create a new file driven handler instance.
     *
     * @param  string $directory
     * @param  int    $minutes
     */
    public function __construct(string $directory, int $minutes)
    {
        $this->filesystem = new Filesystem();

        $this->directory = rtrim($directory, '\\/');

        if (! is_dir($this->directory)) {
            $this->filesystem->makeDirectory($this->directory);
        }

        $this->minutes = $minutes;
    }

    /**
     * {@inheritdoc}
     */
    // TODO : à virer
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    // TODO : à virer
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        return $this->filesystem->exists($this->getFilename($sessionId))
            ? $this->filesystem->read($this->getFilename($sessionId))
            : '';

        /*
        if ($this->filesystem->isFile($path = $this->directory.'/'.$sessionId)) {
            if ($this->filesystem->lastModified($path) >= Carbon::now()->subMinutes($this->minutes)->getTimestamp()) {
                return $this->filesystem->sharedGet($path);
            }
        }

        return '';*/
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        $this->filesystem->write($this->directory . '/' . $sessionId, $data, true);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $this->filesystem->delete($this->directory . '/' . $sessionId);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        $files = Finder::create()
                    ->in($this->directory)
                    ->files()
                    ->ignoreDotFiles(true)
                    ->date('<= now - ' . $lifetime . ' seconds');

        foreach ($files as $file) {
            $this->filesystem->delete($file->getRealPath());
        }
    }

    /**
     * Session data filename.
     *
     * @param string $session_id
     *
     * @return string
     */
    private function getFilename(string $session_id): string
    {
        return "{$this->directory}/{$session_id}";
    }
}
