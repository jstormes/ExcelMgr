<?php
/** All Error Reporting **/
error_reporting(E_ALL);
/** No time limit **/
set_time_limit(0);

/**  Set php include path  **/
if (isset($_SERVER["PHP_INCLUDE_PATH"]))
    set_include_path($_SERVER["PHP_INCLUDE_PATH"]);
else {
    echo "\n\nCannot find environment varable PHP_INCLUDE_PATH.\n\n";
    exit();
}

/**  Set application environment  **/
if (isset($_SERVER["APPLICATION_ENV"]))
    define('APPLICATION_ENV', $_SERVER["APPLICATION_ENV"]);
else {
    echo "\n\nCannot find environment varable APPLICATION_ENV this should be set to 'development', 'test' or 'production'.\n\n";
    exit();
}

/**  Set APPLICATION_PATH environment  **/
if (isset($_SERVER["APPLICATION_PATH"]))
    define('APPLICATION_PATH', $_SERVER["APPLICATION_PATH"]);
else {
    echo "\n\nCannot find environment varable APPLICATION_PATH.\n\n";
    exit();
}

date_default_timezone_set('America/Chicago');

/**  Setup autoloader  **/
require_once 'Zend/Loader/Autoloader.php';
$loader = Zend_Loader_Autoloader::getInstance();

// Create application, bootstrap
$config = array();
$config[] = APPLICATION_PATH . '/configs/application.ini';
if (file_exists(APPLICATION_PATH . '/configs/local.ini'))
    $config[] = APPLICATION_PATH . '/configs/local.ini';

$application = new Zend_Application(
    APPLICATION_ENV,
    array(
        'config' => $config,
    )
);

/**  Bootstrap  **/
$application->bootstrap();

/**  Check for command line parameers  **/
if ($argc!=2) {
    echo "\n\nUsage: {argv[0]} (batch_id)\n\n";
    exit();
}
    
/** Set our parameters  **/
$batch_id = $argv[1];

$Batch = new ExcelMgr_Models_ExcelMgrBatch();

$Batch_Row=$Batch->find($batch_id)->current();

try {
    echo "\nStarted\n";
    echo "\nParamters:\n";
    print_r($Batch_Row->toArray());
    
    $Batch_Row->status="Started";
    $Batch_Row->pid = getmypid();
    $Batch_Row->save();
    
    //**** Call back function ****/
    // Added because we don't have events in ZF1
    //
    if(!is_null( $Batch_Row->callback )){
    	echo "\nPre Call Back: ".$Batch_Row->callback."\n";
    	if(class_exists($Batch_Row->callback)){
    		echo "\nPre Call Back ClassExists\n";
    		$callback = new $Batch_Row->callback($Batch_Row->project_id);
    		if (method_exists($callback,"pre_load")) {
    			echo "\nPre Call Back method exists\n";
    			$callback->pre_load($Batch_Row);
    		} else {
    			echo "\nPre Call Back method not found.\n";
    		}
    	}else{
    		echo "\nCould not find Pre-Call Back Class\n";
    	}
    }
    
    $Loader = new ExcelMgr_ExcelToTable($batch_id);
    if ($Loader->load()){
        $Batch_Row->status="Done";

        //**** Call back function ****/
        // Added because we don't have events in ZF1
        // 
        if(!is_null( $Batch_Row->callback )){
            echo "\nCall Back: ".$Batch_Row->callback."\n";
            if(class_exists($Batch_Row->callback)){
                echo "\nCall Back ClassExists\n";
                $callback = new $Batch_Row->callback;
                if (method_exists($callback,"post_load")) {
                	$callback->post_load($Batch_Row);
                }else{
                	echo "\nCall Back method not found.\n";
                }
            }else{
                echo "\nCould not find Call Back Class\n";
            }
        }

    }else 
        $Batch_Row->status="Crash";
    
    $Batch_Row->pid = null;
    $Batch_Row->save();

    echo "\nDone\n";
}
catch (Exception $Ex) {
    echo "Failed: ".$Ex->getMessage();
    $Batch_Row->status="Failed: ".$Ex->getMessage();
    $Batch_Row->pid = null;
    $Batch_Row->save();
}
