<?php
class ExcelMgr_View_ImportExcel
{

	/**
	 * 
	 *
	 * By: jstormes Nov 7, 2013
	 *
	 * @param unknown $name
	 * @param unknown $destination
	 * @param unknown $project_id
	 * @param unknown $options
	 */
	public function __construct($name, $destination, $project_id, $options){
		
		$this->log = Zend_Registry::get('log');
		
		$this->name = $name;
		
		$this->destTable=$destination;
		
		$this->project_id = $project_id;
	
		/* Set our defaults */
		$_defaults = array(
				'HTML'=>'Upload',
				'Title'=>'Load Table',
				'Help'=>'Select file to upload:'
		);
		
		/* Merge our defaults with the options passed in */
		$this->options = array_merge($_defaults,$options);
		
		$this->table_name 	= $this->destTable->info('name');
		
		$this->dest_meta=$destination->info('metadata');
		
		/* The layout is not part of the current view
		 * you have to grab a copy of the layout to
		 * change values in the header and footer.  */
		$this->layout = Zend_Layout::getMvcInstance();
		
		$this->Controller();
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
				copy ($_FILES[$this->name]['tmp_name'], $tmp_name);
		
				/* Get the meta data about our file */
				$this->file_meta 				= $_FILES[$this->name];
				$this->file_meta['tmp_name']	= $tmp_name;
		
				/* The user is now on step 1 (file uploaded) */
				$step=1;
			}
		}
	}
	
	
	
	/**
	 * Controller Drives the modal views.
	 *
	 * By: jstormes Nov 7, 2013
	 *
	 */
	public function Controller() {
		
		/** TODO: This logic SUCKS, fix it!!! js  ***/
		
		
		/*
		 * Figure out what step in the load we are on:
		*
		* 0 - Not really a step waiting on a file
		* 1 - Mapping the input columns to the destination columns
		* 2 - File is being processed
		*/
		$step = 0;
		
		/* if we have am uploaded file from the this instance
		 * of the class, check for error and process it.
		*/
		if (isset($_FILES[$this->name])) {
			if (!empty($_FILES[$this->name]['name'])) {
				//$this->log->debug($_FILES);
				$step=1;
				$this->ProcessFile();
			}
		}
		
		
		/*******************************************************************
		 * If we have "load" post from step 0 then check for errors and
		* move on the step 1.
		******************************************************************/
		if (isset($_POST[$this->name.'-step1'])) {
			$step=1;
			$this->file_meta = $_POST['file_meta'];
			
		}
		
		if (isset($_POST['Load']))
			$step=2;
		
		if (isset($_GET['batchtablehistory']))
			$step=3;
		
		if (isset($_GET['loadhistory']))
			$step=4;
		
		if (isset($_POST['batch_id'])) {
			if (!empty($_POST['batch_id']))
				$step=5;
		}
		
		$this->ModalUpload();
		switch ($step) {
			case 0:
				$this->log->debug("step0");
				break;
			case 1:
				$this->MapData();
				$this->log->debug("step1");
				break;
			case 2:
				$this->LoadData();
				$this->log->debug("step2");
				break;
			case 3:
				$this->ListBatchHistory();
				break;
			case 5:
				$this->ModalLogView();
				break;
		}
		
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
		$name  = $this->name;
		$Title = $this->options['Title'];
		$HTML  = $this->options['HTML'];
	
		/* Return the templated string */
		return "<a href='#$name' id='{$name}Button' role='button' data-toggle='modal' title='$Title'>$HTML</a>";
	}
	
	public function ListBatchHistory() {
		
		$this->layout->disableLayout();
		
		$modalView = new Zend_View();
		$modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );
		
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
		
		
		
		echo $modalView->render('BatchHistoryTable.phtml');
		
		exit();
	}
	
	public function ModalLogView() {
		/* Create modal by using the Zend_View similar to using the
		 * view from the controller.
		*/
		$modalView = new Zend_View();
		$modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );
		
		$batch_id = $_POST['batch_id'];
		
		$Batch = new ExcelMgr_Models_ExcelMgrBatch();
		
		$record=$Batch->find($batch_id)->current();
		
		$modalView->record=$record;
		
		$this->layout->modals .= $modalView->render('LoadLog.phtml');
	}
	
	/**
	 * Push the upload modal into the modal section of the layout (Step 0).
	 *
	 * By: jstormes Oct 22, 2013
	 *
	 */
	public function ModalUpload() {
	
		/* Create modal by using the Zend_View similar to using the
		 * view from the controller.
		*/
		$modalView = new Zend_View();
		$modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );
	
		/* Calculate the maximum possible upload size from the *.ini setting */
		$upload_max_filesize 			= $this->return_bytes(ini_get('upload_max_filesize'));
		$post_max_size 					= $this->return_bytes(ini_get('post_max_size'));
		$modalView->MAX_FILE_SIZE = ($upload_max_filesize<$post_max_size?$upload_max_filesize:$post_max_size)-1;
	
		$modalView->name=$this->name;
		$modalView->Title=$this->options['Title'];
		$modalView->Help=$this->options['Help'];
		
		/**  Show history for this table's loads  **/
		$Batch = new ExcelMgr_Models_ExcelMgrBatch();
		$sel = $Batch->select();
		$sel->where("project_id = ?", $this->project_id);
		$sel->where("table_name = ?", $this->table_name);
		$sel->order('updt_dtm desc');
		$batch_history = $Batch->fetchAll($sel);
			
		$this->layout->modals .= $modalView->render('ModalUpload.phtml');
	}
	
	/**
	 * Returns an array of source column names for the given tab index.
	 * the returned array will have the Excel column name as the index.
	 * 
	 * if $firstRowNames = 1 it will scan the first row and use what is 
	 * found for the columns names
	 * 
	 * if $firstRowNames = 0 it will return the Excel column names as
	 * the columns names.
	 *
	 * By: jstormes Nov 7, 2013
	 *
	 * @param PHPExcel_Reader_IReader $objReader
	 * @param string $inputFileName
	 * @param integer $source_tab_idx
	 * @param integer $firstRowNames
	 * @return array array[ExcelColumn]=ColumnName
	 */
	public function GetExcelColumns($objReader, $inputFileName ,$source_tab_idx, $firstRowNames) {
		/**  Get dimension of the worksheet  **/
		$worksheetInfo = $objReader->listWorksheetInfo($this->file_meta['tmp_name']);
		$LastColumn = $worksheetInfo[$source_tab_idx]['lastColumnLetter'];

		/**  If we have no columns  **/
		if ($LastColumn=='@')
			return array();
		
		/**  Create a new Instance of our Read Filter  **/
		$chunkFilter = new ExcelMgr_chunkReadFilter();
		/**  Tell the Reader that we want to use the Read Filter  **/
		$objReader->setReadFilter($chunkFilter);
		/**  Starting at row 1 read 1 row  **/
		$chunkFilter->setRows(1,1);
		$objPHPExcel = $objReader->load($inputFileName);
		$sheetData = $objPHPExcel->getActiveSheet()->rangeToArray("A1:{$LastColumn}1",null,true,true,true);
		$columns = $sheetData[1];
		
		/**  Cleanup  **/
		unset($objPHPExcel);
		unset($sheetData);
		
		/**  Return Values  **/
		if ($firstRowNames==1) {
			return $columns;
		}
		else {
			$new_columns = array();
			foreach ($columns as $Name=>$Value) 
				$new_columns[$Name]=$Name;
			return $new_columns;
		}
	}
	
	public function MapData() {
		
		/* Create modal by using the Zend_View similar to using the
		 * view from the controller.  */
		$modalView = new Zend_View();
		$modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );
		
		//  $inputFileType = 'Excel5';
		$inputFileType = 'Excel2007';
		//	$inputFileType = 'Excel2003XML';
		//	$inputFileType = 'OOCalc';
		//	$inputFileType = 'Gnumeric';
		
		/* Create our Excel reader */
		$objReader = PHPExcel_IOFactory::createReader($inputFileType);
		$worksheetNames = $objReader->listWorksheetNames($this->file_meta['tmp_name']);
		
		
		
		
		/* Get our source tab and determine if first row contains column names */
		$firstRowNames=1;
		$worksheet_idx=0;
		if (isset($_POST['worksheet_idx'])) {
			$worksheet_idx=$_POST['worksheet_idx'];
			if (isset($_POST['firstRowNames'])) {
				if ($_POST['firstRowNames']==0)
					$firstRowNames=0;
				else
					$firstRowNames=1;
			}
		}
		
		/**  Get dimension of the worksheet  **/
		$worksheetInfo = $objReader->listWorksheetInfo($this->file_meta['tmp_name']);
		$this->log->debug($worksheetInfo);
		
		$LastColumn = $worksheetInfo[$worksheet_idx]['lastColumnLetter'];
		
		$objReader->setLoadSheetsOnly($worksheetNames[$worksheet_idx]);
		
		$SourceColums = $this->GetExcelColumns($objReader, $this->file_meta['tmp_name'], $worksheet_idx, $firstRowNames);
		
		/**  Destination Columns  **/
		$dest_options = array();
		$dest_options['ignore']="(ignore)";
		foreach($this->dest_meta as $column_name=>$schema) {
			$dest_options[$column_name]=$column_name." (".$schema['DATA_TYPE']." ".$schema['LENGTH'].")";
		}
		
		/* Set the template values */
		$modalView->name=$this->name;
		$modalView->file_meta = $this->hidden_array("file_meta", $this->file_meta);
		
		$modalView->worksheetNames=$worksheetNames;
		$modalView->worksheet_idx=$worksheet_idx;
		$modalView->firstRowNames=$firstRowNames;
		
		$modalView->source_columns = $SourceColums;
		$modalView->dest_options = $dest_options;
		
		/* Place our modal in with the other modals on current page */
		$this->layout->modals .= $modalView->render('MapData.phtml');
		
	}
	
	public function LoadData() {
		
		/* Create modal by using the Zend_View similar to using the
		 * view from the controller. */
		$modalView = new Zend_View();
		$modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );
		
		$Batch = new ExcelMgr_Models_ExcelMgrBatch();
		
		$Batch_Row = $Batch->createRow();
		
		$Batch_Row->project_id  = $this->project_id;
		$Batch_Row->file_name   = $this->file_meta['name'];
		$Batch_Row->tmp_name 	= $this->file_meta['tmp_name'];
		$Batch_Row->tab 		= $_POST['worksheet_idx'];
		$Batch_Row->map 		= json_encode($_POST['mapping']);
		$Batch_Row->table_name 	= $this->destTable->info('name');
		$Batch_Row->log_file    = tempnam ( sys_get_temp_dir() , "PHPlog" );
		$Batch_Row->first_row_names    = $_POST['firstRowNames'];
		
		$Batch_id=$Batch_Row->save();
		
		$this->run("../library/ExcelMgr/Scripts/ExcelToTable.php ".$Batch_id,$Batch_Row->log_file);
		
		for($i=0;$i<5;$i++) {
			sleep(1);
			if (!$this->daemonIsRunning($this->pid))
				break;
		}
				
		$modalView->log = file_get_contents($Batch_Row->log_file);
		
		$this->layout->modals .= $modalView->render('LoadData.phtml');
		
	}
	
	public function run($scrip,$outputFile = '/dev/null')
	{
		putenv("APPLICATION_ENV=".APPLICATION_ENV); 		// APPLICATION_ENV
		putenv("PHP_INCLUDE_PATH=". get_include_path()); 	// PHP_INCLUDE_PATH
		putenv("APPLICATION_PATH=". APPLICATION_PATH);		// APPLICATION_PATH
		$this->pid = shell_exec(sprintf(
				'php %s > %s 2>&1 & echo $!',
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
	 * Turn a simple one dimensional into a collection of hidden HTML inputs.
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
	
	
}
