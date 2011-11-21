<?php

const MAGE_PATH = 'C:/dev/prog/m1610dev';
const MODULE_PATH = 'C:/dev/prog/rewardsinstoredev';

$linker = new MageLinker(MAGE_PATH, MODULE_PATH);
$linker->link();
return;

class MageLinker {

    public $magePath;
    public $modulePath;
    
    private $tbtSharedDirs = array (
        '/app/code/community/TBT',
        '/app/design/adminhtml/base/default/layout',
        '/app/design/adminhtml/base/default/template',
        '/js/tbt',
        '/skin/adminhtml/base/default/css',
        '/skin/adminhtml/base/default/images',
        '/skin/frontend/base/default/css',
        '/skin/frontend/base/default/fonts',
        '/skin/frontend/base/default/images',
    );
    
    function __construct($magePath, $modulePath) 
    {
        $this->magePath = $magePath;
        $this->modulePath = $modulePath;
    }
    
    public function link() 
    {
        $this->recursiveLink($this->modulePath, $this->magePath);
    }

    function recursiveLink($target, $link) 
    {
        // Base case: if target is a file, just link it and we're done
        if (!is_dir($target)) {
            $this->createLink($target, $link);
            return true;
        }
        
        // Base case: if target is a directory, only link if the link direcotry path does not exist
        if (!file_exists($link)) {
        
            // Only link if not a TBT Shared directory. (eg. 'app/code/community/TBT/)
            if (!$this->isTbtSharedDir($link)) {
                $this->createLink($target, $link, true);
                return true;
            }
            
            // If it is a TBT Shared directory, just create the dir and continue
            if (mkdir($link)) {
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

    function createLink($target, $link, $isDir = false, $force = false) 
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
                $this->printl('Link already exists (use force option to override): ' . $link);
                return false;
            }
        }

        // Create symlink
        $mklink = 'mklink' . ($isDir ? ' /D' : '');
        $command = $mklink . ' ' . $this->safePath($link) . ' ' . $this->safePath($target);
        
        $output = exec($command, $output, $exit_code);
        echo $this->printl($exit_code . ': ' . $output);
        
        return $exit_code === 0;
    }
    
    protected function isTbtSharedDir($path) 
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
    
    protected function endsWithTbtSharedPath($path) 
    {
        foreach ($this->tbtSharedDirs as $createDir) {
            if ($this->endsWith($path, $createDir)) {
                return true;
            }
        }
        return false;
    }
    
    protected function endsWith($string, $ending) 
    {
        return substr_compare($string, $ending, -strlen($ending), strlen($ending)) === 0;
    }
    
    protected function safePath($path) 
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function printl($line) 
    {
        echo '<pre>' . $line . '</pre>';
    }
}

?>