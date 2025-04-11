<?php
/**
 * 前台功能函数文件
 */

require_once 'db.php';

/**
 * 获取比赛状态
 * 自动更新比赛状态：比赛时间前20分钟开始检录，比赛开始后变为比赛中
 * @param string $eventTime 比赛时间
 * @param string $currentStatus 当前状态
 * @return string 更新后的状态
 */
function getEventStatus($eventTime, $currentStatus) {
    $now = time();
    $eventTimestamp = strtotime($eventTime);
    
    // 检录时间（比赛时间前20分钟）
    $checkInTime = $eventTimestamp - 1200; // 20分钟 = 1200秒
    
    // 如果比赛已结束，状态已经是公布成绩，则保持不变
    if ($currentStatus == '公布成绩' || $currentStatus == '已结束') {
        return $currentStatus;
    }
    
    if ($now < $checkInTime) {
        // 未到检录时间
        return '未开始';
    } else if ($now >= $checkInTime && $now < $eventTimestamp) {
        // 检录时间
        return '检录中';
    } else {
        // 比赛时间到，开始比赛
        return '比赛中';
    }
}

/**
 * 更新所有比赛状态
 * 用于定时任务或页面加载时调用
 */
function updateAllEventStatus() {
    $sql = "SELECT event_id, event_time, status FROM events";
    $result = query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $newStatus = getEventStatus($row['event_time'], $row['status']);
            
            // 如果状态有变化，则更新
            if ($newStatus != $row['status']) {
                $updateSql = "UPDATE events SET status = '$newStatus' WHERE event_id = {$row['event_id']}";
                query($updateSql);
            }
        }
    }
}

/**
 * 计算班级团体总分
 * 规则：
 * 1. 田径比赛各组别录取前八名。前三名奖励，前八名记分。按9、7、6、5、4、3、2、1记分。
 * 2. 接力项目记分办法：按单项加倍记分。
 * 3. 长跑项目（800米）双倍记分，前八名之后完成比赛者，班级团体总分加1分
 * 4. 报名不足8人（队）采取减一录取（如5人参赛录取前四名记分）
 * 5. 破校记录积分翻倍，设破纪录奖。
 */
function calculateClassTotalScores() {
    // 清空原有积分
    $resetSql = "UPDATE classes SET total_score = 0";
    query($resetSql);
    
    // 获取所有成绩，按项目和班级分组计算
    $resultsSql = "SELECT r.*, e.event_name, p.class_id 
                  FROM results r
                  JOIN events e ON r.event_id = e.event_id
                  JOIN participants p ON r.participant_id = p.participant_id
                  ORDER BY r.event_id, r.ranking";
    
    $resultsQuery = query($resultsSql);
    if (!$resultsQuery) {
        return false;
    }
    
    // 记录各班级得分
    $classScores = [];
    
    // 处理每条成绩记录
    while ($row = $resultsQuery->fetch_assoc()) {
        $classId = $row['class_id'];
        $eventName = $row['event_name'];
        $ranking = (int)$row['ranking'];
        $isRecordBreaking = (bool)$row['is_record_breaking'];
        $points = 0;
        
        // 基础积分计算：9、7、6、5、4、3、2、1
        if ($ranking <= 8) {
            $pointsMap = [9, 7, 6, 5, 4, 3, 2, 1];
            $points = $pointsMap[$ranking - 1];
        }
        
        // 接力项目分数翻倍
        if (strpos($eventName, '接力') !== false || strpos($eventName, '4x') !== false) {
            $points *= 2;
        }
        
        // 长跑项目（800米）双倍计分
        if (strpos($eventName, '800米') !== false) {
            $points *= 2;
            
            // 前八名之后完成比赛者加1分
            if ($ranking > 8) {
                $points = 1;
            }
        }
        
        // 破记录翻倍
        if ($isRecordBreaking) {
            $points *= 2;
        }
        
        // 累加班级得分
        if (!isset($classScores[$classId])) {
            $classScores[$classId] = 0;
        }
        $classScores[$classId] += $points;
    }
    
    // 更新班级总分
    foreach ($classScores as $classId => $score) {
        $updateSql = "UPDATE classes SET total_score = total_score + ? WHERE class_id = ?";
        prepareAndExecute($updateSql, 'di', [$score, $classId]);
    }
    
    return true;
}

/**
 * 创建比赛分组
 * @param string $eventName 比赛名称（不含组别）
 * @param int $groups 组数
 * @param int $participantCount 参赛人数
 * @param string $eventTime 比赛时间
 * @param int $eventDay 比赛日(第几天)
 * @return int|bool 主比赛ID或失败
 */
function createEventGroups($eventName, $groups, $participantCount, $eventTime, $eventDay = 1) {
    // 处理比赛时间格式
    if (strpos($eventTime, ':') !== false && strpos($eventTime, '-') === false) {
        // 只有时间，添加当前日期
        $eventTime = date('Y-m-d') . ' ' . $eventTime;
    }
    
    // 先创建父级比赛（主比赛）
    $parentSql = "INSERT INTO events (event_name, total_groups, participant_count, event_time, event_day) 
                 VALUES (?, ?, ?, ?, ?)";
    $parentStmt = prepareAndExecute($parentSql, 'sissi', [$eventName, $groups, $participantCount, $eventTime, $eventDay]);
    
    if ($parentStmt === false) {
        return false;
    }
    
    $parentId = getInsertId();
    
    // 创建各个分组比赛
    for ($i = 1; $i <= $groups; $i++) {
        $groupEventName = $eventName . " " . $i . "组";
        $groupSql = "INSERT INTO events (parent_event_id, event_name, group_number, total_groups, event_time, event_day) 
                    VALUES (?, ?, ?, ?, ?, ?)";
        $groupStmt = prepareAndExecute($groupSql, 'isiisi', [$parentId, $groupEventName, $i, $groups, $eventTime, $eventDay]);
        
        if ($groupStmt === false) {
            // 如果失败，删除已创建的主比赛
            $deleteSql = "DELETE FROM events WHERE event_id = ?";
            prepareAndExecute($deleteSql, 'i', [$parentId]);
            return false;
        }
    }
    
    return $parentId;
}

/**
 * 检查是否破纪录
 * @param string $eventName 比赛名称
 * @param string $score 成绩
 * @return bool 是否破纪录
 */
function isRecordBreaking($eventName, $score) {
    // 获取比赛基本名称（去除组别信息）
    $baseEventName = preg_replace('/\s+\d+组.*$/', '', $eventName);
    
    // 查询现有记录
    $sql = "SELECT record_score FROM records WHERE event_name = ? ORDER BY record_id DESC LIMIT 1";
    $stmt = prepareAndExecute($sql, 's', [$baseEventName]);
    
    if ($stmt === false) {
        return false;
    }
    
    $result = $stmt->get_result();
    
    // 如果没有记录，则是首次比赛，不算破纪录
    if ($result->num_rows === 0) {
        return false;
    }
    
    $recordRow = $result->fetch_assoc();
    $recordScore = $recordRow['record_score'];
    
    // 根据比赛类型比较成绩
    // 对于时间类（如跑步），较小的成绩更好
    if (strpos($baseEventName, '米') !== false || strpos($baseEventName, '跑') !== false) {
        return compareTimeScores($score, $recordScore) < 0;
    } 
    // 对于距离类（如跳远、投掷），较大的成绩更好
    else if (strpos($baseEventName, '跳') !== false || strpos($baseEventName, '投') !== false) {
        return (float)$score > (float)$recordScore;
    }
    
    // 默认情况，无法判断
    return false;
}

/**
 * 比较时间成绩
 * @param string $score1 成绩1
 * @param string $score2 成绩2
 * @return int 比较结果（-1: score1更好, 0: 相等, 1: score2更好）
 */
function compareTimeScores($score1, $score2) {
    // 将时间转换为秒
    $seconds1 = convertTimeToSeconds($score1);
    $seconds2 = convertTimeToSeconds($score2);
    
    if ($seconds1 < $seconds2) return -1;
    if ($seconds1 > $seconds2) return 1;
    return 0;
}

/**
 * 将时间成绩转换为秒
 * @param string $time 时间成绩 (格式如: "10.5", "1:23.45")
 * @return float 秒数
 */
function convertTimeToSeconds($time) {
    if (strpos($time, ':') !== false) {
        list($minutes, $seconds) = explode(':', $time);
        return (float)$minutes * 60 + (float)$seconds;
    }
    return (float)$time;
}

/**
 * 添加新纪录
 * @param string $eventName 比赛名称
 * @param string $score 成绩
 * @param string $participantName 参赛者姓名
 * @param string $className 班级名称
 * @return bool 是否成功
 */
function addNewRecord($eventName, $score, $participantName, $className) {
    // 获取比赛基本名称（去除组别信息）
    $baseEventName = preg_replace('/\s+\d+组.*$/', '', $eventName);
    
    $sql = "INSERT INTO records (event_name, record_score, participant_name, class_name, record_date) 
            VALUES (?, ?, ?, ?, CURDATE())";
    $stmt = prepareAndExecute($sql, 'ssss', [$baseEventName, $score, $participantName, $className]);
    
    return $stmt !== false;
}

/**
 * 安全输出HTML，防止XSS
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * 获取当前运动会设置
 */
function getSportsSettings() {
    $sql = "SELECT * FROM sports_settings WHERE is_active = 1 LIMIT 1";
    $result = query($sql);
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * 根据日期和设置自动更新所有比赛状态
 */
function updateEventStatus() {
    // 获取当前运动会设置
    $settings = getSportsSettings();
    
    if (!$settings) {
        return false; // 没有活动的运动会设置
    }
    
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
        
        // 当天比赛按照时间更新状态
        // 获取当天所有比赛
        $sql = "SELECT event_id, event_time, status FROM events WHERE event_day = $activeDay";
        $result = query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $newStatus = getEventStatus($row['event_time'], $row['status']);
                
                // 更新状态
                $updateSql = "UPDATE events SET status = '$newStatus' WHERE event_id = {$row['event_id']}";
                query($updateSql);
            }
        }
        
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
 * 根据比赛日期获取赛事
 */
function getEventsByDay($day) {
    $sql = "SELECT * FROM events WHERE event_day = ? AND parent_event_id IS NULL ORDER BY event_time ASC";
    $stmt = prepareAndExecute($sql, 'i', [$day]);
    $events = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    return $events;
}

/**
 * 获取热门比赛（即将开始或正在进行的比赛）
 */
function getHotEvents($limit = 5) {
    $sql = "SELECT * FROM events 
            WHERE parent_event_id IS NULL 
            AND (status = '进行中' OR status = '未开始') 
            ORDER BY 
                CASE 
                    WHEN status = '进行中' THEN 0
                    ELSE 1
                END,
                event_time ASC
            LIMIT ?";
    
    $stmt = prepareAndExecute($sql, 'i', [$limit]);
    $events = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    return $events;
}

/**
 * 创建分页控件
 * @param int $total 总记录数
 * @param int $currentPage 当前页码
 * @param int $perPage 每页记录数
 * @param string $baseUrl 基础URL
 * @return string 分页HTML
 */
function createPagination($total, $currentPage, $perPage, $baseUrl) {
    $totalPages = ceil($total / $perPage);
    
    if ($totalPages <= 1) {
        return '';
    }
    
    $html = '<div class="pagination">';
    
    // 上一页
    if ($currentPage > 1) {
        $html .= '<a href="' . $baseUrl . 'page=' . ($currentPage - 1) . '" class="page-link">上一页</a>';
    } else {
        $html .= '<span class="page-link disabled">上一页</span>';
    }
    
    // 页码
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $startPage + 4);
    
    if ($startPage > 1) {
        $html .= '<a href="' . $baseUrl . 'page=1" class="page-link">1</a>';
        if ($startPage > 2) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<span class="page-link active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $baseUrl . 'page=' . $i . '" class="page-link">' . $i . '</a>';
        }
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<span class="page-ellipsis">...</span>';
        }
        $html .= '<a href="' . $baseUrl . 'page=' . $totalPages . '" class="page-link">' . $totalPages . '</a>';
    }
    
    // 下一页
    if ($currentPage < $totalPages) {
        $html .= '<a href="' . $baseUrl . 'page=' . ($currentPage + 1) . '" class="page-link">下一页</a>';
    } else {
        $html .= '<span class="page-link disabled">下一页</span>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * generatePagination函数 - createPagination的别名
 * 用于保持与管理页面的兼容性
 * @param int $total 总记录数
 * @param int $currentPage 当前页码
 * @param int $perPage 每页记录数
 * @param string $baseUrl 基础URL
 * @return string 分页HTML
 */
function generatePagination($total, $currentPage, $perPage, $baseUrl) {
    return createPagination($total, $currentPage, $perPage, $baseUrl);
}

/**
 * 获取比赛详情
 */
function getEventDetail($eventId) {
    $sql = "SELECT * FROM events WHERE event_id = ?";
    $stmt = prepareAndExecute($sql, 'i', [$eventId]);
    
    if ($stmt) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            return $result->fetch_assoc();
        }
    }
    
    return null;
}

/**
 * 获取比赛参赛者
 */
function getEventParticipants($eventId) {
    $sql = "SELECT p.*, c.class_name, ep.lane_number 
            FROM participants p
            JOIN classes c ON p.class_id = c.class_id
            JOIN event_participants ep ON p.participant_id = ep.participant_id
            WHERE ep.event_id = ?
            ORDER BY ep.lane_number ASC";
    
    $stmt = prepareAndExecute($sql, 'i', [$eventId]);
    $participants = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $participants[] = $row;
        }
    }
    
    return $participants;
}

/**
 * 获取比赛成绩
 */
function getEventResults($eventId) {
    $sql = "SELECT r.*, p.name as participant_name, c.class_name 
            FROM results r
            JOIN participants p ON r.participant_id = p.participant_id
            JOIN classes c ON p.class_id = c.class_id
            WHERE r.event_id = ?
            ORDER BY r.ranking ASC";
    
    $stmt = prepareAndExecute($sql, 'i', [$eventId]);
    $results = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = $row;
        }
    }
    
    return $results;
}

/**
 * 调用智谱AI API生成成绩报告
 * @param int $eventId 比赛ID
 * @param string $eventName 比赛名称
 * @param array $results 比赛成绩数据
 * @return string|bool 生成的报告内容，失败返回false
 */
function generateAIReport($eventId, $eventName, $results) {
    $apiKey = "";
    $url = "https://open.bigmodel.cn/api/paas/v4/chat/completions";
    
    // 准备比赛成绩的描述文本
    $resultsText = "";
    foreach ($results as $index => $result) {
        $resultsText .= "第" . $result['ranking'] . "名: " . $result['participant_name'] . 
                      "(" . $result['class_name'] . "), 成绩: " . $result['score'] . 
                      ($result['is_record_breaking'] ? " (破纪录)" : "") . "\n";
    }
    
    // 构建请求消息
    $promptMessage = "请根据以下信息，以体育记者的口吻生成一段简短的比赛报道（100-150字），突出比赛亮点和选手表现：\n" .
                   "比赛项目：" . $eventName . "\n" .
                   "比赛成绩：\n" . $resultsText;
    
    $messages = [
        ["role" => "user", "content" => $promptMessage]
    ];
    
    // 构建请求体
    $data = [
        "model" => "glm-4-flash",
        "messages" => $messages,
        "temperature" => 0.7,
        "top_p" => 0.8,
        "max_tokens" => 500,
    ];
    
    // 设置HTTP请求头
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ];
    
    // 发送POST请求
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $err = curl_error($ch);
    
    curl_close($ch);
    
    if ($err) {
        error_log("智谱AI API调用失败: " . $err);
        return false;
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['choices'][0]['message']['content'])) {
        $reportContent = $responseData['choices'][0]['message']['content'];
        
        // 保存AI生成的报告
        $sql = "INSERT INTO ai_reports (event_id, report_type, content) VALUES (?, ?, ?)";
        $stmt = prepareAndExecute($sql, 'iss', [$eventId, '成绩报告', $reportContent]);
        
        if ($stmt === false) {
            error_log("保存AI报告失败：" . $reportContent);
        }
        
        return $reportContent;
    }
    
    error_log("智谱AI API返回数据格式错误: " . $response);
    return false;
}

/**
 * 获取最新的AI比赛动态
 * @param int $limit 获取的动态数量
 * @return array 比赛动态数组
 */
function getLatestAIReports($limit = 5) {
    $sql = "SELECT r.*, e.event_name 
            FROM ai_reports r
            JOIN events e ON r.event_id = e.event_id
            WHERE r.report_type = '成绩报告'
            ORDER BY r.created_at DESC
            LIMIT ?";
    
    $stmt = prepareAndExecute($sql, 'i', [$limit]);
    $reports = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $reports[] = $row;
        }
    }
    
    return $reports;
}

/**
 * 获取所有比赛
 * @return array 比赛列表
 */
function getAllEvents() {
    $sql = "SELECT * FROM events ORDER BY event_day ASC, event_time ASC";
    $result = query($sql);
    $events = [];
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }
    
    return $events;
}

/**
 * 获取状态对应的CSS类名
 * @param string $status 状态字符串
 * @return string CSS类名
 */
function getStatusClass($status) {
    switch ($status) {
        case '未开始':
            return 'status-waiting';
        case '检录中':
            return 'status-checkin';
        case '比赛中':
            return 'status-ongoing';
        case '公布成绩':
        case '已结束':
            return 'status-finished';
        default:
            return '';
    }
} 