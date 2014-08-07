#ExcelMgr

ExcelMgr is a library for importing and exporting data from a database table to an Excel worksheet.

This library spawns a long running task that forks multiple loaders. The idea is that by breaking the file to be loaded up into sections and having a fork for each section the load should go faster.

http://stackoverflow.com/questions/45953/php-execute-a-background-process

##Program Flow

    view/modals/upload.phtml -- presents the dialog to select the file. Opened by clicking on the Upload button.
    view/modals/map.phtml -- present the dialog to map input to DB columns.
    view/modals/load.phtml -- presents status of the data load.
    view/modals/load_history_partial.phtml -- formats the HTML table with data for load.phtml.
    view/ImportExcel.php -- handles the load process

This class is the view helper for importing xlsx files into a database.
Currently this file uses a Zend_Db_Table_Abstract to gather meta-data about
the target MySQL table.  In the near future it will also accept a 
PHPSlickGrid_Db_Table that will provide enhanced functions such as column
name alises, fields widths, etc... for now only the minimum data load function
are implemented.

The load process can be viewed as a mini MVC with this class representing 
the controller, the bootstrap modals representing the view and the `*_Db_Table_Abstract` representing the model.

The load proces is split into steps, with step 0 just presenting 
the load HTML (Button).

    Steps/Views:

    0 - No load in progress, only the HTML to initiate the load is presented.

    1 - Upload/History modal is presented, history is updated via AJAX partial 
        with the current load status.

    2 - Mapping, the mapping modal is presented, allowing the user to map xlsx
        columns to the destination MySQL columns.

    3 - Load status, presents the load status in a modal, this is the same load 
        status that is presented in step 1 but without the upload option.

    4 - Not a step per-say, presents the detailed log of a load in a modal 
        window.
    
This class expects that the layout has a <?php echo $this->layout()->modals; ?> in the main body where all bootstrap modals can live.

##Application.ini

Add this to the application.ini

    ;-
    ;- Add the ExcelMgr library to the name space.
    ;-
    autoloaderNamespaces[] = "ExcelMgr_"

##Controller

The controller needs code similar to this to create the upload button

![Upload Button](readme_files/upload_button.png)

    $importTable = new Application_Model_DbTable_ImportStage();
    $project_id = 0;
    $tableMap = null;
    $name = 'ACUpload';
    $options = array(
        "HTML"=>"<button class=\"btn btn-default\"><span class=\"fa fa-upload\"></span> Upload Aircraft Data</button>",
        "Help"=>"Select Excel file to upload.",
        "mapping"=>$tableMap
        );

    $ImportFile = new ExcelMgr_View_ImportExcel($name, $importTable, $project_id, $options);
    $this->view->aircraft_upload = $ImportFile->Button();

When `null` is passed to `$tableMap` the ExcelMgr will create the data mapping array on the fly. To force a map, you can pass an array similar to this

    $tableMap = array(
            "A/C"                     => "aircraft_num_txt",
            "Delta num"               => "delta_item_num",
            "Open Date"               => "open_dt",
            "Rep"                     => "rep_txt",
            "Section"                 => "section_txt",
            "Description and/or PN"   => "prr_desc",
            "Serial Number"           => "serial_num_txt",
            "Query / Issue / Comment" => "query_txt",
            "Response"                => "response_txt",
            "SWA Priority"            => "swa_priority",
            "Response Date"          => "response_dt",
            "Response by"             => "response_by",
            "Status"                  => "source_status",
            "Closed Date"             => "source_closed_dt"
        );

##View

To present the upload button, put this in the view file

    <?= $this->aircraft_upload; ?>

##Paths

    /ExcelMgr/View/Modals/...  
    /ExcelMgr/View/ImportExcel.php  
    /ExcelMgr/View/ExportExcel.php  
    /ExcelMgr/Scripts/ExcelToTable.php  
    /ExcelMgr/Scripts/TableToExcel.php  
    /ExcelMgr/ExcelToTable.php  
    /ExcelMgr/TableToExcel.php 


##Tables

**excel_mgr_batch**

|excel_mgr_batch_id |          path         |       table |                          map |          uptd_usr_id             |          updt_dtm            |          deleted |
|-------------------|-----------------------|-------------|------------------------------|----------------------------------|------------------------------|------------------|
|Primary Key        |Path to  Excel file    |SQL table    |JSON encoded mapping array    |user_id of user that ran batch    |Date &amp; time of request    |Batch was deleted |

**excel_mgr_log**

|excel_mgr_log_id   |excel_mgr_batch_id                              | row                                    |msg                |row_json             |
|-------------------|------------------------------------------------|----------------------------------------|-------------------|---------------------|
|Primary Key        | excel_mgr_batch_id form excel_mgr_batch table  |Row from source that msg occurred on    |Error message      | JSON encoded row    |

NOTE: All tables to be loaded must have these columns:

    `deleted` tinyint(1) NOT NULL  
    `excel_mgr_batch_id` bigint(20) NOT NULL AUTO_INCREMENT


In addition to any other tables needed for the specific application, the `excel_mgr_batch` and `excel_mgr_log` tables are required.

    DROP TABLE IF EXISTS `excel_mgr_batch`;
    CREATE TABLE IF NOT EXISTS `excel_mgr_batch` (
    `excel_mgr_batch_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `project_id` bigint(20) NOT NULL,
    `file_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `tmp_name` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
    `tab` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `first_row_names` tinyint(1) NOT NULL,
    `table_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `map` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
    `pid` bigint(20) DEFAULT NULL,
    `status` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
    `log_file` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
    `uptd_usr_id` int(11) NOT NULL,
    `updt_dtm` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted` tinyint(1) NOT NULL,
    PRIMARY KEY (`excel_mgr_batch_id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=7 ;


    DROP TABLE IF EXISTS `excel_mgr_log`;
    CREATE TABLE IF NOT EXISTS `excel_mgr_log` (
    `excel_mgr_log_id` bigint(20) NOT NULL AUTO_INCREMENT,
    `excel_mgr_batch_id` bigint(20) NOT NULL,
    `row` bigint(20) NOT NULL,
    `msg` text COLLATE utf8_unicode_ci NOT NULL,
    `row_json` text COLLATE utf8_unicode_ci NOT NULL,
    PRIMARY KEY (`excel_mgr_log_id`),
    KEY `excel_mgr_batch_id` (`excel_mgr_batch_id`),
    KEY `excel_mgr_batch_id_2` (`excel_mgr_batch_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

This is a sample of an import data table. Note that all data goes into a TEXT field. Data typing happens during the parsing.

    CREATE TABLE `import_stage` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `excel_mgr_batch_id` bigint(11) DEFAULT NULL,
      `deleted` tinyint(1) DEFAULT NULL,
      `revision_id` int(11) unsigned NOT NULL,
      `mpd_item_number` text,
      `amm_reference` text,
      `cat` text,
      `pgm` text,
      `task` text,
      `zone` text,
      `access` text,
      `applicability` text,
      `applicability_apl` text,
      `applicability_eng` text,
      `manhours` text,
      `task_title` text,
      `task_description` text,
      `type` text,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

##Files

File storage directories are required.
***** Need details *******

##Utility Function

Generate an Excel column list
        
    $letterList = new ExcelMgr_ExcelMgrUtility();
    $columns = $letterList->columnAlphabet(133); // pass in the number of columns
    // Returns an array of letters. A-Z AA-AZ BA-BZ and so on.
    // In this example A-EC






##Basic Load Logic

The load logic will look for the deleted and excel_mgr_batch_id columns in the destination table. During the load deleted will be set to 1 (true) and the excel_mgr_batch_id will be set to the excel_mgr_batch_id from the excel_mgr_table.

On a successful load all the records from that batch will have the deleted column set to 0 (false).

On an un-successful load all the records from that batch will be truly deleted from the table.

##JavaScript

http://stephen.rees-carter.net/2012/03/automatic-javascript-minification-in-zend-framework/