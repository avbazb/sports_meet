<?php
/**
 * 后台管理系统头部模板
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?>运动会管理系统</title>
    <!-- 通用样式 -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- 管理后台专用样式 -->
    <link rel="stylesheet" href="../assets/css/admin.css">
    <!-- 引入Chart.js用于统计图表 -->
    <script src="../assets/js/libs/chart.min.js"></script>
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="logo">
                <h2>运动会管理系统</h2>
            </div>
            <nav class="menu">
                <ul>
                    <li><a href="dashboard.php"><i class="icon dashboard-icon"></i>仪表盘</a></li>
                    <li><a href="events.php"><i class="icon events-icon"></i>比赛管理</a></li>
                    <li><a href="participants.php"><i class="icon participants-icon"></i>参赛者管理</a></li>
                    <li><a href="results.php"><i class="icon results-icon"></i>成绩管理</a></li>
                    <li><a href="records.php"><i class="icon records-icon"></i>记录管理</a></li>
                    <li><a href="statistics.php"><i class="icon stats-icon"></i>统计图表</a></li>
                    <li><a href="settings.php"><i class="icon settings-icon"></i>系统设置</a></li>
                </ul>
            </nav>
            <div class="user-info">
                <div class="user-avatar">
                    <img src="../assets/images/avatar.png" alt="管理员头像">
                </div>
                <div class="user-meta">
                    <p class="user-name">管理员</p>
                    <a href="logout.php" class="logout-link">退出登录</a>
                </div>
            </div>
        </aside>
        
        <main class="content">
            <header class="top-header">
                <div class="page-title">
                    <h1><?php echo isset($pageTitle) ? h($pageTitle) : '运动会管理系统'; ?></h1>
                </div>
                <div class="user-actions">
                    <a href="../index.php" class="btn btn-secondary" target="_blank">访问前台</a>
                </div>
            </header>
            
            <?php if (!empty($alertMessage)): ?>
            <div class="alert alert-<?php echo !empty($alertType) ? h($alertType) : 'info'; ?>">
                <?php echo h($alertMessage); ?>
            </div>
            <?php endif; ?> 