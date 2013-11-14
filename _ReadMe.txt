

ExcelMgr is a library for importing and exporting data from a database table to an
excel worksheet.

This library spawns a long running task that forks multiple loaders.  The idea is that
by breaking the file to be loaded up into section and having a fork for each section
the load should go allow faster.

http://stackoverflow.com/questions/45953/php-execute-a-background-process

PATHS:
======
/ExcelMgr/View/Modals/...
/ExcelMgr/View/ImportExcel.php
/ExcelMgr/View/ExportExcel.php
/ExcelMgr/Scripts/ExcelToTable.php
/ExcelMgr/Scripts/TableToExcel.php
/ExcelMgr/ExcelToTable.php
/ExcelMgr/TableToExcel.php


Tables:
=======

excel_mgr_batch:
-------------------------------------------------------------------------------------------------
| excel_mgr_batch_id | path       | table | map           | uptd_usr_id | updt_dtm    | deleted |
-------------------------------------------------------------------------------------------------
| Primary Key        | Path to    | SQL   | JSON encoded  | user_id of  | Date & time | Batch   |
|                    | excel file | Table | mapping array | user that   | of request  | was     |
|                    |            |       |               | ran batch   |             | deleted |
-------------------------------------------------------------------------------------------------
        ^
        |
        +---------------------+
                              |
excel_mgr_log:                |
----------------------------------------------------------------------------------------
| excel_mgr_log_id | excel_mgr_batch_id   | row              | msg      | row_json     |
----------------------------------------------------------------------------------------
| Primary Key      | excel_mgr_batch_id   | Row from source  | (Error)  | JSON encoded |
|                  | from excel_mgr_batch | that msg occured | Message. | row.         |
|                  | table.               | on.              |          |              |
----------------------------------------------------------------------------------------

NOTE:
All tables to be loaded must have the following columns:
bool   deleted
bigint excel_mgr_batch_id


BASIC LOAD LOGIC:
=================
The load logic will look for the deleted and excel_mgr_batch_id columns in 
the destination table.  During the load deleted will be set to 1 (true) 
and the excel_mgr_batch_id will be set to the excel_mgr_batch_id from the 
excel_mgr_table.

On an successful load all the records from that batch will have the deleted column
set to 0 (false).

On an un-successful load all the records from that batch will be truly deleted from 
the table.


Tables:

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





