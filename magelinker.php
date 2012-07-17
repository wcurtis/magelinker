<?php

const DRY_RUN = false;
const SHOW_PARAMS = false;

/**
 * MageLinker will symlink your Magento module into any Magento installation
 * making it easier and faster for you to manage development of your modules.
 */
class MageLinker
{
    /**
     * The full path to the magento instante we are linking into.
     */
    public $magePath;

    /**
     * The path to the module that is being linked into magento.
     */
    public $modulePath;

    /**
     * Determines whether this is a TBT Module - if so, it will respect certain shared directories.
     */
    public $isTbtModule;

    /**
     * Determines how much output is echo'd out.
     */
    protected $_debugLevel = 3;

    /* A list of directories which are shared by many modules.
    * These directory trees will be built if they do not exist
    * rather than symlinked. This prevents a messy symlinking
    * ordeal if for example, the first module symlinks the
    * first TBT directory.
    */
    private $tbtSharedDirs = array (
        '/app/code/community/TBT',
        '/app/design/adminhtml/base/default/layout',
        '/app/design/adminhtml/base/default/template',
        '/js/tbt',
        '/skin/adminhtml/base/default/css',
        '/skin/adminhtml/base/default/images',
        '/skin/adminhtml/default/default/css',
        '/skin/adminhtml/default/default/images',
        '/skin/frontend/base/default/css',
        '/skin/frontend/base/default/fonts',
        '/skin/frontend/base/default/images',
    );

    /**
     * Constructor just sets the mage path, module path, etc.
     *
     * @param $magePath
     * @param $modulePath
     * @param bool $isTbtModule
     */
    public function __construct($magePath, $modulePath, $isTbtModule = false)
    {
        $this->magePath = $this->_unixPath($magePath);
        $this->modulePath = $this->_unixPath($modulePath);
        $this->isTbtModule = $isTbtModule;
        $this->_validatePaths();
    }

    /**
     * Validate the paths that are passed in.  Main use case on this one is when they type in the paths in
     * the wrong order.
     *
     * @throws Exception
     */
    protected function _validatePaths()
    {
        if (!file_exists("{$this->magePath}/app/Mage.php")) {
            throw new Exception("The magePath doesn't seem to be correct, can't find app/Mage.php inside {$this->magePath},
            maybe you entered the module path as the mage path by accident");
        }
    }

    /**
     * Set the debug level, so that more or less output will be given.
     *
     * @param $level
     * @return MageLinker
     */
    public function setDebugLevel($level)
    {
        $this->_debugLevel = $level;

        return $this;
    }

    /**
     * Determine whether or not this is running within a command line context.
     *
     * @return bool
     */
    protected function _isCommandLine()
    {
        if (isset($_SERVER['HTTP_USER_AGENT']) && $_SERVER['HTTP_USER_AGENT']) {
            return false;
        }

        return true;
    }


    /**
     * Link the module path into the magento path.
     */
    public function link()
    {
        $this->recursiveLink($this->modulePath, $this->magePath);
    }

    /**
     * Link a path recursively into a target - loops over all the directories and files in the source file path
     * and if it's a file, links, if it's a directory only links if it doesn't exist yet.
     *
     * @param $target
     * @param $link
     * @return bool
     */
    public function recursiveLink($target, $link)
    {
        // Base case: if target is a file, just link it and we're done
        if (!is_dir($target)) {
            $this->createLink($target, $link);
            return true;
        }

        // Base case: if target is a directory, only link if the link direcotry path does not exist
        if (!file_exists($link)) {

            // Only link if not a TBT Shared directory. (eg. 'app/code/community/TBT/')
            if (!$this->isTbtModule || !$this->_isTbtSharedDir($link)) {
                $this->createLink($target, $link, true);
                return true;
            }

            // If it is a TBT Shared directory, just create the dir and continue
            if (DRY_RUN || mkdir($link)) {
                $this->printl('Created dir: ' . $link);
            } else {
                $this->printl('Failed to create dir: ' . $link);
            }
        }

        // 'ls'
        $files = scandir($target);

        // Recurse on each file in the directory
        foreach($files as $file) {

            // Ignore '.', '..', and hidden files
            if ($file[0] == '.') {
                continue;
            }

            $newTarget = $target . '/' . $file;
            $newLink = $link . '/' . $file;
            $this->recursiveLink($newTarget, $newLink);
        }

        return true;
    }

    /**
     * Create symlink from a given path to a target path.
     *
     * @param $target
     * @param $link
     * @param bool $isDir
     * @param bool $force
     * @return bool
     */
    public function createLink($target, $link, $isDir = false, $force = false)
    {
        // Skip if target does not exist
        if (!file_exists($target)) {
            $this->printl('Target does not exist: ' . $target);
            return false;
        }

        // Remove old symlink if 'force' option is true
        if (file_exists($link)) {
            if ($force) {
                $this->printl('Removing old link: ' . $link);
                unlink($link);
            } else {
                if ($this->_debugLevel >= 3) {
                    $this->printl('Link already exists (use force option to override): ' . $link);
                }
                return false;
            }
        }

        $command = $this->_getSymlinkCommand($target, $link, $isDir);

        $output = '';
        $exitCode = 0;
        if (DRY_RUN) {
            $output = $command;
        } else {
            $output = exec($command, $output, $exitCode);
        }

        if ($this->_debugLevel >= 3) {
            echo $this->printl($exitCode . ': ' . $output);
        }

        return $exitCode === 0;
    }

    /**
     * Get the command to do a symlink, based on whether in Unix or Win environment.
     *
     * @param $target
     * @param $link
     * @param bool $isDir
     * @return string
     */
    protected function _getSymlinkCommand($target, $link, $isDir = false)
    {
        $target = $this->_safePath($target);
        $link = $this->_safePath($link);

        if ($this->_isUnix()) {
            $command = "ln -s '$target' '$link'";
        } else {
            $mklink = 'mklink' . ($isDir ? ' /D' : '');
            $command = "$mklink $link $target";
        }

        return $command;
    }

    /**
     * Determine whether a given path is a TBT shared directory.  Just checks against our array of shared directories.
     *
     * @param $path
     * @return bool
     */
    protected function _isTbtSharedDir($path)
    {
        foreach ($this->tbtSharedDirs as $sharedPath) {

            $fullMagePath = $this->magePath . $sharedPath;

            if (strpos($fullMagePath, $path) !== false) {
                // return true if the path in contained in a TBT Shared path
                return true;
            }
        }
        return false;
    }

    /**
     * Use a universal directory separator to makes paths cross platform compatible.
     *
     * @param $path
     * @return mixed
     */
    protected function _safePath($path)
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    /**
     * Determine whether we're in Windows or Unix
     *
     * @return bool
     */
    protected function _isUnix()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Convert a windows style file path to a unix style file path.
     *
     * @param $path
     * @return mixed
     */
    protected function _unixPath($path)
    {
        return str_replace('\\', '/', $path);
    }

    /**
     * Output formatting.
     *
     * @param $line
     */
    public function printl($line)
    {
        if ($this->_isCommandLine()) {
            echo $line . "\r\n";
        } else {
            echo '<pre>' . $line . '</pre>';
        }
    }
}