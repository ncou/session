<?php

declare(strict_types=1);

namespace Chiron\Session\Handler;

use Chiron\Filesystem\Filesystem;

// TODO : prefixer les fichiers de session par : "sess_" . $sessionId
//http://git.php.net/?p=php-src.git;a=blob;f=ext/session/mod_files.c;hb=HEAD#l83

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
    // TODO : virer le paramétre $minutes qui ne sert à rien !!!!
    public function __construct(string $directory, int $minutes)
    {
        $this->filesystem = new Filesystem();

        $this->directory = rtrim($directory, '\\/'); // TODO : c'est quoi l'utilité de ce normalize ????

        // TODO : modifier la méthode write du filesystem pour qu'elle créée le répertoire si il n'existe pas !!!!
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
        $this->filesystem->write($this->getFilename($sessionId), $data, true); // TODO : vérifier que le 3eme paramétre de la méthode write créé bien le répertoire si le chemin n'existe pas !!!!

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        $this->filesystem->unlink($this->getFilename($sessionId)); // TODO : renommer le unlink en "delete()"

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
                $this->filesystem->unlink($file->getRealPath()); // TODO : renommer le unlink en "delete()"
            }
        }

/*
        foreach ($this->files->getFiles($this->directory) as $filename) {
            if ($this->files->time($filename) < time() - $maxlifetime) {
                $this->files->delete($filename);
            }
        }*/
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
        // TODO : ajouter le prefix pour éviter de supprimer trop de fichiers !!!!
        return "{$this->directory}/{$session_id}"; // TODO : utiliser un sprintf ou concaténer avec des points 'xx' . 'yy'
    }
}
