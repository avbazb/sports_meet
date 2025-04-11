<?php
/**
 * 运动会管理系统辅助函数
 */

/**
 * 数据库查询辅助函数
 */
function query($sql) {
    global $conn;
    return $conn->query($sql);
}

/**
 * 预处理并执行SQL语句
 */
function prepareAndExecute($sql, $types = '', $params = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return false;
    }
    
    if (!empty($types) && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * 安全输出HTML，防止XSS
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 根据日期和设置自动更新所有比赛状态
 */
function updateAllEventStatus() {
    // 获取当前运动会设置
    $settingsQuery = "SELECT * FROM sports_settings WHERE is_active = 1 LIMIT 1";
    $settingsResult = query($settingsQuery);
    
    if (!$settingsResult || $settingsResult->num_rows == 0) {
        return false; // 没有活动的运动会设置
    }
    
    $settings = $settingsResult->fetch_assoc();
    $currentDay = (int)$settings['current_day'];
    $today = date('Y-m-d');
    $startDate = $settings['start_date'];
    
    // 计算今天是运动会的第几天
    $startDateTime = new DateTime($startDate);
    $todayDateTime = new DateTime($today);
    $dayDiff = $startDateTime->diff($todayDateTime)->days;
    
    // 如果设置了当前天数，优先使用设置的天数
    $activeDay = $currentDay;
    
    // 判断比赛状态
    if ($today < $startDate) {
        // 未开始
        $sql = "UPDATE events SET status = '未开始'";
        query($sql);
    } else if ($dayDiff + 1 > $settings['days']) {
        // 已结束
        $sql = "UPDATE events SET status = '已结束'";
        query($sql);
    } else {
        // 根据当前活动天数更新比赛状态
        
        // 将过去几天的比赛设为已结束
        if ($activeDay > 1) {
            $sql = "UPDATE events SET status = '已结束' WHERE event_day < $activeDay";
            query($sql);
        }
        
        // 将当天的比赛设为进行中
        $sql = "UPDATE events SET status = '进行中' WHERE event_day = $activeDay";
        query($sql);
        
        // 将未来几天的比赛设为未开始
        if ($activeDay < $settings['days']) {
            $sql = "UPDATE events SET status = '未开始' WHERE event_day > $activeDay";
            query($sql);
        }
    }
    
    return true;
}

/**
 * 获取班级总分排名
 */
function getClassRankings() {
    $sql = "SELECT 
                p.class_id, 
                c.class_name,
                SUM(r.score) as total_score,
                COUNT(r.result_id) as result_count
            FROM 
                results r
                JOIN participants p ON r.participant_id = p.participant_id
                JOIN classes c ON p.class_id = c.class_id
            GROUP BY 
                p.class_id
            ORDER BY 
                total_score DESC";
    
    $result = query($sql);
    $rankings = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rankings[] = $row;
        }
    }
    
    return $rankings;
}

/**
 * 获取比赛完成情况统计
 */
function getEventCompletionStats() {
    $sql = "SELECT 
                status, 
                COUNT(*) as count 
            FROM 
                events 
            GROUP BY 
                status";
    
    $result = query($sql);
    $stats = [
        '未开始' => 0,
        '进行中' => 0,
        '已结束' => 0
    ];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats[$row['status']] = (int)$row['count'];
        }
    }
    
    return $stats;
}

/**
 * 获取按天统计的比赛数量
 */
function getEventsByDay() {
    $sql = "SELECT 
                event_day, 
                COUNT(*) as count 
            FROM 
                events 
            GROUP BY 
                event_day 
            ORDER BY 
                event_day";
    
    $result = query($sql);
    $dayStats = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dayStats['第' . $row['event_day'] . '天'] = (int)$row['count'];
        }
    }
    
    return $dayStats;
}

/**
 * 获取班级成绩统计
 */
function getClassScoresByEvent() {
    $sql = "SELECT 
                e.event_name,
                c.class_name,
                SUM(r.score) as total_score
            FROM 
                results r
                JOIN participants p ON r.participant_id = p.participant_id
                JOIN events e ON r.event_id = e.event_id
                JOIN classes c ON p.class_id = c.class_id
            GROUP BY 
                e.event_id, c.class_id
            ORDER BY 
                e.event_id, total_score DESC";
    
    $result = query($sql);
    $classScores = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!isset($classScores[$row['event_name']])) {
                $classScores[$row['event_name']] = [];
            }
            $classScores[$row['event_name']][] = [
                'class_name' => $row['class_name'],
                'score' => $row['total_score']
            ];
        }
    }
    
    return $classScores;
}
?> 