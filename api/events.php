<?php
/**
 * 赛事相关API接口
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 设置返回类型为JSON
header('Content-Type: application/json');

// 处理跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 处理不同请求类型
if (isset($_GET['id'])) {
    // 获取赛事详情
    apiGetEventDetail($_GET['id']);
} elseif (isset($_GET['hot'])) {
    // 获取热门赛事
    apiGetHotEvents($_GET['limit'] ?? 5);
} elseif (isset($_GET['day'])) {
    // 获取指定日期的赛事
    apiGetEventsByDay($_GET['day']);
} else {
    // 获取所有赛事列表
    apiGetAllEventsList();
}

/**
 * 获取所有赛事列表
 */
function apiGetAllEventsList() {
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $sql = "SELECT * FROM events WHERE parent_event_id IS NULL";
    $params = [];
    $types = '';
    
    // 添加状态过滤
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY event_day ASC, event_time ASC";
    
    $stmt = prepareAndExecute($sql, $types, $params);
    $events = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'event_id' => (int)$row['event_id'],
                'event_name' => $row['event_name'],
                'event_day' => (int)$row['event_day'],
                'event_time' => substr($row['event_time'], 0, 5), // 格式化时间为HH:MM
                'status' => $row['status']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => '获取成功',
            'events' => $events
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '获取赛事列表失败',
            'events' => []
        ]);
    }
}

/**
 * 获取赛事详情
 */
function apiGetEventDetail($eventId) {
    $eventId = (int)$eventId;
    
    // 使用includes/functions.php中的getEventDetail函数
    $event = getEventDetail($eventId);
    
    if (!$event) {
        echo json_encode([
            'success' => false,
            'message' => '未找到指定赛事',
            'event' => null
        ]);
        return;
    }
    
    // 获取赛事参赛者
    $participantsSql = "SELECT p.participant_id, p.name, c.class_name, ep.lane_number
                         FROM participants p
                         JOIN classes c ON p.class_id = c.class_id
                         JOIN event_participants ep ON p.participant_id = ep.participant_id
                         WHERE ep.event_id = ?
                         ORDER BY ep.lane_number ASC, p.name ASC";
    
    $participantsStmt = prepareAndExecute($participantsSql, 'i', [$eventId]);
    $participants = [];
    
    if ($participantsStmt) {
        $participantsResult = $participantsStmt->get_result();
        while ($row = $participantsResult->fetch_assoc()) {
            $participants[] = [
                'participant_id' => (int)$row['participant_id'],
                'name' => $row['name'],
                'class_name' => $row['class_name'],
                'lane_number' => $row['lane_number'] ? (int)$row['lane_number'] : null
            ];
        }
    }
    
    // 获取赛事成绩
    $resultsSql = "SELECT r.*, p.name as participant_name, c.class_name
                   FROM results r
                   JOIN participants p ON r.participant_id = p.participant_id
                   JOIN classes c ON p.class_id = c.class_id
                   WHERE r.event_id = ?
                   ORDER BY r.ranking ASC";
    
    $resultsStmt = prepareAndExecute($resultsSql, 'i', [$eventId]);
    $results = [];
    
    if ($resultsStmt) {
        $resultsResult = $resultsStmt->get_result();
        while ($row = $resultsResult->fetch_assoc()) {
            $results[] = [
                'result_id' => (int)$row['result_id'],
                'participant_id' => (int)$row['participant_id'],
                'participant_name' => $row['participant_name'],
                'class_name' => $row['class_name'],
                'score' => $row['score'],
                'ranking' => (int)$row['ranking'],
                'is_record_breaking' => (bool)$row['is_record_breaking']
            ];
        }
    }
    
    // 构建返回数据
    $eventData = [
        'event_id' => (int)$event['event_id'],
        'event_name' => $event['event_name'],
        'event_day' => (int)$event['event_day'],
        'event_time' => substr($event['event_time'], 0, 5), // 格式化时间为HH:MM
        'status' => $event['status'],
        'participant_count' => (int)$event['participant_count']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'event' => $eventData,
        'participants' => $participants,
        'results' => $results
    ]);
}

/**
 * 获取热门赛事
 */
function apiGetHotEvents($limit) {
    $limit = (int)$limit;
    
    // 调用includes/functions.php中的函数
    $hotEvents = getHotEvents($limit);
    
    $events = [];
    foreach ($hotEvents as $event) {
        $events[] = [
            'event_id' => (int)$event['event_id'],
            'event_name' => $event['event_name'],
            'event_day' => (int)$event['event_day'],
            'event_time' => substr($event['event_time'], 0, 5), // 格式化时间为HH:MM
            'status' => $event['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'events' => $events
    ]);
}

/**
 * 获取指定日期的赛事
 */
function apiGetEventsByDay($day) {
    $day = (int)$day;
    
    // 调用includes/functions.php中的函数
    $dayEvents = getEventsByDay($day);
    
    $events = [];
    foreach ($dayEvents as $event) {
        $events[] = [
            'event_id' => (int)$event['event_id'],
            'event_name' => $event['event_name'],
            'event_day' => (int)$event['event_day'],
            'event_time' => substr($event['event_time'], 0, 5), // 格式化时间为HH:MM
            'status' => $event['status']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'events' => $events
    ]);
} 