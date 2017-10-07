<?php

class Config
{
    const HOST = 'localhost';
    const USERNAME = 'root';
    const PSD = 'root';
    const DBNAME = 'weekly';
    const SORT = 3306;
}


class ORM
{
    private static $db;
    private static $connect;
    private $sql_type = '';
    private $table = '';
    private $sql = '';
    private $pre = [];
    private $fetch_flg = 0;
    private $st;

    private function __construct(){
    }
    private function __clone(){

    }


    /**
     * 保证单例类
     * @return ORM
     */
    public static function getdb():ORM{
        if (isset(self::$db)) {
            return self::$db;
        } else {
            self::$db = new self();
            self::newPDO(Config::HOST,Config::USERNAME,Config::PSD,Config::DBNAME,Config::SORT);
            return self::$db;
        }
    }


    /**
     * 连接数据库
     * @param $host mixed
     * @param $username mixed
     * @param $psd mixed
     * @param $dbname mixed
     * @param $sort int
     */
    private static function newPDO($host,$username,$psd,$dbname,$sort){
        try {
            self::$connect = new PDO("mysql:host=$host;sort=$sort;dbname=$dbname", $username, $psd);
            self::$connect->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        }catch (Exception $exception){
            die('连接数据库失败'.$exception);
        }
    }


    /**
     * 设定要查询的数据库
     * @param $table string
     * @return ORM
     * @throws Exception
     */
    public function table(string $table):ORM{
        $this->emptyInfo();
        $this->table = $table;
        return $this;
    }


    /**
     * 查询语句
     * @param $filed array
     * @return ORM
     * @throws Exception
     */
    public function select(... $filed):ORM{
        $this->checkInfo();
        $this->emptyInfo();
        $this->sql_type = 'SELECT';
        $filed = count($filed) ? join(',',$filed) : '*';
        $this->sql = "SELECT $filed FROM {$this->table}";
        return $this;
    }


    /**
     * 更新语句
     * @param $info array
     * @return ORM
     */
    public function update(array $info):ORM{
        $this->checkInfo();
        $this->emptyInfo();
        $this->sql_type = 'UPDATE';
        $this->sql = "UPDATE {$this->table} SET ";
        foreach ($info as $filed=>$value){
            $this->sql .= " $filed=?,";
            $this->pre[] = $value;
        }
        $this->sql = substr($this->sql,0,strlen($this->sql)-1);
        return $this;
    }


    /**
     * 插入语句
     * @param $info array
     * @return ORM
     */
    public function insert(array $info):ORM{
        $this->checkInfo();
        $this->emptyInfo();
        $this->sql_type = 'INSERT';
        foreach ($info as $filed=>$value){
            $pla[] = '?';
            $this->pre[] = $value;
        }
        $this->sql = "INSERT INTO {$this->table} (".join(',',array_keys($info)).") VALUE(".join(',',$pla).")";

        return $this;
    }


    /**
     * 删除语句
     */
    public function delete(){
        $this->checkInfo();
        $this->emptyInfo();
        $this->sql_type = 'DELETE';
        $this->sql = "DELETE FROM {$this->table}";
        return $this;
    }


    /**
     * WHERE子句
     * @param $filed string
     * @param $operator string
     * @param $aim mixed
     * @return mixed
     * @throws Exception
     */
    public function where(string $filed,string $operator,$aim):ORM{
        if (empty($this->sql))
            throw new Exception('尚未指定任何SQL语句');
        if ($this->sql_type == 'INSERT')
            throw new Exception('不能在INSERT语句中使用WHERE子句');

        if (strpos($this->sql,'WHERE') !== false) {
            $this->sql .= "AND $filed $operator ?";
            $this->pre[] = "$aim";
        } else {
            $this->sql .= " WHERE $filed $operator ?";
            $this->pre[] = "$aim";
        }
        return $this;
    }


    /**
     * 执行SQL
     * @param $flg bool
     * @return mixed
     * @throws Exception
     */
    public function exec(bool $flg=false){
        if (empty($this->sql)) {
            throw new Exception('尚未指定任何sql语句');
        }
        switch ($this->sql_type){
            case 'SELECT':
                return $this->execSelect($flg);
            case 'UPDATE':
                return $this->execUpdate();
            case 'INSERT':
                return $this->execInsert();
            case 'DELETE':
                return $this->execDelete();
            default:
                throw new Exception('错误的操作类型');
        }
    }


    /**
     * 执行SELECT操作
     * @param $flg
     * @return mixed
     * @throws Exception
     */
    private function execSelect(bool $flg){
        if ($this->fetch_flg == 0) {
            if (strpos($this->sql,'WHERE') !== false) {
                $res = $this->setSt();
                if (!$res) {
                    throw new Exception('预处理语句执行失败');
                }
            }else{
                $this->st = self::$connect->query($this->sql);
            }
            $this->pre = '';
            $this->sql = '';
            $this->table = '';
        }

        if ($flg) {
            $res = $this->st->fetchAll(PDO::FETCH_ASSOC);
            $this->emptyInfo();
            return $res;
        } else {
            $res = $this->st->fetch(PDO::FETCH_ASSOC);
            if ($res !== false){
                $this->fetch_flg = 1;
                return $res;
            }else{
                $this->emptyInfo();
                return false;
            }
        }
    }


    /**
     * 执行更新操作
     * @return bool
     */
    private function execUpdate():bool{
        return $this->setSt();
    }


    /**
     * 执行删除操作
     * @return bool
     */
    private function execDelete():bool{
        if (strpos($this->sql,'WHERE') !== false) {
            $res = $this->setSt();
        }else{
            $res = self::$connect->exec($this->sql);
        }
        $this->emptyInfo();
        return $res;
    }


    /**
     * 执行插入操作
     * @return bool
     */
    private function execInsert():bool{
        $this->st = self::$connect->prepare($this->sql);
        $res = $this->st->execute($this->pre);
        return $res;
    }


    /**
     * 清空sql信息
     */
    private function emptyInfo(){
        $this->sql = '';
        $this->sql_type = '';
        $this->pre = '';
        $this->fetch_flg = 0;
        unset($this->st);
    }


    /**
     * 检查是否完成前置操作
     * @throws Exception
     */
    private function checkInfo(){
        if (!empty($this->sql_type))
            throw new Exception('上一条其他类型的SQL语句尚未执行');
        if (empty($this->table))
            throw new Exception('尚未设置需要操作的数据表(table)');
    }


    /**
     * 执行预处理语句
     * @return mixed
     */
    private function setSt(){
        $this->st = self::$connect->prepare($this->sql);
        $res = $this->st->execute($this->pre);
        return $res;
    }



}