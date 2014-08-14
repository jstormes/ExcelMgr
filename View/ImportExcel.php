<?php
/**
 * This class is the view helper for importing xlsx files into a database.
 * Currenlty this file uses a Zend_Db_Table_Abstract to gather meta-data about
 * the target MySQL table.  In the near future it will also accept a
 * PHPSlickGrid_Db_Table that will proide enchanced functions such as column
 * name alises, fields widths, etc... for now only the minimum data load function
 * are implmented.
 *
 * The load process can be viewed as a mini MVC with this class representing
 * the controler, the bootstrap modals representing the view and the
 * *_Db_Table_Abstract representing the model.
 *
 * The load proces is split into steps, with step 0 represting just presenting
 * the load HTML (Button).
 *
 * Steps/Views:
 *
 * 0 - No load in progress, only the HTML to initiaate the load is preseneted.
 * 1 - Upload/History modal is presented, history is updated via AJAX partial
 *     with the current load status.
 * 2 - Mapping, the mapping modal is presented, allowing the user to map xlsx
 *     columns to the destination MySQL columns,
 * 3 - Load status, presents the load status in a modal, this is the same load
 *     status that is presented in step 1 but without the upload option.
 * 4 - Not a step per-say, presents the detailed log of a load in a modal
 *     window.
 *
 * This class expect that the layout has a <?php echo $this->layout()->modals; ?>
 * in the main body where all bootstrap modals can live.
 *
 * @author jstormes
 *
 */

class ExcelMgr_View_ImportExcel
{

    /**
     *
     *
     * By: jstormes Nov 7, 2013
     *
     * @param string $name
     * @param Zend_Db_Table_Abstract $destination
     * @param integer $project_id
     * @param array $options
     */
    public function __construct($name, $destination, $project_id, $options){

        // Get system log
        $this->log = Zend_Registry::get('log');
        // Get system config
        $this->config = Zend_Registry::get('config');

        /* Get the Zend_Layout */
        $this->layout = Zend_Layout::getMvcInstance();

        // expose our parameters to the reset of this class
        $this->name       = $name;
        $this->destTable  = $destination;
        $this->project_id = $project_id;

        /* Set our defaults */
        $_defaults = array(
                // 'HTML'    =>'Upload',
                'Title'   =>'Load Table',
                'Help'    =>'Select file to upload:',
                'btn_id'  =>'uploadButton',
                'btn_class'  => 'btn btn-primary',
                'btn_icon' => 'fa fa-upload',
                'btn_text' => 'Upload Excel File',
                'class'   =>null,
                'preload' =>null
        );

        /* Merge our defaults with the options passed in */
        $this->options = array_merge($_defaults,$options);

        // Get our table name from the model
        $this->table_name = $this->destTable->info('name');

        // Get the meta data from the model
        $this->dest_meta = $destination->info('metadata');

        // Get the primary key, must be only one!!!
        $this->primary_key = $destination->info('primary');
        $this->primary_key = $this->primary_key[1];

        // Set hidden columns -- _hidden is set in the model.
        $this->hidden = array();
        if(property_exists($destination, '_hidden')){
            $this->hidden = $destination->_hidden;
        }

        // Start the ImportExcel controller
        $this->Controller();
    }

    /**
     * Return a HTML anchor tag that can trigger the upload modal dialog (step 0).
     *
     * By: jstormes Oct 22, 2013
     *
     * @return string
     */
    public function Button() {
        /* Copy varables for template */
        $name  = $this->name;               // Control name
        $Title   = $this->options['Title'];   // title for on hover event
        // $HTML    = $this->options['HTML'];    // HTML of the button/link
        // $class   = $this->options['class'];   // CSS class of the button/link
        $preload = $this->options['preload']; // path to modal for saving meta data about the file

        $btn_id    = $this->options['btn_id'];    
        $btn_class = $this->options['btn_class']; 
        $btn_icon  = $this->options['btn_icon'];  
        $btn_text  = $this->options['btn_text'];  

        // build the button
        $formHTML = '<form>';
        $formHTML .= "<input type=\"hidden\" name=\"preload\" value = \"$preload\">";
        $formHTML .= "<a href='#{$name}_upload_modal' id='{$name}_button' role='button' data-toggle='modal' title='{$Title}'>";
        $formHTML .= "<button id=\"{$btn_id}\" class=\"{$btn_class}\"><span class=\"{$btn_icon}\">&nbsp;</span>{$btn_text}</button>";
        $formHTML .= "</a>";
        $formHTML .= "</form>";

        /* Return the templated string */
        return $formHTML;
    }

    public function Controller() {
        $this->log->debug("ImportExcel.php - Controller"); 
        $this->log->debug($this->options); 
        // if AJAX parameters are present
        if (isset($_GET['excel_mgr_ajax'])
            && isset($_GET['project_id'])
            && isset($_GET['table_name'])
            && isset($_GET['control_name'])
            && isset($_GET['procedure'])
        ) {
            // if AJAX parameters match our parameters
            if (($_GET['excel_mgr_ajax']==true)
                && ($_GET['project_id']==$this->project_id)
                && ($_GET['table_name']==$this->table_name)
                && ($_GET['control_name']==$this->name)
            ) {
                // This is an AJAX call that we have a procedure for
                if ($_GET['procedure']=="load_history")
                    $this->load_history();
            }
        }
        else {
            $this->log->debug($this->options['preload']);
            if ($this->options['preload']) {
                // allow a modal to capture human entred data for the upload
                $this->PreLoadModal();
            }
            // Add the upload/history modal to the view
            $this->UploadModal();

            if (isset($_POST['project_id'])
                && isset($_POST['table_name'])
                && isset($_POST['control_name'])
            ) {
                if (($_POST['project_id']==$this->project_id)
                    && ($_POST['table_name']==$this->table_name)
                    && ($_POST['control_name']==$this->name)
                    ) {
                        if (isset($_POST['batch_id'])) {
                            if ($_POST['batch_id']!=0) {
                                $this->LogModal();
                                return;
                            }
                        }

                        if (isset($_POST['form_name'])){
                            if ($_POST['form_name']=='upload') {
                                if ($this->ProcessFile()) {
                                    $this->MapModal();
                                }
                                else {
                                    echo "<script>\n";
                                    echo "alert('Error: Could not process file.');\n";
                                    echo "</script>\n";
                                }
                            }

                            if ($_POST['form_name']=='map') {
                                $this->file_meta = $_POST['file_meta'];
                                if (isset($_POST['Load'])){
                                    $this->LoadModal();
                                }else{
                                    $this->MapModal();
                                }
                            }
                        }
                }
            }
        }

    }

    /***********************************************************************
     * Modals
     **********************************************************************/
    /**
     * Push the upload modal into the modal section of the layout (Step 0).
     *
     * By: jstormes Oct 22, 2013
     *
     */
 
    public function PreLoadModal()
    {
        $this->log->debug("ImportExcel.php - PreLoadModal");
        $modalPath = $this->options['preload'];
    }



    public function UploadModal() {
        $this->log->debug("ImportExcel.php - UploadModal");
        /* Create modal by using the Zend_View similar to using the
         * view from the controller.
        */
        $modalView = new Zend_View();
        $modalView->setScriptPath( dirname(__file__) . '/modals/' );

        /* Calculate the maximum possible upload size from the *.ini setting.
         * These are passed to javascript in the modal so that browsers that
         * support it can prevent the user from trying to upload a file that
         * is too big for this server.
         */
        $upload_max_filesize            = $this->return_bytes(ini_get('upload_max_filesize'));
        $post_max_size                  = $this->return_bytes(ini_get('post_max_size'));
        $modalView->MAX_FILE_SIZE       = ($upload_max_filesize<$post_max_size?$upload_max_filesize:$post_max_size)-1;

        // Boilerplate modal view varables.
        $modalView->name  = $this->name;
        $modalView->Title = $this->options['Title'];
        $modalView->Help  = $this->options['Help'];
        $modalView->project_id = $this->project_id;
        $modalView->table_name = $this->table_name;

        // if callback then pass it on
        if( isset($_POST['callback'] )){        
            $modalView->callback = $_POST['callback'];
        }else{
            $modalView->callback = '';
        }

        /**  Show history for this table's loads  **/
        $Batch = new ExcelMgr_Models_ExcelMgrBatch();  // Model for load history.
        $sel = $Batch->select();
        $sel->where("project_id = ?", $this->project_id);
        $sel->where("table_name = ?", $this->table_name);
        $sel->order('updt_dtm desc');
        $batch_history = $Batch->fetchAll($sel);

        $this->layout->modals .= $modalView->render('upload.phtml');
    }

    public function MapModal() {
        $this->log->debug("ImportExcel.php - MapModal");  
        $this->log->debug( $_POST['worksheet_idx'] );  

        /* Create modal by using the Zend_View similar to using the
         * view from the controller.  */
        $modalView = new Zend_View();
        $modalView->setScriptPath( dirname(__file__) . '/modals/' );

        $xlsx = new ExcelMgr_SimpleXLSX($this->file_meta['tmp_name']);
        $worksheetNames = $xlsx->sheetNames();

        // hidden columns are set in the model and configured in init()
        $hidden = $this->hidden;

        // Get the worksheet to map
        if (isset($_POST['worksheet_idx'])){
            $worksheet_idx = $_POST['worksheet_idx'];
        }else{
            $worksheet_idx = 1;
        }

        // Get the row where the data starts
        if (isset($_POST['dataStartRow'])){
            $dataStartRow = $_POST['dataStartRow'];
        }else{
            $dataStartRow = 0;
        }

        // Does the first row have the column names
        if (isset($_POST['firstRowNames'])) {
            if ($_POST['firstRowNames'] == 0) {
                $firstRowNames = 0;
            }else{
                $firstRowNames = 1;
            }
        }

        /* Get our source tab and determine if first row contains column names */
        // $firstRowNames = 1;
        $ws = $xlsx->worksheet($worksheet_idx);
        
        // get some info about the worksheet.
        // $tableInfo[0] = columns
        // $tableInfo[1] = rows
        $tableInfo = $xlsx->dimension($worksheet_idx);

        if ($firstRowNames == 0) {      
            $SourceColumns = $this->columnAlphabet( $tableInfo[0] );
        }else{
            $SourceColumns = $xlsx->row(0, $ws, $tableInfo[0]);
        }
              
        $this->log->debug($ws);        
        $this->log->debug($worksheet_idx);        
        $this->log->debug($tableInfo);        
        $this->log->debug($tableInfo[0]);        
        $this->log->debug($tableInfo[1]);        
        $this->log->debug($firstRowNames);        
        $this->log->debug($SourceColumns);        

        /**  Destination Columns  **/
        $dest_options = array();
        $dest_options['ignore'] = "(ignore)";
        foreach($this->dest_meta as $column_name=>$schema) {
            $dest_options[$column_name]=$column_name." (".$schema['DATA_TYPE']." ".$schema['LENGTH'].")";
        }

        $dest_options = $this->removeHidden($dest_options, $hidden);

        // $this->log->debug($this->options);
        $mapping = array();
        if (isset($this->options['mapping'])) {
            //$this->log->debug("Mapping found");
            //$mapping = $this->options['mapping'];
            // $this->log->debug($SourceColumns);
            foreach($SourceColumns as $key=>$value) {
                if(array_key_exists ($value,$this->options['mapping'])){
                    //$this->log->debug("found");
                    $mapping[$key] = $this->options['mapping'][$value];
                }
                else {
                    $mapping[$key] = "(ignore)";
                }
            }
        }
        else {
            foreach($SourceColumns as $key=>$value) {
                // $this->log->debug($mapping[$key]);
                $mapping[$key] = $this->findClosestMatchingString($value,$dest_options);
            }
        }
        // $this->log->debug($dataStartRow);

        /* Boilerplate */
        $modalView->name           = $this->name;
        $modalView->Title          = $this->options['Title'];
        $modalView->Help           = $this->options['Help'];
        $modalView->project_id     = $this->project_id;
        $modalView->table_name     = $this->table_name;
        
        $modalView->file_meta      = $this->hidden_array("file_meta", $this->file_meta);
        
        $modalView->worksheetNames = $worksheetNames;
        $modalView->worksheet_idx  = $worksheet_idx;
        $modalView->firstRowNames  = $firstRowNames;
        $modalView->dataStartRow   = $dataStartRow;
        
        $modalView->source_columns = $SourceColumns;
        $modalView->dest_options   = $dest_options;
        
        $modalView->mapping        = $mapping;
        
        // if callback then pass it on
        if( isset($_POST['callback'] )){        
            $modalView->callback = $_POST['callback'];
        }else{
            $modalView->callback = '';
        }
        
        /* Place our modal in with the other modals on current page */
        $this->layout->modals .= $modalView->render('map.phtml');
    }

    public function LoadModal() {
        $this->log->debug("ImportExcel.php - LoadModal"); 
        /* Create modal by using the Zend_View similar to using the
         * view from the controller. */
        $modalView = new Zend_View();
        $modalView->setScriptPath( dirname(__file__) . '/modals/' );

        $Batch = new ExcelMgr_Models_ExcelMgrBatch();

        $Batch_Row                  = $Batch->createRow();
        
        $Batch_Row->project_id      = $this->project_id;
        $Batch_Row->file_name       = $this->file_meta['name'];
        $Batch_Row->tmp_name        = $this->file_meta['tmp_name'];
        $Batch_Row->tab             = $_POST['worksheet_idx'];
        $Batch_Row->map             = json_encode($_POST['mapping']);
        $Batch_Row->table_name      = $this->destTable->info('name');
        $Batch_Row->log_file        = tempnam ( sys_get_temp_dir() , "PHPlog" );
        $Batch_Row->first_row_names = $_POST['firstRowNames'];
        $Batch_Row->data_start_row  = $_POST['dataStartRow'];
        
        $Batch_Row->callback        = $_POST['callback'];        
        $Batch_Row->preloadData     = $_POST['preloadData'];        
        
        $Batch_id                   = $Batch_Row->save();

        if(property_exists($this->destTable, '_truncate')){
           if($this->destTable->_truncate){
               $this->destTable->getAdapter()->query('TRUNCATE TABLE '.$this->destTable->info(Zend_Db_Table::NAME));
           }
        }

        $this->run( dirname(__file__) . "/../Scripts/ExcelToTable.php ".$Batch_id, $Batch_Row->log_file);

        $this->log->debug("After Run");
        $j=0;
        for($i=0;$i<5;$i++) {
            $this->log->debug("Sleep $i");
            sleep(1);
            $j++;
            if (!$this->daemonIsRunning($this->pid))
                break;
        }
        $this->log->debug("After wait loop");

//      $modalView->log = file_get_contents($Batch_Row->log_file);

        // Boilerplate modal view varables.
        $modalView->name       = $this->name;
        $modalView->Title      = $this->options['Title'];
        $modalView->Help       = $this->options['Help'];
        $modalView->project_id = $this->project_id;
        $modalView->table_name = $this->table_name;

        $this->layout->modals .= $modalView->render('load.phtml');
    }

    public function LogModal() {
        $this->log->debug("ImportExcel.php - LogModal"); 
        /* Create modal by using the Zend_View similar to using the
         * view from the controller.
        */
        $modalView = new Zend_View();
        $modalView->setScriptPath( dirname(__file__) . '/modals/' );

        $batch_id = $_POST['batch_id'];

        $Batch = new ExcelMgr_Models_ExcelMgrBatch();

        $record=$Batch->find($batch_id)->current();

        $modalView->record=$record;

        $this->layout->modals .= $modalView->render('log.phtml');
    }

    /***********************************************************************
     * AJAX methods
     **********************************************************************/

    public function load_history() {
        $this->log->debug("ImportExcel.php - load_history"); 
        $this->layout->disableLayout();

        $modalView = new Zend_View();
        $modalView->setScriptPath( dirname(__file__) . '/modals/' );

        /**  Show history for this table's loads  **/
        $Batch = new ExcelMgr_Models_ExcelMgrBatch();
        $sel = $Batch->select();
        $sel->where("project_id = ?", $this->project_id);
        $sel->where("table_name = ?", $this->table_name);
        $sel->order('updt_dtm desc');
        $batch_history = $Batch->fetchAll($sel);

        // Search for any process that has crashed.
        foreach($batch_history as $record) {
            if (($record->status != 'Done')&&($record->status != 'Crashed')) {
                if (!$this->daemonIsRunning($record->pid)) {
                    $record->status = "Crashed";
                    $record->save();
                }
            }
        }

        $modalView->name = $this->name;

        $modalView->batch_history=$batch_history->toArray();

        echo $modalView->render('load_history_partial.phtml');

        exit();
    }

    /***********************************************************************
     * Support methods
     **********************************************************************/

    function removeHidden($ary,$hidden) {

        foreach($ary as $key=>$value) {
            if (in_array($key,$hidden))
                unset($ary[$key]);
            else
            if (in_array($value,$hidden))
                unset($ary[$key]);
        }

        return $ary;
    }

    // define the mapping function
    function findClosestMatchingString($s, $arr2) {
        $stringEditDistanceThreshold = 4;
        $closestDistanceThusFar = $stringEditDistanceThreshold + 1;
        $closestMatchValue      = null;

        foreach ($arr2 as $key => $value) {
            $editDistance = levenshtein(strtolower($key),  strtolower($s));
            //$this->log->debug($key." ".$s." ".$editDistance);

            // exact match
            if ($editDistance == 0) {
                return $key;

                // best match thus far, update values to compare against/return
            } elseif ($editDistance < $closestDistanceThusFar) {
                $closestDistanceThusFar = $editDistance;
                $closestMatchValue      = $key;
            }
        }

        //$this->log->debug($s." ".$closestMatchValue." ");
        return $closestMatchValue; // possible to return null if threshold hasn't been met
    }

    public function ProcessFile() {
        if (isset($_FILES[$this->name])) {
            if ($_FILES[$this->name]["error"] > 0)
            {
                /* Don't know how to test this JS */
                echo "<script>\n";
                echo "alert('Error: " . $_FILES[$this->name]["error"] . "');\n";
                echo "</script>\n";
            }
            else
            {
                /* Copy the uploaded file to a more "permanent" temp file */
                $tmp_name = tempnam ( sys_get_temp_dir() , "PHPslick" );
                if (copy ($_FILES[$this->name]['tmp_name'], $tmp_name)) {

                    /* Get the meta data about our file */
                    $this->file_meta                = $_FILES[$this->name];
                    $this->file_meta['tmp_name']    = $tmp_name;
                    return true;
                }
            }
        }
        return false;
    }

    public function run($scrip,$outputFile = '/dev/null')
    {
        $interpreter = $this->config->php_interpreter;
        //'%s %s > %s 1>&2 & echo $!',
        putenv("APPLICATION_ENV=".APPLICATION_ENV);         // APPLICATION_ENV
        putenv("PHP_INCLUDE_PATH=". get_include_path());    // PHP_INCLUDE_PATH
        putenv("APPLICATION_PATH=". APPLICATION_PATH);      // APPLICATION_PATH
        $this->pid = shell_exec(sprintf(
                '%s %s > %s 2>&1 &',
                $interpreter,
                $scrip,
                $outputFile
        ));
    }

    public function daemonIsRunning($pid) {
        exec('ps '.$pid,$output,$result);
        if( count( $output ) == 2 ) {
            return true; //daemon is running
        }
        return false;
    }

    /**
     * Turn a simple one dimensional array into a collection of hidden HTML inputs.
     *
     * By: jstormes Oct 22, 2013
     *
     * @param string $name
     * @param array $ary
     * @return string
     */
    function hidden_array($name,$ary) {

        $return_value = "\n<!-- array $name -->\n";

        foreach($ary as $key=>$value) {
            $return_value .= "<input type='hidden' name='".$name."[$key]' value ='$value' />\n";
        }

        $return_value .= "<!-- End array $name -->\n\n";

        return $return_value;
    }

    /**
     * Calculate the number of bytes from a php.ini
     * setting.
     *
     * By: jstormes Oct 8, 2013
     *
     * @param unknown $val
     * @return Ambigous <number, string>
     */
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        switch($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }

    /**
     * Return a list letters for Excel columns
     *
     * By: dgdavidson Aug 7, 2014
     *
     * @param integer $columns
     * @return Array
     */
    public function columnAlphabet($columns){
        $alphabet = Array();
        for ($na = 0; $na < $columns; $na++) {
            $alphabet[] = $this->generateAlphabet($na);
        }
        return $alphabet;
    }

    private  function generateAlphabet($na) {
        $sa = "";
        while ($na >= 0) {
            $sa = chr($na % 26 + 65) . $sa;
            $na = floor($na / 26) - 1;
        }
        return $sa;
    }
}