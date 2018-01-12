<?php
namespace CatPHP\DB;

class Sql
{
    public  $tableName;
    public  $queryType = 's';
    private $columns   = '*';
    public  $where = [];
    public  $whereString = '';
    public  $sql;
    public  $orderBy = '';
    public  $groupBy = '';
    public  $joinStr = '';
    function __construct($tableName)
    {
        $this->tableName = $tableName;
    }
    public function sql($bindParam=false)
    {
        if ($this->queryType==='s') {
            return "select {$this->columns} from {$this->tableName} "
            .$this->joinStr
            .$this->makeWhere($bindParam)
            .$this->groupBy
            .$this->orderBy
            ;
        }elseif ($this->queryType==='i') {
            # code...
        }
        elseif ($this->queryType==='u') {
            # code...
        }
        elseif ($this->queryType==='d') {
            # code...
        }
        return $this->where;
    }
    private function makeWhere($bindParam)
    {
            $whereStr = '';
            foreach ($this->where as $i=>$w) {
                if (is_array($w[1])) {
                    if ($bindParam) {
                        $w[1] = '('. rtrim(str_repeat('?,', count($w[1]) ),',') .')';
                    }else{
                        $w[1] = '('.implode(',',  array_map([$this,'pendingType'],$w[1])  ).')';
                    }
                    if($w[2]=='not in')
                    {
                        $w[2] = 'not in';
                    }else{
                        $w[2] = 'in';
                    }
                }
                elseif ($w[1]==='null') {
                    $w[2] = 'is';
                }
                elseif ($w[2]=='like') {
                    $w[1] = "'$w[1]'";
                }
                if (!is_array($w[1]) && $bindParam && $w[2]!='in' && $w[2]!='not in') {
                    $w[1] = '?';
                }
                if ($i==0) {
                    $whereStr.=" where {$w[0]} {$w[2]} $w[1]";
                }else{
                    $whereStr.=" {$w[3]} {$w[0]} {$w[2]} $w[1]";
                }
                // if ($i==($len-1)) {
                //     $whereStr.=$w[0].' '.$w[2].' '.$w[1];
                // }else{
                //     $whereStr.=$w[0].' '.$w[2].' '.$w[1].' '.$w[3].' ';
                // }
            }
        // }
        if (!empty($this->whereString)) {
            $whereStr .= ' and '.$this->whereString;
        }
        return $whereStr;
    }
    public function whereString($whereStr)
    {
        $this->whereString = $whereStr;
    }
    public function where($where,$value,$condition='=',$logic='and')
    {
        // 如果没有绑定参数
        // if ($bindParams) {
        //     $this->where = $where;
        // }else{
        // }
        // if (is_array($value)) {
        //     $value = '('.implode(',',  array_map([$this,'pendingType'],$value)  ).')';
        //     $condition = 'in';
        // }
        // elseif ($value=='null') {
        //     $condition = 'is';
        // }
        // elseif ($condition=='like') {
        //     $value = "'$value'";
        // }
        if ($value===false || $value==='') {
            return ;
        }
        $this->where[] = [$where,$value,$condition,$logic];
        // return $this;
    }
    public function getBindParam()
    {
        $paramArray = [];
        foreach ($this->where as $p) {
            if (is_array($p[1])) {
                $paramArray = array_merge($paramArray,$p[1]);
            }else{
                $paramArray[] = $p[1];
            }
        }
        return $paramArray;
    }
    public function insert($data)
    {
        $columns = [];
        $values  = [];
        foreach ($data as $key => $value) {
            $columns[] = "`$key`";
            $values[]  = '?';
        }
        return "insert into {$this->tableName} (".implode(',', $columns).") values(".implode(',', $values).")";
    }
    public function update($data)
    {
        $columns  = [];
        foreach ($data as $key => $value) {
            $columns[] = "`$key`".' = ?';
        }
        $whereStr = $this->makeWhere(true);
        if ($whereStr) {
            return "update {$this->tableName} set ".implode(',', $columns).$whereStr;
        }else{
            throw new Exception("where Not Specified ", 1);
        }
    }
    public function increment($column,$value=1)
    {
        return "update {$this->tableName} set $column=$column+$value ".$this->makeWhere(true);
    }
    public function delete()
    {
        $whereStr = $this->makeWhere(true);
        if ($whereStr) {
            return "delete from {$this->tableName}".$whereStr;
        }else{
            throw new Exception("where Not Specified ", 1);
        }
    }
    public function groupBy()
    {
        if (is_string($groupBy)) {
            $this->groupBy = ' group by '.$groupBy;
        }
    }
    public function orderBy($orderBy)
    {
        if (is_string($orderBy)) {
            $this->orderBy = ' order by '.$orderBy;
        }
    }
    public function join($joinStr)
    {
        if (is_string($joinStr)) {
            $this->joinStr = $joinStr;
        }
    }
    public function select($columns)
    {
        if (is_string($columns)) {
            $this->columns = $columns;
        }
    }
    private static function pendingType(&$param)
    {
        if (is_numeric($param)) {
            return $param;
        }else{
            return "'$param'";
        }
    }
}
