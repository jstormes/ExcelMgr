<?php

class ExcelMgr_ExcelToTable
{
	
	
	/** @var Zend_Db_Table */
	public $Batch_Row;
	
	/** @var Zend_Db_Table */
	public $destTable;
	
	
	public function __construct($batch_id) {
		ini_set('memory_limit', '2G');
		
		$this->log = Zend_Registry::get('log');
		
		$Batch = new ExcelMgr_Models_ExcelMgrBatch();
		
		$this->batch_id = $batch_id;
		
		$this->Batch_Row=$Batch->find($batch_id)->current();
		
		$this->file_name  = $this->Batch_Row->file_name;
		$this->tmp_name   = $this->Batch_Row->tmp_name;
		$this->tab        = $this->Batch_Row->tab;
		$this->table_name = $this->Batch_Row->table_name;
		$this->project_id = $this->Batch_Row->project_id;
		$this->first_row_names = $this->Batch_Row->first_row_names;
		
		$this->map        = json_decode($this->Batch_Row->map,true);
		
		//$this->log->debug($this->batch_id);
		//$this->log->debug($this->Batch_Row);
		//print_r($this->map);
		
	}
	
	
	function load() {
		
		$start_time = time();
		
		$LogTable = new ExcelMgr_Models_ExcelMgrLog();
		
		$dbAdapter = Zend_Db_Table::getDefaultAdapter();
		
		$this->destTable = new Zend_Db_Table($this->table_name);
		
		$metadata = $this->destTable->info('metadata');
		
		$xlsx = new ExcelMgr_SimpleXLSX($this->tmp_name);
		
		$worksheetDimension = $xlsx->dimension($this->tab);
		
		
		$LastColumn = $worksheetDimension[0];
		$TotalRows	= $worksheetDimension[1];
		
		
		echo "Total Rows ".$TotalRows."\n";
		$error_cnt=0;
		$map=$this->map;
		
		$map2=array();
		foreach($map as $k=>$v) {
			if ($v!='ignore')
				$map2[$k]=$v;
		}
		
		$map=$map2;
		$map[] = "project_id";
		$map[] = "excel_mgr_batch_id";
		$map[] = "deleted";
		
		//$str_columns = implode(",",$map);
		
		$str_columns = " (`".implode("`, `", $map)."`)";
		
		
		
		$tmp_str = array();
		foreach ($map as $m)
			$tmp_str[]="?";
		
		$pos_str = implode(",",$tmp_str);
		
		$sql = "INSERT INTO {$this->table_name} {$str_columns}
				VALUES ({$pos_str})";
		
		echo "\n";
		print_r($map);
		echo "\n";
		print_r($sql);
		echo "\n";
		
			
		$stmt = $dbAdapter->prepare($sql);
		
		$ws=$xlsx->worksheet( $this->tab );
		list($cols,) = $xlsx->dimension( $this->tab );
		
		$backgound_columns = array();
		$backgound_columns[] = 'project_id';
		$backgound_columns[] = 'excel_mgr_batch_id';
		$backgound_columns[] = 'deleted';
		
		for($i=1;$i<$TotalRows;$i++) {
			$row = $xlsx->row($i,$ws,$cols);
			
			$new_row = array();
			foreach($map as $k=>$v) {
			
				if (!in_array($v,$backgound_columns)) {
				
					if ($metadata[$map[$k]]['DATA_TYPE']=='date')
						$new_row[]=date('c',($row[$k] - 25569) * 86400);
					else {
						if ($metadata[$map[$k]]['DATA_TYPE']=='varchar') {
							if (strlen($row[$k])>$metadata[$map[$k]]['LENGTH']) {
								$error_cnt++;
								echo "Error on row {$i} data to to big for column {$v}.\n";
							}
						}
						switch ($metadata[$map[$k]]['DATA_TYPE']) {
							case "bigint":
							case "int":
								if (is_numeric($row[$k]))
									$new_row[]=(int)$row[$k];
								else 
									$new_row[]=null;
								break;
							case "varchar":
							case "text":
								$new_row[]=(string)$row[$k];
								break;
						}
						
					}
				}
			}
			
			$row=$new_row;
			$row[]=$this->project_id;
			$row[]=$this->batch_id;
			$row[]=1;
			
			
			if ($error_cnt>20)
				break;

			if ($i%1000==0) {
				$this->Batch_Row->status="{$i}/{$TotalRows}";
				$this->Batch_Row->save();
				//unset($xlsx);
				//gc_collect_cycles();
				//$xlsx = new ExcelMgr_SimpleXLSX($this->tmp_name);
				
			}
			
			try {
				$stmt->execute($row);	
				
			}
			catch (Exception $Ex) {
				// Catch errors
				$error_cnt++;
				echo "Error on row {$i}, ".$Ex->getMessage()."\n";
						$log_row = $LogTable->createRow();
						$log_row->excel_mgr_batch_id = $this->batch_id;
						$log_row->row = json_encode($row);
						$log_row->msg = $Ex->getMessage();
						print_r($row);
			}
			unset($row);
			unset($new_row);
			//gc_collect_cycles();

		}


		if ($error_cnt!=0) {
			// Delete this batch from the table.
			$where = $this->destTable->getAdapter()->quoteInto('excel_mgr_batch_id = ?', $this->batch_id);
			$this->destTable->delete($where);
			return false;
		}

		$end_time = microtime(true);

		$run_time = $end_time-$start_time;

		$hours = floor($run_time / 3600);
		$mins = floor(($run_time - ($hours*3600)) / 60);
		$secs = floor($run_time % 60);
		$remainder = round(fmod($run_time,60), 4);
		$secs = $secs + $remainder;

		echo "start time: {$start_time}\n";
		echo "end time: {$end_time}\n";
		echo "run time: ".$hours."h ".$mins."m ".$secs."s\n";

		$where = $this->destTable->getAdapter()->quoteInto('excel_mgr_batch_id = ?', $this->batch_id);
		$this->destTable->update(array('deleted'=>0), $where);


		return true;













		//$TotalRows = $worksheetInfo[$this->tab]['totalRows'];
		//$LastColumn = $worksheetInfo[$this->tab]['lastColumnLetter'];

		//$objReader->setLoadSheetsOnly($worksheetNames[$this->tab]);
		//$objReader->setReadDataOnly(true); /* this */

		/**  Create a new Instance of our Read Filter  **/
		//$chunkFilter = new ExcelMgr_chunkReadFilter();
		/**  Tell the Reader that we want to use the Read Filter  **/
		//$objReader->setReadFilter($chunkFilter);

		//$BlockSize=250;

		$map=$this->map;


		echo "Ttoal Rows ".$TotalRows."\n";
		echo "Block Size ".$BlockSize."\n";

		$BlockCount = round($TotalRows/$BlockSize+0.5);
		echo "Block count ". $BlockCount . "\n";
		$error_cnt = 0;
		for($i=0;$i<=$BlockCount;$i++) {

			$rows = 10;
			$blockStart = $BlockSize*$i;
			$blockEnd = ($blockStart+$BlockSize);
			if ($blockStart==0) {
				if ($this->first_row_names==1) {
					$blockStart=2;
					//$blockEnd = ($blockStart+$BlockSize)-1;
				}
				else {
					$blockStart=1;
					//$blockEnd = ($blockStart+$BlockSize)-1;
				}
			}
			
				
			
			if ($blockEnd>$TotalRows)
				$blockEnd=$TotalRows+1;  
			$chunkFilter->setRows($blockStart,$blockEnd-$blockStart);
			$objPHPExcel = $objReader->load($this->tmp_name);
			//$blockEnd2 = $blockEnd-1;
			$sheetData = $objPHPExcel->getActiveSheet()->rangeToArray("A{$blockStart}:{$LastColumn}{$blockEnd}",null,false,false,true);
			//$columns = $sheetData[1];
			//echo "\n\n\n****************************************\n";
			//echo $BlockSize*$i."\n";
			//print_r($sheetData);
			$this->Batch_Row->status="{$blockStart}/{$TotalRows}";
			$this->Batch_Row->save();
			echo "Load Excel Rows $blockStart to $blockEnd\n";
			//echo "{$blockStart}/{$TotalRows}\n";
			//sleep(1);
			
			
			array_pop($sheetData);
			
			foreach($sheetData as $Row=>$Columns){
				//echo "row $Row\n";
				$NewRow=$this->destTable->createRow();
				
				if ($error_cnt>20)
					break;
				
				//print_r($Columns);
				foreach($Columns as $SourceColumnName=>$Value) {
					if (isset($map[$SourceColumnName])) {
						if ($map[$SourceColumnName]!='ignore') {
							switch ($metadata[$map[$SourceColumnName]]['DATA_TYPE']) {
								case 'date':
									$NewRow->$map[$SourceColumnName] = date('c',($Value - 25569) * 86400);
									break;
								case 'int':
								case 'bigint':
									if (is_numeric($Value))
										$NewRow->{$map[$SourceColumnName]}=$Value;
									else 
										$NewRow->{$map[$SourceColumnName]}=null;
									break;
								default:
									$NewRow->{$map[$SourceColumnName]}=$Value;
									break;
							}
// 							if ($metadata[$map[$SourceColumnName]]['DATA_TYPE']=='date') {
// 								$NewRow->$map[$SourceColumnName] = date('c',($Value - 25569) * 86400);
// 							}
// 							else {
// 								$NewRow->{$map[$SourceColumnName]}=$Value;
// 								if ($map[$SourceColumnName]=='descrepancy_txt') {
// 									$NewRow->descrepancy_txt="$Value";
// 								}	
// 							}
					
						}
					}
				}
				
				try {
					// Attempt insert
					//$this->log->info("Writing Row");
					$NewRow->project_id=$this->project_id;
					$NewRow->excel_mgr_batch_id=$this->batch_id;
					$NewRow->deleted=1;
					//print_r($NewRow->toArray());
					$id=$NewRow->save();
					
					
					//$this->log->info("Row $id written.");
					
				}
				catch (Exception $Ex) {
					// Catch errors
					$error_cnt++;
					echo "Error on row {$Row}, ".$Ex->getMessage()."\n";
					$log_row = $LogTable->createRow();
					$log_row->excel_mgr_batch_id = $this->batch_id;
					$log_row->row = json_encode($Columns);
					$log_row->msg = $Ex->getMessage();
				}
				unset($NewRow);
				gc_collect_cycles();
				
			}
			//echo "********************\n";
			$objPHPExcel->disconnectWorksheets();
			unset($objPHPExcel);
			unset($sheetData);
		}
		
		if ($error_cnt!=0) {
			// Delete this batch from the table.
			$where = $this->destTable->getAdapter()->quoteInto('excel_mgr_batch_id = ?', $this->batch_id);
			$this->destTable->delete($where);
			return false;
		}
		return true;
	}
	
	
	public function log() {
		
		$LogTable = new ExcelMgr_Models_ExcelMgrLog();
		
		$sel = $LogTable->select();
		$sel->where("excel_mgr_batch_id = ?", $this->batch_id);
		
		print_r($LogTable->fetchAll($sel)->toArray(),true);
		
	}
	
}