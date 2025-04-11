<?php
/**
 * 班级团队相关API接口
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
if (isset($_GET['class_id'])) {
    // 获取班级详情
    apiGetClassDetail($_GET['class_id']);
} else {
    // 获取班级排名
    apiGetClassRankings();
}

/**
 * 获取班级总分排名
 */
function apiGetClassRankings() {
    // 调用includes/functions.php中的函数
    $rankings = getClassRankings();
    
    $formattedRankings = [];
    foreach ($rankings as $index => $ranking) {
        $formattedRankings[] = [
            'class_id' => (int)$ranking['class_id'],
            'class_name' => $ranking['class_name'],
            'total_score' => (float)$ranking['total_score'],
            'result_count' => (int)$ranking['result_count'],
            'rank' => $index + 1
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'rankings' => $formattedRankings
    ]);
}

/**
 * 获取班级详情
 */
function apiGetClassDetail($classId) {
    $classId = (int)$classId;
    
    // 获取班级基本信息
    $sql = "SELECT * FROM classes WHERE class_id = ?";
    $stmt = prepareAndExecute($sql, 'i', [$classId]);
    
    if (!$stmt) {
        echo json_encode([
            'success' => false,
            'message' => '获取班级详情失败',
            'class' => null
        ]);
        return;
    }
    
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'message' => '未找到指定班级',
            'class' => null
        ]);
        return;
    }
    
    $class = $result->fetch_assoc();
    
    // 获取班级参赛者
    $participantsSql = "SELECT p.participant_id, p.name, p.gender
                        FROM participants p
                        WHERE p.class_id = ?
                        ORDER BY p.name ASC";
    
    $participantsStmt = prepareAndExecute($participantsSql, 'i', [$classId]);
    $participants = [];
    
    if ($participantsStmt) {
        $participantsResult = $participantsStmt->get_result();
        while ($row = $participantsResult->fetch_assoc()) {
            $participants[] = [
                'participant_id' => (int)$row['participant_id'],
                'name' => $row['name'],
                'gender' => $row['gender']
            ];
        }
    }
    
    // 获取班级成绩
    $resultsSql = "SELECT r.result_id, r.score, r.ranking, r.points, r.is_record_breaking,
                         e.event_id, e.event_name,
                         p.participant_id, p.name as participant_name
                  FROM results r
                  JOIN events e ON r.event_id = e.event_id
                  JOIN participants p ON r.participant_id = p.participant_id
                  WHERE p.class_id = ?
                  ORDER BY r.points DESC, r.ranking ASC";
    
    $resultsStmt = prepareAndExecute($resultsSql, 'i', [$classId]);
    $results = [];
    
    if ($resultsStmt) {
        $resultsResult = $resultsStmt->get_result();
        while ($row = $resultsResult->fetch_assoc()) {
            $results[] = [
                'result_id' => (int)$row['result_id'],
                'event_id' => (int)$row['event_id'],
                'event_name' => $row['event_name'],
                'participant_id' => (int)$row['participant_id'],
                'participant_name' => $row['participant_name'],
                'score' => $row['score'],
                'ranking' => (int)$row['ranking'],
                'points' => (int)$row['points'],
                'is_record_breaking' => (bool)$row['is_record_breaking']
            ];
        }
    }
    
    // 构建返回数据
    $classData = [
        'class_id' => (int)$class['class_id'],
        'class_name' => $class['class_name'],
        'grade' => $class['grade'],
        'total_score' => (float)$class['total_score']
    ];
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'class' => $classData,
        'participants' => $participants,
        'results' => $results
    ]);
} 