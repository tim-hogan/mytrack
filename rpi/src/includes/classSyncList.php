<?php
namespace devt\SyncList;
use DateTime;

class SyncList
{
    private $_strAuditFile;
    private $_fHandle;
    private $_seq;
    private $_list;
    private $_emptyFile;

    function __construct($strAuditFile,$start_seq=0)
    {
        $this->_strAuditFile = $strAuditFile;
        $this->_fHandle = null;
        $this->_list = array();
        $this->_seq = $start_seq;
        $this->_emptyFile = false;

        if (file_exists($strAuditFile) )
        {
            echo "Recovering from audit file {$strAuditFile}\n";
            $this->recoverFromAudit();
            $this->_fHandle = fopen($strAuditFile,"a");
        }
        else
        {
            echo "Creating new audit file {$strAuditFile}\n";
            $this->_fHandle = fopen($strAuditFile,"w");
            $this->_emptyFile = true;
        }

        if ( ! $this->_fHandle )
            throw(new Exception("Failed to open audit file"));
    }

    function __destruct()
    {
        if ($this->_fHandle)
            fclose($this->_fHandle);
    }

    private function recoverFromAudit()
    {
        $maxseq = -1;
        $hdr = array();
        $line = "";
        $seq = -1;

        //Make a copy of the file
        $strDT = (new DateTime())->format("YmdHis");
        copy($this->_strAuditFile,$this->_strAuditFile . "save-$strDT");

        $f = fopen( $this->_strAuditFile,"r");
        while (($data = fgetcsv($f, 1000, ",")) !== FALSE)
        {
            if (empty($hdr))
            {
                $hdr = $data;
            }
            else
            {
               foreach($data as $k => $v)
               {
                   $a[$hdr[$k]] = $v;
               }

               $seq = $a["seq"];
               $maxseq = max($maxseq,$seq);

               $type = $a["type"];

               unset($a["seq"]);
               unset($a["type"]);

               if ($type == 1)
                   $this->_list[$seq] = $a;
               if ($type == 0)
                   unset($this->_list[$seq]);
            }
        }
        fclose($f);

        //Now  rewrite the file
        $f = fopen( $this->_strAuditFile,"w");
        foreach($hdr as $v)
            $line .= ",{$v}";
        $line = trim($line,",");
        $line = trim($line);
        $line .= "\n";
        fwrite($f,$line);

        foreach($this->_list as $key => $a)
        {
            $line = "{$key},1";
            foreach($a as $v)
                $line .= ",{$v}";
            $line .= "\n";
            fwrite($f,$line);
        }
        fclose($f);

        $this->_seq = $maxseq + 1;
    }

    private function writeHeaderFromData($a)
    {
        $line = "seq,type";
        foreach($a as $key => $v)
        {
            $line .= ",{$key}";
        }
        $line .= "\n";
        if ($this->_fHandle)
            fwrite($this->_fHandle,$line);
    }

    public function insert($a)
    {
        $line = "";
        if ($this->_emptyFile)
        {
            $this->writeHeaderFromData($a);
            $this->_emptyFile = false;
        }

        $this->_list[$this->_seq] = $a;


        $line .= strval($this->_seq) . ",1";
        foreach($a as $v)
        {
            $line.= "," . $v;
        }

        $line .= "\n";
        if ($this->_fHandle)
            fwrite($this->_fHandle,$line);

        $this->_seq++;
    }

    public function remove($seq)
    {
        $line = "";

        unset($this->_list[$seq]);
        $line .= strval($seq) . ",0\n";
        if ($this->_fHandle)
            fwrite($this->_fHandle,$line);
    }

    public function getFullList()
    {
        return $this->_list;
    }

    public function count()
    {
        return count($this->_list);
    }

    public function inList($key,$value)
    {
        foreach($this->_list as $v)
        {
            if ($v[$key] == $value)
                return true;
        }
        return false;
    }
}
?>