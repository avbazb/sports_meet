<?php
/**
 * 数据库配置文件
 */

// 数据库连接信息
define('DB_HOST', 'localhost');
define('DB_USER', 'sports_meet');
define('DB_PASS', '');
define('DB_NAME', 'sports_meet');

// 网站基本设置
define('SITE_TITLE', '运动会管理系统');
define('BASE_URL', 'http://localhost/sports_meet'); // 请根据实际情况修改

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 错误报告级别
error_reporting(E_ALL);
ini_set('display_errors', 1); 