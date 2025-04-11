<?php
/**
 * 用户端首页
 * 显示赛事列表，比赛日程和记录查询
 */
require_once 'includes/db.php';
require_once 'includes/functions.php';

// 更新所有比赛状态
updateEventStatus();

// 获取当前页面
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// 页面标题
$pageTitle = '校园运动会';

// 获取运动会设置
$settings = getSportsSettings();
$currentDay = 1;
$maxDays = 2;

if ($settings) {
    $pageTitle = $settings['sports_name'];
    $currentDay = (int)$settings['current_day'];
    $maxDays = (int)$settings['days'];
}

// 获取天气信息 (海淀区)
$weatherData = null;
$weatherError = null;
if ($page === 'home') {
    $weatherApiUrl = "https://restapi.amap.com/v3/weather/weatherInfo?key=1d115bcf6cc3584ba6bbd56198c4edaa&city=110108&extensions=base";
    
    // 使用CURL代替file_get_contents，更可靠且有错误信息
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $weatherApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $weatherJson = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($weatherJson !== false) {
        $weatherData = json_decode($weatherJson, true);
        // 检查API返回是否成功
        if (!$weatherData || isset($weatherData['status']) && $weatherData['status'] != '1') {
            $weatherError = '天气API返回错误: ' . (isset($weatherData['info']) ? $weatherData['info'] : '未知错误');
        }
    } else {
        $weatherError = '无法连接天气API: ' . $curlError . '(HTTP状态码: ' . $httpCode . ')';
    }
}

// 根据页面设置标题
switch ($page) {
    case 'home':
        $pageTitle = '首页 - ' . $pageTitle;
        break;
    case 'events':
        $day = isset($_GET['day']) ? (int)$_GET['day'] : $currentDay;
        $pageTitle = '第' . $day . '天赛事 - ' . $pageTitle;
        break;
    case 'schedule':
        $day = isset($_GET['day']) ? (int)$_GET['day'] : $currentDay;
        $pageTitle = '第' . $day . '天日程 - ' . $pageTitle;
        break;
    case 'results':
        $pageTitle = '成绩查询 - ' . $pageTitle;
        break;
    case 'records':
        $pageTitle = '赛事纪录 - ' . $pageTitle;
        break;
    case 'ranking':
        $pageTitle = '团体总分 - ' . $pageTitle;
        break;
    case 'event_detail':
        $pageTitle = '赛事详情 - ' . $pageTitle;
        break;
}

// 获取热门比赛（首页显示）
$hotEvents = getHotEvents(5);

// 获取指定日期的比赛（赛事信息页面显示）
$dayEvents = [];
if ($page === 'events' || $page === 'schedule') {
    $day = isset($_GET['day']) ? (int)$_GET['day'] : $currentDay;
    $dayEvents = getEventsByDay($day);
}

// 提取搜索参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchType = isset($_GET['search_type']) ? $_GET['search_type'] : 'event';

// 成绩查询
$searchResults = [];
if (!empty($search)) {
    if ($searchType === 'event') {
        // 按赛事名称搜索成绩
        $searchSql = "SELECT r.*, e.event_name, p.name as participant_name, c.class_name, e.event_time 
                    FROM results r
                    JOIN events e ON r.event_id = e.event_id
                    JOIN participants p ON r.participant_id = p.participant_id
                    JOIN classes c ON p.class_id = c.class_id
                    WHERE e.event_name LIKE ?
                    ORDER BY e.event_time DESC, r.ranking ASC";
        $stmt = prepareAndExecute($searchSql, 's', ['%' . $search . '%']);
    } else {
        // 按运动员姓名搜索成绩
        $searchSql = "SELECT r.*, e.event_name, p.name as participant_name, c.class_name, e.event_time
                    FROM results r
                    JOIN events e ON r.event_id = e.event_id
                    JOIN participants p ON r.participant_id = p.participant_id
                    JOIN classes c ON p.class_id = c.class_id
                    WHERE p.name LIKE ?
                    ORDER BY e.event_time DESC, r.ranking ASC";
        $stmt = prepareAndExecute($searchSql, 's', ['%' . $search . '%']);
    }
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $searchResults[] = $row;
        }
    }
}

// 获取班级排名数据
$classRankings = getClassRankings();

// 获取比赛完成情况统计
$eventCompletionStats = getEventCompletionStats();

// 赛事详情
$eventDetail = null;
$participants = [];
$results = [];
if ($page === 'event_detail' && isset($_GET['id'])) {
    $eventId = (int)$_GET['id'];
    
    // 获取赛事详情
    $eventDetail = getEventDetail($eventId);
    
    // 获取参赛名单
    $participants = getEventParticipants($eventId);
    
    // 获取比赛成绩
    $results = getEventResults($eventId);
}

// 获取历史记录
$records = [];
if ($page === 'records') {
    $recordsSql = "SELECT * FROM records ORDER BY event_name ASC, record_date DESC";
    $recordsResult = query($recordsSql);
    
    if ($recordsResult) {
        while ($row = $recordsResult->fetch_assoc()) {
            $records[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <!-- 引入样式表 -->
    <link rel="stylesheet" href="assets/css/frontend.css">
    <!-- 引入Chart.js用于统计图表 -->
    <script src="assets/js/libs/chart.min.js"></script>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <svg width="40" height="40" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect width="64" height="64" rx="14" fill="#0071E3"/>
                    <path d="M20 32C20 25.373 25.373 20 32 20C38.627 20 44 25.373 44 32C44 38.627 38.627 44 32 44C25.373 44 20 38.627 20 32Z" stroke="white" stroke-width="3"/>
                    <path d="M32 20V44" stroke="white" stroke-width="3"/>
                    <path d="M20 32H44" stroke="white" stroke-width="3"/>
                    <path d="M32 20L44 32L32 44L20 32L32 20Z" stroke="white" stroke-width="3"/>
                </svg>
                <h1><?php echo h($settings ? $settings['sports_name'] : '校园运动会'); ?></h1>
            </div>
        </header>
        
        <nav class="nav-menu">
            <a href="index.php" class="<?php echo $page === 'home' ? 'active' : ''; ?>">首页</a>
            <a href="index.php?page=events" class="<?php echo $page === 'events' ? 'active' : ''; ?>">赛事信息</a>
            <a href="index.php?page=schedule" class="<?php echo $page === 'schedule' ? 'active' : ''; ?>">比赛日程</a>
            <a href="index.php?page=results" class="<?php echo $page === 'results' ? 'active' : ''; ?>">成绩查询</a>
            <a href="index.php?page=records" class="<?php echo $page === 'records' ? 'active' : ''; ?>">历史纪录</a>
            <a href="index.php?page=ranking" class="<?php echo $page === 'ranking' ? 'active' : ''; ?>">团体总分</a>
        </nav>
        
        <?php if ($page === 'home'): ?>
        <!-- 首页 -->
        <?php if ($settings): ?>
        <div class="home-cards">
            <div class="home-card">
                <h3 class="home-card-title">运动会日期</h3>
                <p class="home-card-value"><?php echo date('Y年m月d日', strtotime($settings['start_date'])); ?></p>
                <p style="color: #86868b;">为期<?php echo $settings['days']; ?>天</p>
            </div>
            <div class="home-card">
                <h3 class="home-card-title">当前状态</h3>
                <p class="home-card-value">第<?php echo $settings['current_day']; ?>天</p>
                <?php
                // 计算当天日期
                $startDate = new DateTime($settings['start_date']);
                $currentDate = clone $startDate;
                $currentDate->modify('+' . ($settings['current_day'] - 1) . ' days');
                ?>
                <p style="color: #86868b;"><?php echo $currentDate->format('Y年m月d日'); ?></p>
            </div>
            <div class="home-card">
                <h3 class="home-card-title">完成进度</h3>
                <?php
                $total = $eventCompletionStats['未开始'] + $eventCompletionStats['进行中'] + $eventCompletionStats['已结束'];
                $percentage = $total > 0 ? round(($eventCompletionStats['已结束'] / $total) * 100) : 0;
                ?>
                <p class="home-card-value"><?php echo $percentage; ?>%</p>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%;"></div>
                </div>
            </div>
            
            <!-- 天气信息卡片 -->
            <div class="home-card">
                <h3 class="home-card-title">当前天气</h3>
                <?php if ($weatherData && isset($weatherData['lives'][0])): ?>
                    <?php $weather = $weatherData['lives'][0]; ?>
                    <div style="display: flex; align-items: center; margin-bottom: 10px;">
                        <?php 
                        // 根据天气描述显示图标
                        $weatherIcon = '☀️'; // 默认晴天
                        
                        if (strpos($weather['weather'], '雨') !== false) {
                            $weatherIcon = '🌧️';
                        } elseif (strpos($weather['weather'], '雪') !== false) {
                            $weatherIcon = '❄️';
                        } elseif (strpos($weather['weather'], '阴') !== false) {
                            $weatherIcon = '☁️';
                        } elseif (strpos($weather['weather'], '云') !== false || strpos($weather['weather'], '多云') !== false) {
                            $weatherIcon = '⛅';
                        } elseif (strpos($weather['weather'], '雾') !== false || strpos($weather['weather'], '霾') !== false) {
                            $weatherIcon = '🌫️';
                        } elseif (strpos($weather['weather'], '风') !== false || strpos($weather['weather'], '飓风') !== false) {
                            $weatherIcon = '🌪️';
                        }
                        ?>
                        <span style="font-size: 36px; margin-right: 10px;"><?php echo $weatherIcon; ?></span>
                        <div>
                            <p class="home-card-value" style="margin: 0;"><?php echo $weather['temperature']; ?>°C</p>
                            <p style="color: #86868b; margin: 0;"><?php echo $weather['weather']; ?></p>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <div>
                            <p style="margin: 0; color: #86868b;">风向：<?php echo $weather['winddirection']; ?></p>
                            <p style="margin: 0; color: #86868b;">风力：<?php echo $weather['windpower']; ?>级</p>
                        </div>
                        <div>
                            <p style="margin: 0; color: #86868b;">湿度：<?php echo $weather['humidity']; ?>%</p>
                            <p style="margin: 0; color: #86868b;">发布时间：<?php echo date('H:i', strtotime($weather['reporttime'])); ?></p>
                        </div>
                    </div>
                <?php elseif ($weatherError): ?>
                    <p class="home-card-value">获取天气信息失败</p>
                    <p style="color: #ff3b30; font-size: 12px; margin-top: 5px;"><?php echo h($weatherError); ?></p>
                    <p style="color: #86868b; font-size: 12px; margin-top: 5px;">API响应: <pre style="overflow: auto; max-height: 100px; font-size: 10px;"><?php echo h(print_r($weatherData, true)); ?></pre></p>
                <?php else: ?>
                    <p class="home-card-value">数据加载中...</p>
                    <p style="color: #86868b; font-size: 12px; margin-top: 5px;">请刷新页面重试</p>
                <?php endif; ?>
            </div>
            
            <!-- 比赛动态卡片 -->
            <div class="home-card">
                <h3 class="home-card-title">比赛动态</h3>
                <?php 
                // 获取最新的比赛动态
                $latestReports = getLatestAIReports(3);
                if (!empty($latestReports)): 
                ?>
                    <div style="max-height: 200px; overflow-y: auto;">
                        <?php foreach ($latestReports as $report): ?>
                        <div style="margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #f2f2f2;">
                            <p style="font-weight: 600; margin-bottom: 5px;"><?php echo h($report['event_name']); ?></p>
                            <p style="font-size: 14px; color: #1d1d1f;"><?php echo h($report['content']); ?></p>
                            <p style="font-size: 12px; color: #86868b; text-align: right; margin-top: 5px;">
                                <?php echo date('m-d H:i', strtotime($report['created_at'])); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #86868b;">暂无比赛动态</p>
                <?php endif; ?>
                <p style="color: #ff3b30; font-size: 12px; margin-top: 10px; font-style: italic;">由AI生成，结果可能不准确</p>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2 class="card-title">比赛完成情况</h2>
            <div class="chart-container">
                <canvas id="eventStatusChart"></canvas>
            </div>
            
            <script>
            // 赛事状态图表
            const ctx = document.getElementById('eventStatusChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['未开始', '进行中', '已结束'],
                    datasets: [{
                        data: [
                            <?php echo $eventCompletionStats['未开始']; ?>, 
                            <?php echo $eventCompletionStats['进行中']; ?>, 
                            <?php echo $eventCompletionStats['已结束']; ?>
                        ],
                        backgroundColor: [
                            'rgba(0, 113, 227, 0.7)',
                            'rgba(255, 149, 0, 0.7)',
                            'rgba(52, 199, 89, 0.7)'
                        ],
                        borderColor: [
                            'rgba(0, 113, 227, 1)',
                            'rgba(255, 149, 0, 1)',
                            'rgba(52, 199, 89, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        title: {
                            display: true,
                            text: '比赛完成情况'
                        }
                    }
                }
            });
            </script>
        </div>
        
        <div class="card">
            <h2 class="card-title">全部比赛</h2>
            <div class="scrollable-table-container" id="hotEventsContainer">
                <table class="event-list">
                    <thead>
                        <tr>
                            <th>比赛项目</th>
                            <th>比赛日</th>
                            <th>时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // 获取所有比赛而不仅仅是热门比赛
                        $allEvents = getAllEvents();
                        
                        if (empty($allEvents)): 
                        ?>
                            <tr>
                                <td colspan="5">暂无比赛信息</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $currentTimeIndex = -1; 
                            $now = new DateTime();
                            
                            foreach ($allEvents as $index => $event): 
                                // 检查哪个比赛时间最接近当前时间
                                $eventTime = new DateTime($event['event_time']);
                                if ($currentTimeIndex == -1 && $eventTime >= $now) {
                                    $currentTimeIndex = $index;
                                }
                                
                                // 为接近当前时间的比赛添加标记
                                $highlightClass = '';
                                if ($index == $currentTimeIndex) {
                                    $highlightClass = 'current-event';
                                }
                            ?>
                                <tr class="<?php echo $highlightClass; ?>" id="event-<?php echo $event['event_id']; ?>">
                                    <td><?php echo h($event['event_name']); ?></td>
                                    <td>第<?php echo $event['event_day']; ?>天</td>
                                    <td><?php echo date('H:i', strtotime($event['event_time'])); ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch ($event['status']) {
                                            case '未开始':
                                                $statusClass = 'status-waiting';
                                                break;
                                            case '检录中':
                                                $statusClass = 'status-checkin';
                                                break;
                                            case '比赛中':
                                                $statusClass = 'status-ongoing';
                                                break;
                                            case '公布成绩':
                                            case '已结束':
                                                $statusClass = 'status-finished';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo h($event['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="index.php?page=event_detail&id=<?php echo $event['event_id']; ?>">查看详情</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($currentTimeIndex >= 0): ?>
            <script>
                // 页面加载完成后滚动到当前时间附近的比赛
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        const container = document.getElementById('hotEventsContainer');
                        const currentEvent = document.querySelector('.current-event');
                        if (container && currentEvent) {
                            container.scrollTop = currentEvent.offsetTop - container.offsetTop - (container.clientHeight / 2);
                        }
                    }, 500);
                });
            </script>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2 class="card-title">班级得分情况</h2>
            <div class="chart-container">
                <canvas id="classScoreChart"></canvas>
            </div>
            
            <script>
            // 班级得分图表
            const classCtx = document.getElementById('classScoreChart').getContext('2d');
            new Chart(classCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($classRankings, 'class_name')); ?>,
                    datasets: [{
                        label: '总分',
                        data: <?php echo json_encode(array_column($classRankings, 'total_score')); ?>,
                        backgroundColor: 'rgba(0, 113, 227, 0.7)',
                        borderColor: 'rgba(0, 113, 227, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: '班级得分排名'
                        }
                    }
                }
            });
            </script>
        </div>
        
        <?php elseif ($page === 'events'): ?>
        <!-- 比赛信息页面 -->
        <div class="card">
            <h2 class="card-title">赛事信息</h2>
            
            <div class="tab-nav">
                <?php for ($i = 1; $i <= $maxDays; $i++): ?>
                    <a href="index.php?page=events&day=<?php echo $i; ?>" class="tab-link <?php echo (!isset($_GET['day']) && $i == $currentDay) || (isset($_GET['day']) && $_GET['day'] == $i) ? 'active' : ''; ?>">
                        第<?php echo $i; ?>天
                    </a>
                <?php endfor; ?>
            </div>
            
            <div class="scrollable-table-container" id="eventsContainer">
                <table class="event-list">
                    <thead>
                        <tr>
                            <th>比赛项目</th>
                            <th>参赛人数</th>
                            <th>组数</th>
                            <th>时间</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (empty($dayEvents)): 
                        ?>
                            <tr>
                                <td colspan="6">暂无比赛信息</td>
                            </tr>
                        <?php 
                        else:
                            $currentEventIndex = -1;
                            $now = new DateTime();
                            
                            foreach ($dayEvents as $index => $event):
                                // 判断哪个比赛时间最接近当前时间
                                $eventTime = new DateTime($event['event_time']);
                                if ($currentEventIndex == -1 && $eventTime >= $now) {
                                    $currentEventIndex = $index;
                                }
                                
                                // 为接近当前时间的比赛添加高亮标记
                                $highlightClass = '';
                                if ($index == $currentEventIndex) {
                                    $highlightClass = 'current-event';
                                }
                        ?>
                                <tr class="<?php echo $highlightClass; ?>" id="event-info-<?php echo $event['event_id']; ?>">
                                    <td><?php echo h($event['event_name']); ?></td>
                                    <td><?php echo h($event['participant_count']); ?></td>
                                    <td><?php echo h($event['total_groups']); ?></td>
                                    <td><?php echo date('H:i', strtotime($event['event_time'])); ?></td>
                                    <td>
                                        <?php 
                                        $statusClass = '';
                                        switch ($event['status']) {
                                            case '未开始':
                                                $statusClass = 'status-waiting';
                                                break;
                                            case '检录中':
                                                $statusClass = 'status-checkin';
                                                break;
                                            case '比赛中':
                                                $statusClass = 'status-ongoing';
                                                break;
                                            case '公布成绩':
                                            case '已结束':
                                                $statusClass = 'status-finished';
                                                break;
                                        }
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo h($event['status']); ?></span>
                                    </td>
                                    <td>
                                        <a href="index.php?page=event_detail&id=<?php echo $event['event_id']; ?>">查看详情</a>
                                    </td>
                                </tr>
                        <?php 
                            endforeach; 
                        endif; 
                        ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($dayEvents) && $currentEventIndex >= 0): ?>
            <script>
                // 页面加载完成后滚动到当前时间附近的比赛
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        const container = document.getElementById('eventsContainer');
                        const currentEvent = document.querySelector('.current-event');
                        if (container && currentEvent) {
                            container.scrollTop = currentEvent.offsetTop - container.offsetTop - (container.clientHeight / 2);
                        }
                    }, 500);
                });
            </script>
            <?php endif; ?>
        </div>
        
        <?php elseif ($page === 'schedule'): ?>
        <!-- 比赛日程页面 -->
        <div class="card">
            <h2 class="card-title">比赛日程表</h2>
            
            <div class="tab-nav">
                <?php for ($i = 1; $i <= $maxDays; $i++): ?>
                    <a href="index.php?page=schedule&day=<?php echo $i; ?>" class="tab-link <?php echo (!isset($_GET['day']) && $i == $currentDay) || (isset($_GET['day']) && $_GET['day'] == $i) ? 'active' : ''; ?>">
                        第<?php echo $i; ?>天
                    </a>
                <?php endfor; ?>
            </div>
            
            <?php if (empty($dayEvents)): ?>
                <p>暂无日程安排</p>
            <?php else: ?>
                <?php
                // 按照时间点分组显示
                $timeGroups = [];
                $currentTimeBlock = '';
                $now = new DateTime();
                
                foreach ($dayEvents as $event) {
                    $timeKey = date('H:i', strtotime($event['event_time']));
                    $eventTime = new DateTime($event['event_time']);
                    
                    // 找出最接近当前时间的时间块
                    if (empty($currentTimeBlock) && $eventTime >= $now) {
                        $currentTimeBlock = $timeKey;
                    }
                    
                    if (!isset($timeGroups[$timeKey])) {
                        $timeGroups[$timeKey] = [];
                    }
                    $timeGroups[$timeKey][] = $event;
                }
                ksort($timeGroups);
                ?>
                
                <div class="schedule-grid" id="scheduleContainer" style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($timeGroups as $time => $events): ?>
                        <div class="time-block <?php echo ($time === $currentTimeBlock) ? 'current-time-block' : ''; ?>" id="time-block-<?php echo str_replace(':', '-', $time); ?>">
                            <div class="time-block-header">
                                <?php echo $time; ?>
                            </div>
                            <div class="time-block-events">
                                <?php foreach ($events as $event): ?>
                                    <div class="time-block-event">
                                        <div class="time-block-event-name">
                                            <a href="index.php?page=event_detail&id=<?php echo $event['event_id']; ?>"><?php echo h($event['event_name']); ?></a>
                                        </div>
                                        <div class="time-block-event-info">
                                            参赛人数: <?php echo h($event['participant_count']); ?> | 
                                            组数: <?php echo h($event['total_groups']); ?> | 
                                            <span class="status-badge <?php echo getStatusClass($event['status']); ?>"><?php echo h($event['status']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (!empty($currentTimeBlock)): ?>
                <script>
                    // 页面加载完成后滚动到当前时间附近的比赛
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(function() {
                            const container = document.getElementById('scheduleContainer');
                            const currentBlock = document.getElementById('time-block-<?php echo str_replace(':', '-', $currentTimeBlock); ?>');
                            if (container && currentBlock) {
                                container.scrollTop = currentBlock.offsetTop - container.offsetTop - 50;
                            }
                        }, 500);
                    });
                </script>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php elseif ($page === 'results'): ?>
        <!-- 成绩查询页面 -->
        <div class="card">
            <h2 class="card-title">成绩查询</h2>
            
            <div class="search-form">
                <form action="index.php" method="get" class="search-input">
                    <input type="hidden" name="page" value="results">
                    <select name="search_type">
                        <option value="event" <?php echo $searchType === 'event' ? 'selected' : ''; ?>>赛事名称</option>
                        <option value="participant" <?php echo $searchType === 'participant' ? 'selected' : ''; ?>>运动员姓名</option>
                    </select>
                    <input type="text" name="search" placeholder="输入搜索关键词..." value="<?php echo h($search); ?>">
                    <button type="submit" class="btn">搜索</button>
                </form>
            </div>
            
            <?php if (!empty($search)): ?>
                <h3>搜索结果：<?php echo h($search); ?></h3>
                
                <?php if (empty($searchResults)): ?>
                    <p>未找到相关成绩记录</p>
                <?php else: ?>
                    <table class="event-list">
                        <thead>
                            <tr>
                                <th>比赛项目</th>
                                <th>运动员</th>
                                <th>班级</th>
                                <th>成绩</th>
                                <th>排名</th>
                                <th>得分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($searchResults as $result): ?>
                                <tr>
                                    <td><?php echo h($result['event_name']); ?></td>
                                    <td><?php echo h($result['participant_name']); ?></td>
                                    <td><?php echo h($result['class_name']); ?></td>
                                    <td><?php echo h($result['score']); ?></td>
                                    <td><?php echo h($result['ranking']); ?></td>
                                    <td><span class="score"><?php echo h($result['points']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php else: ?>
                <p>请输入赛事名称或运动员姓名进行搜索</p>
            <?php endif; ?>
        </div>
        
        <?php elseif ($page === 'records'): ?>
        <!-- 历史纪录页面 -->
        <div class="card">
            <h2 class="card-title">历史最佳纪录</h2>
            
            <?php if (empty($records)): ?>
                <p>暂无记录数据</p>
            <?php else: ?>
                <?php
                // 按比赛项目分组
                $recordsByEvent = [];
                foreach ($records as $record) {
                    $eventName = $record['event_name'];
                    if (!isset($recordsByEvent[$eventName])) {
                        $recordsByEvent[$eventName] = [];
                    }
                    $recordsByEvent[$eventName][] = $record;
                }
                ?>
                
                <div class="records-grid">
                    <?php foreach ($recordsByEvent as $eventName => $eventRecords): ?>
                        <?php $bestRecord = $eventRecords[0]; // 取第一个记录，应是最新的 ?>
                        <div class="record-card">
                            <div class="record-card-title"><?php echo h($eventName); ?></div>
                            <div class="record-card-score"><?php echo h($bestRecord['record_score']); ?></div>
                            <div class="record-card-info">
                                <?php if (!empty($bestRecord['participant_name'])): ?>
                                    <div>记录保持者：<?php echo h($bestRecord['participant_name']); ?></div>
                                <?php endif; ?>
                                <?php if (!empty($bestRecord['class_name'])): ?>
                                    <div>班级：<?php echo h($bestRecord['class_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($bestRecord['record_date'])): ?>
                                <div class="record-card-date">创造日期：<?php echo date('Y-m-d', strtotime($bestRecord['record_date'])); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php elseif ($page === 'ranking'): ?>
        <!-- 团体总分页面 -->
        <div class="card">
            <h2 class="card-title">团体总分排名</h2>
            
            <table class="ranking-list">
                <tbody>
                    <?php if (empty($classRankings)): ?>
                        <tr>
                            <td>暂无排名数据</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classRankings as $index => $ranking): ?>
                            <tr>
                                <td>
                                    <span class="rank-number <?php echo $index < 3 ? 'rank-' . ($index + 1) : ''; ?>">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <?php echo h($ranking['class_name']); ?>
                                </td>
                                <td>参赛项目: <?php echo h($ranking['result_count']); ?></td>
                                <td><span class="score"><?php echo h($ranking['total_score']); ?></span> 分</td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="chart-container" style="margin-top: 30px;">
                <canvas id="rankingChart"></canvas>
            </div>
            
            <script>
            // 团体总分图表
            const rankingCtx = document.getElementById('rankingChart').getContext('2d');
            new Chart(rankingCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($classRankings, 'class_name')); ?>,
                    datasets: [{
                        label: '总分',
                        data: <?php echo json_encode(array_column($classRankings, 'total_score')); ?>,
                        backgroundColor: 'rgba(0, 113, 227, 0.7)',
                        borderColor: 'rgba(0, 113, 227, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: '班级总分排名'
                        }
                    }
                }
            });
            </script>
        </div>
        
        <?php elseif ($page === 'event_detail' && $eventDetail): ?>
        <!-- 赛事详情页面 -->
        <div class="card">
            <h2 class="card-title"><?php echo h($eventDetail['event_name']); ?></h2>
            
            <div class="event-detail">
                <div class="event-info">
                    <div class="event-info-item">
                        <div class="event-info-label">比赛日期</div>
                        <div class="event-info-value">第<?php echo $eventDetail['event_day']; ?>天</div>
                    </div>
                    <div class="event-info-item">
                        <div class="event-info-label">比赛时间</div>
                        <div class="event-info-value"><?php echo date('H:i', strtotime($eventDetail['event_time'])); ?></div>
                    </div>
                    <div class="event-info-item">
                        <div class="event-info-label">比赛状态</div>
                        <div class="event-info-value">
                            <?php 
                            $statusClass = '';
                            switch ($eventDetail['status']) {
                                case '未开始':
                                    $statusClass = 'status-waiting';
                                    break;
                                case '检录中':
                                    $statusClass = 'status-checkin';
                                    break;
                                case '比赛中':
                                    $statusClass = 'status-ongoing';
                                    break;
                                case '公布成绩':
                                case '已结束':
                                    $statusClass = 'status-finished';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo h($eventDetail['status']); ?></span>
                        </div>
                    </div>
                    <div class="event-info-item">
                        <div class="event-info-label">参赛人数</div>
                        <div class="event-info-value"><?php echo count($participants); ?> 人</div>
                    </div>
                </div>
                
                <h3>参赛名单</h3>
                <?php if (empty($participants)): ?>
                    <p>暂无参赛信息</p>
                <?php else: ?>
                    <table class="participant-list">
                        <thead>
                            <tr>
                                <th>道次</th>
                                <th>参赛者</th>
                                <th>班级</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants as $participant): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($participant['lane_number'])): ?>
                                            <span class="lane-number"><?php echo h($participant['lane_number']); ?></span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($participant['name']); ?></td>
                                    <td><?php echo h($participant['class_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (!empty($results)): ?>
                    <h3>比赛成绩</h3>
                    <table class="result-list">
                        <thead>
                            <tr>
                                <th>排名</th>
                                <th>参赛者</th>
                                <th>班级</th>
                                <th>成绩</th>
                                <th>得分</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $result): ?>
                                <tr>
                                    <td>
                                        <span class="rank-number <?php echo $result['ranking'] <= 3 ? 'rank-' . $result['ranking'] : ''; ?>">
                                            <?php echo h($result['ranking']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo h($result['participant_name']); ?></td>
                                    <td><?php echo h($result['class_name']); ?></td>
                                    <td><?php echo h($result['score']); ?></td>
                                    <td><span class="score"><?php echo h($result['points']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
    // 辅助函数，用于状态显示
    function getStatusClass(status) {
        switch (status) {
            case '未开始': return 'status-waiting';
            case '检录中': return 'status-checkin';
            case '比赛中': return 'status-ongoing';
            case '公布成绩': 
            case '已结束': return 'status-finished';
            default: return '';
        }
    }
    </script>
</body>
</html> 