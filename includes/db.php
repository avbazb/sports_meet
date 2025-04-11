<?php
/**
 * 数据库连接文件
 */

require_once 'config.php';

/**
 * 获取数据库连接
 * @return mysqli 数据库连接对象
 */
function getDbConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        // 检查连接
        if ($conn->connect_error) {
            die('数据库连接失败: ' . $conn->connect_error);
        }
        
        // 设置字符集
        $conn->set_charset('utf8mb4');
    }
    
    return $conn;
}

/**
 * 执行SQL查询
 * @param string $sql SQL语句
 * @return mysqli_result|bool 查询结果
 */
function query($sql) {
    $conn = getDbConnection();
    $result = $conn->query($sql);
    
    if ($result === false) {
        error_log('SQL查询错误: ' . $conn->error . ' SQL: ' . $sql);
    }
    
    return $result;
}

/**
 * 执行预处理语句
 * @param string $sql 预处理SQL
 * @param string $types 参数类型 ('s'字符串, 'i'整数, 'd'浮点数, 'b'二进制)
 * @param array $params 参数数组
 * @return mysqli_stmt|bool 预处理语句对象
 */
function prepareAndExecute($sql, $types, $params) {
    $conn = getDbConnection();
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        error_log('预处理语句错误: ' . $conn->error . ' SQL: ' . $sql);
        return false;
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    
    if ($stmt->error) {
        error_log('执行预处理语句错误: ' . $stmt->error . ' SQL: ' . $sql);
        return false;
    }
    
    return $stmt;
}

/**
 * 获取插入ID
 * @return int 最后插入的ID
 */
function getInsertId() {
    $conn = getDbConnection();
    return $conn->insert_id;
}

/**
 * 获取受影响行数
 * @return int 受影响的行数
 */
function getAffectedRows() {
    $conn = getDbConnection();
    return $conn->affected_rows;
}

/**
 * 转义字符串
 * @param string $str 需要转义的字符串
 * @return string 转义后的字符串
 */
function escapeString($str) {
    $conn = getDbConnection();
    return $conn->real_escape_string($str);
}

/**
 * 关闭数据库连接
 */
function closeDbConnection() {
    $conn = getDbConnection();
    $conn->close();
} 