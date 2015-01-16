<?php

/**
 * 数据库操作类
 */
class DbModel {

    public $db;
    public $table;
    public $opt;
    public $sql;
    public $sign = array("gt" => ">", "eq" => "=", "lt" => "<", "ge" => ">=", "le" => "<=");

    /**
     * 构造方法
     */
    public function __construct($tableName) {
        $this->config($tableName);
    }

    public function config($tableName) {
        $this->table = DBPREFIX . $tableName;
        $this->db = new mysqli(DBHOST, DBUSER, DBPWD, DBNAME);
        if (mysqli_connect_errno()) {
            printf("连接数据库失败: %s\n", mysqli_connect_error());
            exit();
        }
        //设置编码为utf8
        $this->db->query("set names utf8");
        //默认筛选字段为 *
        $this->opt['field'] = " * ";
        //默认查询条件为空
        $this->opt['where'] = "";
    }

    /**
     * 获得表字段
     * @return \Db
     */
    public function toFields() {
        $sql = " DESC " . $this->table;
        $result = $this->db->query($sql);
        $resultArr = array();
        while ($row = $result->fetch_assoc()) {
            $resultArr[] = $row;
        }
        return $resultArr;
    }

    /**
     * 获得查询字段
     * 
     * <code>
     * 	// 以下参数体等效
     * 	@('id,name');
     * 	@(array('id', 'name'));
     * </code>
     * @param $args mixed
     */
    public function field() {
        $args = func_get_args();
        if (is_array($args[0])) {
            $args = $args[0];
        } else {
            $args = explode(',', $args[0]);
        }

        if (is_array($args)) {
            //字段别名
            foreach ($args as $key => &$val) {
                if (!is_numeric($key)) {
                    $val = "" . $key . ' ' . $val;
                } else {
                    $val = "`" . $val . "`";
                }
            }
        }
        $this->opt['field'] = join(',', $args);
        return $this;
    }

    /**
     * 获得数据表主键
     */
    function getPk() {
        $sql = "DESC " . $this->table;
        $result = array();
        $res = $this->db->query($sql);
        while ($row = $res->fetch_assoc()) {
            if ($row['Key'] == "PRI") {
                return $row['Field'];
            }
        }
        return "id";
    }

    /**
     * group
     */
    public function groupBy($group) {
        $group = is_string($group) ? $group : '';
        $this->opt['group'] = "GROUP BY {$group} ";
        return $this;
    }

    /**
     * where条件限制
     */
    public function where($where = array()) {
        if (!empty($where)) {
            $this->opt($where);
        }
        return $this;
    }

    /**
     * limit 限制
     * <code>
     * //以下参数等效
     * @(10,30)
     * @ (array('offset'=>30,'limit'=>10))
     * </code>
     */
    public function limit($limit, $offset = 0) {
        if (is_array($limit)) {
            isset($limit['offset']) && $offset = $limit['offset'];
            isset($limit['limit']) && $limit = $limit['limit'];
        }
        $this->opt['offset'] = $offset;
        $this->opt['limit'] = $limit;
        return $this;
    }

    /**
     * 排序配置
     * <code>
     * 以下参数等效
     * ('id desc,score asc')
     * ('id desc', 'age asc');
     * array('id desc','score asc');
     * </code>
     */
    public function order() {
        $args = func_get_args();
        if (is_array($args[0])) {
            $args = join(',', $args[0]);
        } elseif (count($args) > 1) {
            $args = join(',', $args);
        } else {
            $args = $args[0];
        }
        $this->opt['order'] = $args;
        return $this;
    }

    /**
     * 查询结果集
     */
    public function select($where = array()) {
        if (!empty($where)) {
            $this->opt($where);
        }
        $this->opt['order'] = $this->opt['order'] ? " ORDER BY " . $this->opt['order'] : '';
        $this->opt['limit'] = $this->opt['limit'] ? " LIMIT " . $this->opt['offset'] . "," . $this->opt['limit'] : '';

        $this->opt['sql'] = "SELECT " . $this->opt['field'] . " FROM " . $this->table . $this->opt['where'] . $this->opt['order'] . $this->opt['limit'];
        $res = $this->query($this->opt['sql']);
        return $res;
    }

    /**
     * 单条查询
     */
    public function find($where = array()) {
        if (!empty($where)) {
            $this->opt($where);
        }
        $this->opt['order'] = $this->opt['order'] ? " ORDER BY " . $this->opt['order'] : '';
        $this->opt['limit'] = " LIMIT 1";

        $this->opt['sql'] = "SELECT " . $this->opt['field'] . " FROM " . $this->table . $this->opt['where'] . $this->opt['order'] . $this->opt['limit'];
        $res = $this->query($this->opt['sql']);
        return $res;
    }

    /**
     * 插入方法
     * <code>
     * @param mixed 
     * @return 返回受影响行数
     * </code>
     */
    public function insert($args) {

        $fieldData = array_keys($args);
        $valueData = array_values($args);

        array_walk($fieldData, array($this, 'addSpecialChar'));
        array_walk($valueData, array($this, 'escapeString'));

        $field = implode(',', $fieldData);
        $value = implode(',', $valueData);

        $this->opt['sql'] = "INSERT INTO `" . $this->table . "` (" . $field . ") VALUES(" . $value . ")";
        $return = $this->sql($this->opt['sql']);
        if ($return) {
            return mysqli_insert_id($this->db);
        } else {
            return;
        }
    }

    /**
     * 
     * @param mixed $data
     * @param string $where
     * <code>
     * $data
     * (array('name'=>'hello','age'=>10),array('id'=>'10'))
     * 
     * $where array('id'=>'123','name'=>'world')
     * $where array('id'=>array('gt'=>'123'));
     * $where 'id=1','name=world'
     * </code>
     * @return boolean
     */
    public function update($data, $where, $logic = 'AND') {
        $this->opt($where, $tmp = $logic);
        $field = array();
        foreach ($data as $key => $val) {
            $field[] = $this->addSpecialChar($key) . '=' . $this->escapeString($val);
        }
        $field = implode(',', $field);
        $this->opt['sql'] = 'UPDATE ' . $this->table . ' SET ' . $field . $this->opt['where'];
        $return = $this->sql($this->opt['sql']);
        return $return;
    }

    public function delete($where, $logic = 'AND') {
        $this->opt($where, $tmp = $logic);
        $this->opt['sql'] = "DELETE FROM " . $this->table . $this->opt['where'];
        $return = $this->sql($this->opt['sql']);
        return $return;
    }

    /**
     * 检查符号关系
     */
    public function checkSign(&$value) {
        foreach ($this->sign as $key => $val) {
            if ($value == $val || $value == $key) {
                $value = $val;
            }
        }
        return $value;
    }

    /**
     * 解析操作条件
     * @param type $condition
     */
    public function opt($where, $logic = " AND ") {
        $condition = '';  //条件
        if (empty($where)) {
            return;
        }
        if (is_string($where)) {
            if (is_numeric($where)) {
                $condition.='`' . $this->getPk() . '` ="' . $where . '"';
            } else {
                $arr = explode("=", $where);
                $condition.='`' . $arr[0] . '` = ' . '"' . $arr[1] . '"';
            }
        } else if (is_array($where)) {
            foreach ($where as $field => $row) {
                if (is_array($row)) {
                    $fieldData = array_keys($row);
                    array_walk($fieldData, array($this, 'checkSign'));
                    $valueData = array_values($row);
                    foreach ($fieldData as $key => $val) {
                        $condition .= ' `' . $field . '` ' . $val . ' " ' . $valueData[$key] . '" ' . $logic;
                    }
                } else {
                    $condition .= ' `' . $field . '` = "' . $row . '" ' . $logic;
                }
            }
            $pos = strrpos($condition, "AND");
            $condition = substr($condition, 0, $pos);
        }

        $where = ' WHERE ' . $condition;
        $this->opt['where'] = $where;
    }

    /**
     * 对字段 两边加反引号，以保证数据库安全性
     * @param array $value
     */
    public function addSpecialChar(&$value) {
        if ('*' == $value || false !== strpos($value, '(') || false !== strpos($value, '.') || false !== strpos($value, '`')) {
            
        } else {
            $value = '`' . trim($value) . '`';
        }
        if (preg_match("/(select|update|insert|delete)/i", $value)) {
            $value = preg_replace('/(select|update|insert|delete)/i', '', $value);
        }
        return $value;
    }

    /**
     * 对字段值两边加'',以保证数据库安全
     * @param array $value 
     * @param int $quotation
     */
    public function escapeString(&$value, $quotation = 1) {
        if ($quotation) {
            $q = '\'';
        } else {
            $q = "'";
        }
        $value = $q . $value . $q;
        return $value;
    }

    /**
     * 执行有结果集的查询
     */
    public function query($sql) {
        $result = $this->db->query($sql) or die($this->halt());
        $res = array();
        while ($row = $result->fetch_assoc()) {
            $res[] = $row;
        }
        return $res;
    }

    /**
     * 执行没有结果集的查询
     */
    public function sql($sql) {
        $this->db->query($sql) or die($this->halt());
        return $this->db->affected_rows;
    }

    /**
     * 获得上一条SQL语句
     */
    public function getLastSql() {
        return $this->opt['sql'];
    }

    /**
     * 操作报错方法
     */
    public function halt() {
        return "<b>mysqlError:" . mysqli_errno($this->db) . "</b><p>" . mysqli_error($this->db) . "</p>";
    }

}

?>
