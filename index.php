<?php

require_once('magelinker.php');

if (SHOW_PARAMS || isset($_GET['debug'])) {
    echoParams();
}

$magePath = getcwd();
$modulePath = null;
$isTbtModule = false;

if (isset($_GET['module'])) {
    $modulePath = $_GET['module'];
}

if (isset($_GET['istbtmodule'])) {
    $isTbtModule = true;
}

if (isset($_GET['magePath'])) {
    $magePath = $_GET['magePath'];
}

?>

<style xmlns="http://www.w3.org/1999/html">
    .textBox {
        width:300px;
    }
</style>

<h1>Mage Linker [Beta]</h1>



<form name="input" method="get">
    <p><b>Mage Path: </b><?php echo $magePath; ?></b></p>
    <b>Module Path: </b><input type="text" class="textBox" name="module" value="<?php echo dirname($magePath) . DIRECTORY_SEPARATOR; ?>" /><br/><br/>
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

try {
    $linker = new MageLinker($magePath, $modulePath, $isTbtModule);
    $linker->link();
} catch (Exception $e) {
    echo "<b>Oops!  Something went wrong:</b> " . $e->getMessage();
}

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