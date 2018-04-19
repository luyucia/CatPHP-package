<?php
namespace CatPHP\DB;

use CatPHP\DB\Sql;
use \PDO;

class DB
{
    private $dbh            = null;
    private $page           = false;
    public  $sqlObj         = null;
    public  $cache          = null;
    public  $cacheNameSpace = '';
    public  $cacheEnable    = false;
    private $config         = [];
    private $sqlStr         = array();
    public $totalPageKey    = 'totalPage';
    public $totalRowsKey    = 'totalRows';

    function __construct($config)
    {
        if (is_array($config)) {
            // todo 检查配置文件
            $this->checkConfig($config);
            $this->config = $config;
        }elseif (is_object($config)) {
            // 如果是pdo对象之间赋值
            $this->dbh = $config;
        }
    }


    public function connect()
    {
        if ($this->dbh) {
            return;
        }
        // 数据库类型不支持
        try
        {
            $this->dbh = new PDO("{$this->config['type']}:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['database']};charset={$this->config['charset']}", $this->config['username'], $this->config['password'],[PDO::ATTR_PERSISTENT => false,PDO::ATTR_TIMEOUT=>3]);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->dbh->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        }
        catch (PDOException $e)
        {
            print "Error!: " . $e->getMessage() . "<br/>";
            die();
        }
    }
    public function beginTransaction()
    {
        $this->connect();
        $this->dbh->beginTransaction();
    }
    public function commit()
    {
        $this->dbh->commit();
    }
    public function rollBack()
    {
        $this->dbh->rollBack();
    }
    public function lastInsertId()
    {
        return $this->dbh->lastInsertId();
    }
    public function close()
    {
        $this->dbh = null;
    }
    public function getPDO()
    {
        $this->connect();
        return $this->dbh;
    }
    public function setCache($cache)
    {
        $this->cache = $cache;
    }
    // 检查配置文件
    private function checkConfig($config)
    {

    }
    public function table($tableName)
    {
        $this->sqlObj         = new Sql($tableName,$this);
        $this->cacheNameSpace = $tableName;
        return $this;
    }
    function __call($name,$arguments)
    {
        // 调用sql构建对象中的方法
        call_user_func_array( [$this->sqlObj,$name], $arguments);
        return $this;
    }
    // public function where($where,$value,$condition='=',$logic='and')
    // {
    //     $this->sqlObj->where($where,$value,$condition='=',$logic='and');
    //     return $this;
    // }
    // public function select()
    // {
    //     $this->sqlObj->queryType('s');
    // }
    public function insert($data)
    {
        $this->connect();
        $this->sqlObj->queryType = 'i';
        // 批量
        if (isset($data[1]) && is_array($data[1]) ) {
            $sql  = $this->sqlObj->insert($data[0]);
            $this->stmt = $this->dbh->prepare($sql);
            # 二维
            foreach ($data as $row) {
                $i = 1;
                foreach ($row as $d) {
                    $this->stmt->bindValue($i++, $d);
                }
                $executeResult = $this->stmt->execute();
            }
        }else{
            // 单条
            $sql = $this->sqlObj->insert($data);
            $this->stmt = $this->dbh->prepare($sql);
            $i = 1;
            foreach ($data as $d) {
                $this->stmt->bindValue($i++, $d);
            }
            $executeResult = $this->stmt->execute();
        }
        $this->notifyCacheUpdate();
        if($executeResult){
            return $this->dbh->lastInsertId();
        }else{
            return $executeResult;
        }
    }
    public function update($data)
    {
        $this->connect();
        $this->sqlObj->queryType = 'u';
        $sql = $this->sqlObj->update($data);
        // echo $sql;
        $this->stmt = $this->dbh->prepare($sql);
        $i = 1;
        foreach ($data as $d) {
            $this->stmt->bindValue($i++, $d);
        }
        // bind where
        $this->bindWhere($i);
        $executeResult = $this->stmt->execute();
        $this->notifyCacheUpdate();
        return $executeResult;
    }
    public function increment($column,$value=1)
    {
        $this->connect();
        $this->sqlObj->queryType = 'u';
        $sql = $this->sqlObj->increment($column,intval($value) );
        $this->stmt = $this->dbh->prepare($sql);
        // echo $sql;
        // bind where
        $this->bindWhere();
        $this->stmt->execute();
        $this->notifyCacheUpdate();
    }
    public function delete()
    {
        $this->connect();
        $this->sqlObj->queryType = 'd';
        $sql = $this->sqlObj->delete();
        $this->stmt = $this->dbh->prepare($sql);
        $this->bindWhere();
        $executeResult = $this->stmt->execute();
        $this->notifyCacheUpdate();
        return $executeResult;
    }
    public function sql($bindParam=false)
    {
        $sql =  $this->sqlObj->sql($bindParam);
        return $sql;
    }
    public function printSql($print=true)
    {
        if ($print) {
            print_r($this->sqlStr);
        }
        return $this->sqlStr;
    }
    public function execute($sql='',$bindParams=[])
    {
        $this->connect();
        $this->sqlStr[] = $sql;
        $this->stmt = $this->dbh->prepare($sql);
        return $this->stmt->execute($bindParams);
    }
    public function queryOne($sql,$bindParams=[])
    {
        if ($this->cacheEnable===true && $this->cache!=null) {
            if ($this->cacheKey) {
                $key = 'dc:'.$this->cacheNameSpace.':'.$this->cacheKey;
            }else
            {
                $key = 'dc:'.$this->cacheNameSpace.':'.md5($sql.implode('', $bindParams));
            }
            $rs =  unserialize($this->cache->get($key)) ;
            if ($rs!==false) {
                return $rs;
            }else{
                $this->connect();
                $this->sqlStr[] = $sql;
                $this->stmt = $this->dbh->prepare($sql);
                $this->stmt->execute($bindParams);
                $rs =  $this->stmt->fetch(PDO::FETCH_ASSOC);
                $this->cache->setex($key,$this->cacheTime,serialize($rs) );
                return $rs;
            }
        }else{
            $this->connect();
            $this->sqlStr[] = $sql;
            $this->stmt = $this->dbh->prepare($sql);
            $this->stmt->execute($bindParams);
            return $this->stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    private function runQuery($sql,$bindParams=[])
    {
        // echo $sql;
        // 如果开启缓存，并且设置了缓存
        // var_dump($this->cacheEnable);
        // echo $sql.'<br>';
        if ($this->cacheEnable===true && $this->cache!=null) {
            if ($this->cacheKey) {
                $key = 'dc:'.$this->cacheNameSpace.':'.$this->cacheKey;
            }else
            {
                $key = 'dc:'.$this->cacheNameSpace.':'.md5($sql.implode('', $bindParams));
            }
            $rs =  unserialize($this->cache->get($key)) ;
            if ($rs!==false) {
                return $rs;
            }else{
                $this->connect();
                $this->sqlStr[] = $sql;
                $this->stmt = $this->dbh->prepare($sql);
                $this->stmt->execute($bindParams);
                $rs =  $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->cache->setex($key,$this->cacheTime,serialize($rs) );
                return $rs;
            }
        }else{
            // echo $sql;
            // echo "$key";
            $this->connect();
            $this->sqlStr[] = $sql;
            $this->stmt = $this->dbh->prepare($sql);
            $this->stmt->execute($bindParams);
            return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
    public function query($sql='',$bindParams=[])
    {
        if ($sql === '') {
            $autoGenerateSql = true;
        }else{
            $autoGenerateSql = false;
        }
        if ($autoGenerateSql) {
            // 动态生成sql
            $sql        = $this->sql(true);
            $bindParams = $this->sqlObj->getBindParam();
        }
        $oriSql = $sql;
        // var_dump($this->page);
        if ($this->page!=false)
        {
            $sql    = $this->pagingSql($sql);
            $rs['data'] = $this->runQuery($sql,$bindParams);
            // APP分页
            if ($this->appPage)
            {
                if(isset($rs['data'][$this->pageSize]))
                {
                    $rs['hasNext'] = true;
                    unset($rs['data'][$this->pageSize]);
                }
                else
                {
                    $rs['hasNext'] = false;
                }
            }
            else
            {
                $totalRows  = $this->runQuery( $this->totalPageSql($oriSql),$bindParams )[0]['total'];
                $rs[$this->totalPageKey] = ceil($totalRows/$this->pageSize);
                $rs[$this->totalRowsKey] = $totalRows;
            }
        }
        else
        {
            $rs =  $this->runQuery($sql,$bindParams);
        }
        $this->page        = false;
        $this->cacheEnable = false;
        $this->cacheKey    = false;
        return $rs;
    }
    public function getStatement($sql='',$bindParams=[])
    {
        $this->connect();
        $this->sqlStr[] = $sql;
        // echo $sql;
        $this->stmt = $this->dbh->prepare($sql);
        $this->stmt->execute($bindParams);
        // $rs =  $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        return $this->stmt;
    }
    public function cache($time = 600,$cacheNameSpace = false,$key = false)
    {
        $this->cacheEnable    = true;
        $this->cacheTime      = $time;
        if ($cacheNameSpace) {
            $this->cacheNameSpace = $cacheNameSpace;
        }else{
            if (isset($this->sqlObj->tableName)) {
                $this->cacheNameSpace = $this->sqlObj->tableName;
            }else{
                throw new Exception("the param cacheNameSpace must be set", 1);
            }
        }
        $this->cacheKey       = $key;
        return $this;
    }
    // 通知缓存数据变更
    public function notifyCacheUpdate($cacheNameSpace=null)
    {
        // 传参数优先级高于默认
        if($cacheNameSpace!==null){
            $namespace = $cacheNameSpace;
        }else{
            $namespace = $this->cacheNameSpace;
        }
        if ($this->cache && $namespace!='') {
            $key = 'dc:'.$namespace.':*';
            // 遍历缓存名空间下的所有key，删除
            $ks = $this->cache->keys($key);
            foreach ($ks as $k) {
                $this->cache->delete($k);
            }
        }
    }
    private function bindWhere($i=1)
    {
        // bind where
        foreach ($this->sqlObj->where as $p) {
            if (is_array($p[1])) {
                foreach ($p[1] as $v) {
                    $this->stmt->bindValue($i++, $v);
                }
            }else{
                $this->stmt->bindValue($i++, $p[1]);
            }
        }
    }
    public function page($page,$size=20,$app=false)
    {
        $this->page     = $page;
        $this->appPage  =  $app;
        $this->pageSize = $size;
        return $this;
    }
    private function pagingSql($sql)
    {
        if($this->appPage)
        {
            // app列表分页
            $now      = ($this->page-1)*$this->pageSize;
            $offset   = $this->pageSize+1;
            $pageSql  = $sql." limit $now,$offset";
        }
        else
        {
            // 传统分页
            $now      = ($this->page-1)*$this->pageSize;
            $offset   = $this->pageSize;
            $pageSql  = $sql." limit $now,$offset";
        }
        return $pageSql;
    }
    private function totalPageSql($sql)
    {
        return $totalSql = "select count(*) as total from ($sql) as t";
    }
}
