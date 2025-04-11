<?php
/**
 * 管理员控制面板
 */
require_once 'auth.php';

// 更新所有比赛状态
updateAllEventStatus();

// 获取统计数据
$stats = [
    'events' => 0,
    'participants' => 0,
    'finished_events' => 0,
    'records' => 0
];

// 获取赛事总数
$eventsQuery = "SELECT COUNT(*) as count FROM events WHERE parent_event_id IS NULL";
$eventsResult = query($eventsQuery);
if ($eventsResult && $row = $eventsResult->fetch_assoc()) {
    $stats['events'] = $row['count'];
}

// 获取参赛人员总数
$participantsQuery = "SELECT COUNT(*) as count FROM participants";
$participantsResult = query($participantsQuery);
if ($participantsResult && $row = $participantsResult->fetch_assoc()) {
    $stats['participants'] = $row['count'];
}

// 获取已完成赛事数（状态为"公布成绩"）
$finishedQuery = "SELECT COUNT(*) as count FROM events WHERE status = '公布成绩'";
$finishedResult = query($finishedQuery);
if ($finishedResult && $row = $finishedResult->fetch_assoc()) {
    $stats['finished_events'] = $row['count'];
}

// 获取破纪录数
$recordsQuery = "SELECT COUNT(*) as count FROM records";
$recordsResult = query($recordsQuery);
if ($recordsResult && $row = $recordsResult->fetch_assoc()) {
    $stats['records'] = $row['count'];
}

// 获取最近比赛
$recentEventsQuery = "SELECT * FROM events WHERE parent_event_id IS NULL ORDER BY event_time ASC LIMIT 5";
$recentEvents = query($recentEventsQuery);

// 获取班级团体总分排名
$rankingQuery = "SELECT * FROM classes ORDER BY total_score DESC LIMIT 10";
$ranking = query($rankingQuery);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员控制面板 - <?php echo SITE_TITLE; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            background-color: #f5f5f7;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo svg {
            margin-right: 12px;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 500;
            color: #1d1d1f;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 16px;
            color: #1d1d1f;
        }
        
        .logout-btn {
            background-color: #f5f5f7;
            color: #1d1d1f;
            border: 1px solid #d2d2d7;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .logout-btn:hover {
            background-color: #e5e5ea;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        
        .stat-card h2 {
            font-size: 36px;
            font-weight: 600;
            color: #1d1d1f;
            margin: 0 0 8px 0;
        }
        
        .stat-card p {
            font-size: 16px;
            color: #6e6e73;
            margin: 0;
        }
        
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }
        
        .card {
            background-color: #fff;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
        }
        
        .card h2 {
            font-size: 18px;
            font-weight: 500;
            color: #1d1d1f;
            margin: 0 0 20px 0;
            padding-bottom: 16px;
            border-bottom: 1px solid #d2d2d7;
        }
        
        .event-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .event-table th, .event-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #d2d2d7;
        }
        
        .event-table th {
            font-weight: 500;
            color: #6e6e73;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-waiting {
            background-color: #e5e5ea;
            color: #6e6e73;
        }
        
        .status-checkin {
            background-color: #ffeacc;
            color: #cc8033;
        }
        
        .status-ongoing {
            background-color: #d1e7ff;
            color: #0071e3;
        }
        
        .status-finished {
            background-color: #e3f7ee;
            color: #34c77b;
        }
        
        .rank-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .rank-table th, .rank-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #d2d2d7;
        }
        
        .rank-table th {
            font-weight: 500;
            color: #6e6e73;
        }
        
        .rank {
            font-weight: 600;
            width: 40px;
        }
        
        .rank-1, .rank-2, .rank-3 {
            font-size: 16px;
        }
        
        .rank-1 {
            color: #af9500;
        }
        
        .rank-2 {
            color: #b4b4b8;
        }
        
        .rank-3 {
            color: #a77044;
        }
        
        .nav-menu {
            display: flex;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }
        
        .nav-menu a {
            padding: 16px 24px;
            color: #1d1d1f;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .nav-menu a:hover {
            background-color: #f5f5f7;
        }
        
        .nav-menu a.active {
            background-color: #0071e3;
            color: #fff;
        }
        
        .card-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .all-link {
            font-size: 14px;
            color: #0071e3;
            text-decoration: none;
        }
        
        .all-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="64" height="64" rx="14" fill="#0071E3"/>
                    <path d="M20 32C20 25.373 25.373 20 32 20C38.627 20 44 25.373 44 32C44 38.627 38.627 44 32 44C25.373 44 20 38.627 20 32Z" stroke="white" stroke-width="3"/>
                    <path d="M32 20V44" stroke="white" stroke-width="3"/>
                    <path d="M20 32H44" stroke="white" stroke-width="3"/>
                    <path d="M32 20L44 32L32 44L20 32L32 20Z" stroke="white" stroke-width="3"/>
                </svg>
                <h1>运动会管理系统</h1>
            </div>
            
            <div class="user-info">
                <span>欢迎，<?php echo h($adminUsername); ?></span>
                <a href="logout.php" class="logout-btn">退出登录</a>
            </div>
        </div>
        
        <div class="nav-menu">
            <a href="dashboard.php" class="active">控制面板</a>
            <a href="events.php">赛事管理</a>
            <a href="participants.php">参赛人员</a>
            <a href="results.php">成绩管理</a>
            <a href="records.php">破纪录管理</a>
        </div>
        
        <div class="stats-container">
            <div class="stat-card">
                <h2><?php echo $stats['events']; ?></h2>
                <p>赛事总数</p>
            </div>
            <div class="stat-card">
                <h2><?php echo $stats['participants']; ?></h2>
                <p>参赛人员</p>
            </div>
            <div class="stat-card">
                <h2><?php echo $stats['finished_events']; ?></h2>
                <p>已完成赛事</p>
            </div>
            <div class="stat-card">
                <h2><?php echo $stats['records']; ?></h2>
                <p>破纪录数</p>
            </div>
        </div>
        
        <div class="main-content">
            <div>
                <div class="card">
                    <div class="card-title">
                        <h2>最近比赛</h2>
                        <a href="events.php" class="all-link">查看全部</a>
                    </div>
                    <table class="event-table">
                        <thead>
                            <tr>
                                <th>比赛项目</th>
                                <th>参赛人数</th>
                                <th>比赛时间</th>
                                <th>状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($recentEvents && $recentEvents->num_rows > 0): ?>
                                <?php while ($event = $recentEvents->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo h($event['event_name']); ?></td>
                                        <td><?php echo h($event['participant_count']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($event['event_time'])); ?></td>
                                        <td>
                                            <?php 
                                            $statusClass = '';
                                            switch ($event['status']) {
                                                case '待开赛':
                                                    $statusClass = 'status-waiting';
                                                    break;
                                                case '检录中':
                                                    $statusClass = 'status-checkin';
                                                    break;
                                                case '比赛中':
                                                    $statusClass = 'status-ongoing';
                                                    break;
                                                case '公布成绩':
                                                    $statusClass = 'status-finished';
                                                    break;
                                            }
                                            ?>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo h($event['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">暂无赛事</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div>
                <div class="card">
                    <div class="card-title">
                        <h2>班级团体总分排名</h2>
                        <a href="../index.php?page=ranking" class="all-link">查看全部</a>
                    </div>
                    <table class="rank-table">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>班级</th>
                                <th>总分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ranking && $ranking->num_rows > 0): ?>
                                <?php $rank = 1; ?>
                                <?php while ($class = $ranking->fetch_assoc()): ?>
                                    <tr>
                                        <td class="rank rank-<?php echo $rank; ?>"><?php echo $rank; ?></td>
                                        <td><?php echo h($class['class_name']); ?></td>
                                        <td><?php echo h($class['total_score']); ?></td>
                                    </tr>
                                    <?php $rank++; ?>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;">暂无数据</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/script.js"></script>
</body>
</html> 