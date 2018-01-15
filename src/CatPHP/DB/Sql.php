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
    public  $limit   = false;


    function __construct($tableName)
    {
        $this->tableName = $tableName;
    }
    public function sql($bindParam=false)
    {
        if ($this->queryType==='s') {
            $sql = "select {$this->columns} from {$this->tableName} "
            .$this->joinStr
            .$this->makeWhere($bindParam)
            .$this->groupBy
            .$this->orderBy;
            if ($this->limit) {
                $sql.= " limit {$this->limit}";
            }
            return $sql;
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
            $first    = true;

            foreach ($this->where as $w) {

                $column_name      = $w[0] ;
                $column_value     = $w[1];
                $column_condition = $w[2];
                $column_logic     = $w[3];

                if (is_array($column_value)) {
                    if ($bindParam) {
                        $column_value = '('. rtrim(str_repeat('?,', count($column_value) ),',') .')';
                    }else{
                        $column_value = '('.implode(',',  array_map([$this,'pendingType'],$column_value)  ).')';
                    }
                    if($column_condition == 'not in')
                    {
                        $column_condition = 'not in';
                    }else{
                        $column_condition = 'in';
                    }
                }
                elseif ($column_value==='null') {
                    $column_condition = 'is';
                }
                elseif ($column_condition=='like') {
                    $column_value = "'%$column_value%'";
                }else{
                    $column_value = $this->pendingType($column_value);
                }
                // 参数绑定处理
                if (!is_array($column_value) && $bindParam && $column_condition!='in' && $column_condition!='not in') {
                    $column_value = '?';
                }

                if ($first) {
                    $whereStr.=" where {$column_name } {$column_condition} $column_value";
                }else{
                    $whereStr.=" {$column_logic} {$column_name } {$column_condition} $column_value";
                }
                $first = false;

            }
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

        if ($value === false || $value === '' || $value === null) {
            return ;
        }
        $this->where[] = [$where,$value,$condition,$logic];
    }
    public function getBindParam()
    {
        $paramArray = [];
        foreach ($this->where as $p) {
            $condition = $p[2];
            $value     = $p[1];
            if (is_array($value)) {
                $paramArray = array_merge($paramArray,$p[1]);
            }elseif ($condition == 'like') {
                $paramArray[] = "%$value%";
            }
            else{
                $paramArray[] = $value;
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
    public function limit($limit)
    {
        if (is_numeric($limit)) {
            $this->limit = $limit;
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
