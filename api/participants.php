<?php
/**
 * 参赛者相关API接口
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
    // 获取参赛者详情
    apiGetParticipantDetail($_GET['id']);
} else {
    // 搜索参赛者
    apiSearchParticipants();
}

/**
 * 搜索参赛者
 */
function apiSearchParticipants() {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
    
    $sql = "SELECT p.participant_id, p.name, p.gender, c.class_name
           FROM participants p
           JOIN classes c ON p.class_id = c.class_id
           WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // 添加搜索条件
    if (!empty($search)) {
        $sql .= " AND p.name LIKE ?";
        $params[] = "%$search%";
        $types .= 's';
    }
    
    // 添加班级过滤
    if ($classId) {
        $sql .= " AND p.class_id = ?";
        $params[] = $classId;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY p.name ASC LIMIT 100"; // 限制结果数量
    
    $stmt = prepareAndExecute($sql, $types, $params);
    $participants = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $participants[] = [
                'participant_id' => (int)$row['participant_id'],
                'name' => $row['name'],
                'gender' => $row['gender'],
                'class_name' => $row['class_name']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => '获取成功',
            'participants' => $participants
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '查询参赛者失败',
            'participants' => []
        ]);
    }
}

/**
 * 获取参赛者详情
 */
function apiGetParticipantDetail($participantId) {
    $participantId = (int)$participantId;
    
    // 获取参赛者基本信息
    $sql = "SELECT p.*, c.class_name
            FROM participants p
            JOIN classes c ON p.class_id = c.class_id
            WHERE p.participant_id = ?";
    
    $stmt = prepareAndExecute($sql, 'i', [$participantId]);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => '获取参赛者详情失败',
            'participant' => null
        ]);
        return;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => '未找到指定参赛者',
            'participant' => null
        ]);
        return;
    }
    
    $participant = $result->fetch_assoc();
    
    // 获取参赛者参加的比赛
    $eventsSql = "SELECT e.event_id, e.event_name, e.event_day, e.event_time, e.status
                  FROM events e
                  JOIN event_participants ep ON e.event_id = ep.event_id
                  WHERE ep.participant_id = ?
                  ORDER BY e.event_day ASC, e.event_time ASC";
    
    $eventsStmt = prepareAndExecute($eventsSql, 'i', [$participantId]);
    $events = [];
    
    if ($eventsStmt) {
        $eventsResult = $eventsStmt->get_result();
        while ($row = $eventsResult->fetch_assoc()) {
            $events[] = [
                'event_id' => (int)$row['event_id'],
                'event_name' => $row['event_name'],
                'event_day' => (int)$row['event_day'],
                'event_time' => substr($row['event_time'], 0, 5), // 格式化时间为HH:MM
                'status' => $row['status']
            ];
        }
    }
    
    // 获取参赛者的成绩
    $resultsSql = "SELECT r.*, e.event_name
                   FROM results r
                   JOIN events e ON r.event_id = e.event_id
                   WHERE r.participant_id = ?
                   ORDER BY r.ranking ASC";
    
    $resultsStmt = prepareAndExecute($resultsSql, 'i', [$participantId]);
    $results = [];
    
    if ($resultsStmt) {
        $resultsResult = $resultsStmt->get_result();
        while ($row = $resultsResult->fetch_assoc()) {
            $results[] = [
                'result_id' => (int)$row['result_id'],
                'event_id' => (int)$row['event_id'],
                'event_name' => $row['event_name'],
                'score' => $row['score'],
                'ranking' => (int)$row['ranking'],
                'points' => (int)$row['points'],
                'is_record_breaking' => (bool)$row['is_record_breaking']
            ];
        }
    }
    
    // 构建返回数据
    $participantData = [
        'participant_id' => (int)$participant['participant_id'],
        'name' => $participant['name'],
        'gender' => $participant['gender'],
        'class_name' => $participant['class_name']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'participant' => $participantData,
        'events' => $events,
        'results' => $results
    ]);
} 