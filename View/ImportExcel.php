<?php


class ExcelMgr_View_ImportExcel 
{

	/** @var Zend_Layout */
	public $layout;

	/** @var Zend_View */
	public $modalView;

	public $name;

	public $options = array();

	/** @var Zend_log */
	public $log;

	public $file_meta;

	public $dest_meta;

	public $destTable;

	public $source_table = null;

	public $source_table_idx = null;

	public $project_id = null;

	/**
	 *
	 *
	 * By: jstormes Oct 23, 2013
	 *
	 * @param unknown $name
	 * @param PHPSlickGrid_Db_Table $destination
	 * @param unknown $options
	 */
	public function __construct($name, $destination, $project_id, $options){

		$this->project_id = $project_id;

		$this->log = Zend_Registry::get('log');

		$this->log->debug("Const");

		
		$this->destTable=$destination;

		$this->dest_meta=$destination->info('metadata');


		$this->name=$name;

		/* Set our defaults */
		$_defaults = array(
				'HTML'=>'Upload',
				'Title'=>'Upload File',
				'Help'=>'Select the file to upload.'
		);
			
		/* Merge our defaults with the options passed in */
		$this->options = array_merge($_defaults,$options);


		/* The layout is not part of the current view
		 * you have to grab a copy of the layout to
		* change values in the header and footer.
		*/
		$this->layout = Zend_Layout::getMvcInstance();

		// if (AJAX) {
		//http://davidwalsh.name/detect-ajax
		//
		//$this->layout->disableLayout();
		//}

		/* *********************************************
		 * Figure out what step in the load we are on:
		*
		* 0 - Nothing uploaded just icon/button shown.
		* *********************************************/
		$step = 0;

		/* if we have am uploaded file from the this instance
		 * of the class, check for error and process it.
		*/
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

		/*******************************************************************
		 * If we have "load" post from step 0 then check for errors and
		* move on the step 1.
		******************************************************************/
		if (isset($_POST[$this->name.'-step1'])) {
			$step=1;
			$this->file_meta = $_POST['file_meta'];
			$this->log->debug($this->file_meta);
				
		}

		if (isset($_POST['Load']))
			$step=2;

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
		return "<a href='#$name' role='button' data-toggle='modal' title='$Title'>$HTML</a>";
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
		$this->modalView = new Zend_View();
		$this->modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );

		/* Calculate the maximum possible upload size from the *.ini setting */
		$upload_max_filesize 			= $this->return_bytes(ini_get('upload_max_filesize'));
		$post_max_size 					= $this->return_bytes(ini_get('post_max_size'));
		$this->modalView->MAX_FILE_SIZE = ($upload_max_filesize<$post_max_size?$upload_max_filesize:$post_max_size)-1;

		$this->modalView->name=$this->name;
		$this->modalView->Title=$this->options['Title'];
		$this->modalView->Help=$this->options['Help'];

		$this->layout->modals .= $this->modalView->render('ModalUpload.phtml');
	}

	/**
	 * Present the modal to map the source columns to destination columns
	 * (Step 1).
	 *
	 * By: jstormes Oct 25, 2013
	 *
	 */
	public function MapData() {

		/* Create modal by using the Zend_View similar to using the
		 * view from the controller.
		*/
		$this->modalView = new Zend_View();
		$this->modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );

		/* Database adaptor like Excel file */
		$Excel = new PHPSlickGrid_Excel($this->file_meta['tmp_name']);

		// recover the selected source table if set
		if (isset($_POST['source_table_idx'])) {
			$this->source_table_idx=$_POST['source_table_idx'];
			if (isset($_POST['firstRowNames'])) {
				if ($_POST['firstRowNames']==0)
					$Excel->firstRowNames=0;
				else
					$Excel->firstRowNames=1;
				$this->log->debug(array("firstRowNames"=>$_POST['firstRowNames']));
				$this->log->debug($_POST);
			}
		}
			
		// Set the default sheet in the drop down.
		if ($this->source_table_idx===null) {
			$this->source_table_idx=$Excel->getCurrentSheetIdx();
		}



		$dest_options = array();
		$dest_options['ignore']="(ignore)";
		foreach($this->dest_meta as $column_name=>$schema) {
			$dest_options[$column_name]=$column_name." (".$schema['DATA_TYPE']." ".$schema['LENGTH'].")";
		}

		$source_tables=$Excel->listTables();
		$tableInfo = $Excel->describeTable($source_tables[$this->source_table_idx]);
		//$this->log->debug($tableInfo);


		// Build Map
		// Mapping[(Source Column)]=(Destination Column)
		$mapping = array();

		foreach($tableInfo as $column_name=>$schema) {
			if (isset($_POST['mapping'][$column_name]))
				$mapping[$column_name]=$_POST['mapping'][$column_name];
			else
				$mapping[$column_name]='';
		}

		//$this->log->debug($mapping);



		/* Set the template values */
		$this->modalView->name=$this->name;
		$this->modalView->firstRowNames=$Excel->firstRowNames;
		$this->modalView->source_table_idx=$this->source_table_idx;
		$this->modalView->source_tables=$source_tables;
		$this->modalView->source_tableInfo=$tableInfo;
		$this->modalView->file_meta = $this->hidden_array("file_meta", $this->file_meta);

		$this->modalView->dest_meta = $this->dest_meta;
		$this->modalView->mapping = $mapping;
		$this->modalView->dest_options = $dest_options;

		$this->modalView->SourceSchema = json_encode($tableInfo);
		$this->modalView->DestSchema = json_encode($this->dest_meta);

		/* Place our modal in with the other modals on current page */
		$this->layout->modals .= $this->modalView->render('PHPExcelLoader.phtml');

		/* Trigger the modal */
		echo "<script>\n";
		echo "$( document ).ready(function() {\n";
		echo "	$('#testmod').modal();\n";
		echo "});";
		echo "</script>\n";
	}

	/**
	 * Load the data from the source columns into the destination columns
	 * (Step 2).
	 *
	 * By: jstormes Oct 25, 2013
	 *
	 */
	public function LoadData() {
		/* Create modal by using the Zend_View similar to using the
		 * view from the controller.
		*/
		$this->modalView = new Zend_View();
		$this->modalView->setScriptPath( APPLICATION_PATH . '/../library/ExcelMgr/View/modals/' );

//		$log="";

		// Get mapping
		//$mapping=$_POST['mapping'];
		//$sourceTableIdx = $_POST['source_table_idx'];

		//$this->log->debug($mapping);

		$Batch = new ExcelMgr_Models_ExcelMgrBatch();
		
		$Batch_Row = $Batch->createRow();

		$Batch_Row->project_id  = $this->project_id;
		$Batch_Row->file_name   = $this->file_meta['name'];
		$Batch_Row->tmp_name 	= $this->file_meta['tmp_name'];
		$Batch_Row->tab 		= $_POST['source_table_idx'];
		$Batch_Row->map 		= json_encode($_POST['mapping']);
		$Batch_Row->table_name 	= $this->destTable->info('name');
		
		$Batch_id=$Batch_Row->save();
		
		$this->log->debug($Batch_id);
		
		$Loader = new ExcelMgr_ExcelToTable($Batch_id);
		
		$Loader->load();
		
		$this->modalView->log = $Loader->log();

		$this->layout->modals .= $this->modalView->render('LoadData.phtml');

		/* Trigger the modal */
		echo "<script>\n";
		echo "$( document ).ready(function() {\n";
		echo "	$('#testmod').modal();\n";
		echo "});";
		echo "</script>\n";
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