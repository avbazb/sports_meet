<?php
/**
 * 管理员认证检查
 * 包含在所有需要管理员权限的页面顶部
 */
session_start();

// 检查管理员是否已登录
if (!isset($_SESSION['admin_id'])) {
    // 未登录则跳转到登录页
    header('Location: index.php');
    exit;
}

require_once '../includes/db.php';
require_once '../includes/functions.php';

// 获取当前管理员信息
$adminId = $_SESSION['admin_id'];
$adminUsername = $_SESSION['admin_username']; 