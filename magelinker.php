<?php

const DS = DIRECTORY_SEPARATOR;
const DRY_RUN = false;
const SHOW_PARAMS = false;

if (SHOW_PARAMS || isset($_GET['debug'])) {
	echoParams();
}

$magePath = getcwd();
$modulePath = null;
$isTbtModule = true;

if (isset($_GET['module'])) {
	$modulePath = $_GET['module'];
}

if (isset($_GET['istbtmodule'])) {
	$isTbtModule = true;
}

?>

<style>
.textBox {
    width:300px;
}
</style>

<h1>Mage Linker [Beta]</h1>

<p><b>Mage Path: </b><?php echo $magePath; ?></p>

<form name="input" method="get">
	<b>Module Path: </b><input type="text" class="textBox" name="module" value="<?php echo dirname($magePath) . DS; ?>" /><br/><br/>
	<input type="checkbox" name="istbtmodule" value="checked" checked="checked"/>Is TBT module<br /><br/>
	<input type="submit" value="Submit" />
</form>

<br/>

<?php 

// Done if no module specified
if (!$modulePath) {
	return;
}

if (DRY_RUN) {
    echo '<p><b>DRY RUN (no linking will be done)</b></p>';
}

$linker = new MageLinker($magePath, $modulePath);
$linker->link();
echo '<p><b>Done.</b></p>';
return;

/***************************************************
* HELPER FUNCTIONS START HERE
***************************************************/

function echoParams() {
	echo p('POST: ' . print_r($_POST, true));
	echo p('GET: ' . print_r($_GET));
	echo p('REQUEST: ' . print_r($_REQUEST));
}

function p($line)
{
	echo '<pre>' . $line . '</pre>';
}

/***************************************************
 * CLASSES START HERE
 ***************************************************/

class MageLinker {

    public $magePath;
    public $modulePath;
    
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
        '/skin/frontend/base/default/css',
        '/skin/frontend/base/default/fonts',
        '/skin/frontend/base/default/images',
    );
    
    function __construct($magePath, $modulePath) 
    {
        $this->magePath = $this->unixPath($magePath);
        $this->modulePath = $this->unixPath($modulePath);
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
        
        $output = '';
        $exitCode = 0;
        if (DRY_RUN) {
            $output = $command;
        } else {
            $output = exec($command, $output, $exitCode);
        }
        echo $this->printl($exitCode . ': ' . $output);
        
        return $exitCode === 0;
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
        return str_replace('/', DS, $path);
    }
    
    protected function unixPath($path) 
    {
        return str_replace('\\', '/', $path);
    }

    public function printl($line) 
    {
        echo '<pre>' . $line . '</pre>';
    }
}

?>