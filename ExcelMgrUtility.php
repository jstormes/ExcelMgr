<?php

class ExcelMgr_ExcelMgrUtility
{
    /************* Utility Functions *******************/


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