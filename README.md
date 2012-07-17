# MageLinker BETA #

Welcome to MageLinker BETA.

MageLinker will symlink your Magento module into any Magento installation
making it easier and faster for you to manage development of your modules.

Compatible with Windows and Unix dev environments (tested on Windows and OSX).

## Web Usage ##
1. Drop magelinker.php and magelinkerweb.php into your Magento root
2. Access it in the browser (eg. mystore.dev/magelinkerweb.php.php)
3. Enter the module path you wish to link into your dev environment!

## Command Line Usage ##
1. From within your PHP command line script:

        require_once('path/to/magelinker.php');

2. Construct the MageLinker object.  If a TBT module, pass in the true parameter.

        $magelinker = new MageLinker($fullPathToMagento, $fullPathToModule, true);

3. Now go ahead and link() it, setting debug level to something lower if you prefer to only see output when there
   is a problem from your command-line script:

         $magelinker->setDebugLevel(0)->link();
