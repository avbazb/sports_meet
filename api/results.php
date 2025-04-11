<?php
/**
 * 成绩相关API接口
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 设置返回类型为JSON
header('Content-Type: application/json');

// 处理跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 查询成绩
apiSearchResults();

/**
 * 查询成绩
 */
function apiSearchResults() {
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;
    $participantId = isset($_GET['participant_id']) ? (int)$_GET['participant_id'] : null;
    $classId = isset($_GET['class_id']) ? (int)$_GET['class_id'] : null;
    
    $sql = "SELECT r.result_id, r.score, r.ranking, r.is_record_breaking,
                  e.event_id, e.event_name, e.event_day,
                  p.participant_id, p.name as participant_name,
                  c.class_id, c.class_name
           FROM results r
           JOIN events e ON r.event_id = e.event_id
           JOIN participants p ON r.participant_id = p.participant_id
           JOIN classes c ON p.class_id = c.class_id
           WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // 添加过滤条件
    if ($eventId) {
        $sql .= " AND r.event_id = ?";
        $params[] = $eventId;
        $types .= 'i';
    }
    
    if ($participantId) {
        $sql .= " AND r.participant_id = ?";
        $params[] = $participantId;
        $types .= 'i';
    }
    
    if ($classId) {
        $sql .= " AND c.class_id = ?";
        $params[] = $classId;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY e.event_day ASC, e.event_time ASC, r.ranking ASC LIMIT 200"; // 限制结果数量
    
    $stmt = prepareAndExecute($sql, $types, $params);
    $results = [];
    
    if ($stmt) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $results[] = [
                'result_id' => (int)$row['result_id'],
                'event_id' => (int)$row['event_id'],
                'event_name' => $row['event_name'],
                'participant_id' => (int)$row['participant_id'],
                'participant_name' => $row['participant_name'],
                'class_id' => (int)$row['class_id'],
                'class_name' => $row['class_name'],
                'score' => $row['score'],
                'ranking' => (int)$row['ranking'],
                'is_record_breaking' => (bool)$row['is_record_breaking']
            ];
        }
        
        echo json_encode([
            'success' => true,
            'message' => '获取成功',
            'results' => $results
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '查询成绩失败',
            'results' => []
        ]);
    }
} 