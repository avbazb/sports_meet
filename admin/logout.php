<?php
/**
 * 管理员登出
 */
session_start();

// 清除所有会话变量
$_SESSION = array();

// 销毁会话
session_destroy();

// 跳转到登录页
header('Location: index.php');
exit; 