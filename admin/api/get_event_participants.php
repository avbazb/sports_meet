<?php
/**
 * API: 获取比赛参赛者列表
 */
require_once '../../includes/db.php';
require_once '../../includes/functions.php';

// 设置返回类型为JSON
header('Content-Type: application/json');

// 检查是否有比赛ID
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    echo json_encode([
        'success' => false,
        'message' => '缺少比赛ID参数',
        'participants' => []
    ]);
    exit;
}

$eventId = (int)$_GET['event_id'];

// 查询比赛是否存在
$eventSql = "SELECT * FROM events WHERE event_id = ?";
$eventStmt = prepareAndExecute($eventSql, 'i', [$eventId]);

if (!$eventStmt) {
    echo json_encode([
        'success' => false,
        'message' => '查询比赛信息失败',
        'participants' => []
    ]);
    exit;
}

$eventResult = $eventStmt->get_result();

if ($eventResult->num_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => '未找到指定比赛',
        'participants' => []
    ]);
    exit;
}

// 获取比赛参赛者
$sql = "SELECT p.participant_id as id, p.name, c.class_name, ep.lane_number
        FROM participants p
        JOIN classes c ON p.class_id = c.class_id
        LEFT JOIN event_participants ep ON p.participant_id = ep.participant_id AND ep.event_id = ?
        WHERE ep.event_id = ?
        ORDER BY p.name ASC";

$stmt = prepareAndExecute($sql, 'ii', [$eventId, $eventId]);

$participants = [];

if ($stmt) {
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $participants[] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'class_name' => $row['class_name'],
            'lane_number' => $row['lane_number'] ? (int)$row['lane_number'] : null
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '',
        'participants' => $participants
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '查询参赛者失败',
        'participants' => []
    ]);
}
?> 