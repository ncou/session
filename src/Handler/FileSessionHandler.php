<?php

declare(strict_types=1);

namespace Chiron\Session\Handler;

use Chiron\Filesystem\Filesystem;

//https://github.com/spiral/session/blob/master/src/Handler/FileHandler.php
//https://github.com/illuminate/session/blob/master/FileSessionHandler.php

// TODO : utiliser un fileprefix pour éviter un comportement dangereux du garbage collector avec l'effacement des fichiers => https://github.com/Dynom/SessionHandler/blob/master/D/SessionDriver/File.php
//https://github.com/horde/SessionHandler/blob/master/lib/Horde/SessionHandler/Storage/File.php#L156

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
        $this->filesystem->write($this->getFilename($sessionId), $data, true);

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $this->filesystem->unlink($this->getFilename($sessionId));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        /*
        $files = Finder::create()
                    ->in($this->directory)
                    ->files()
                    ->ignoreDotFiles(true)
                    ->date('<= now - ' . $lifetime . ' seconds');

        foreach ($files as $file) {
            $this->filesystem->delete($file->getRealPath());
        }*/

        // TODO : attention c'est dangereux de faire une suppression de tous les fichiers dans le répertoire, car si l'utilisateur a mal configuré son répertoire ca va être dangereux !!! il faudrait peut être préfixer les fichiers de session avec un préfix du genre "_session_XXXXXX" et on ne supprime que les fichiers qui commencent par ce préfix !!!
        foreach ($this->filesystem->files($this->directory) as $file) {
            if ($file->getMTime() < time() - $lifetime) {
                $this->filesystem->unlink($file->getRealPath());
            }
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
