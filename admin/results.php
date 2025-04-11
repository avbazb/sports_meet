<?php
/**
 * 比赛成绩管理页面
 */
require_once 'auth.php';

// 更新所有比赛状态
updateAllEventStatus();

// 页面标题
$pageTitle = '成绩管理';

// 消息提示
$alertMessage = '';
$alertType = '';

// 处理添加成绩请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_result') {
    $eventId = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
    $score = isset($_POST['score']) ? trim($_POST['score']) : '';
    $ranking = isset($_POST['ranking']) ? (int)$_POST['ranking'] : 0;
    $points = isset($_POST['points']) ? (int)$_POST['points'] : 0;
    
    if ($eventId <= 0 || $participantId <= 0 || empty($score) || $ranking <= 0) {
        $alertMessage = '请填写所有必填字段';
        $alertType = 'danger';
    } else {
        // 获取比赛信息
        $eventSql = "SELECT event_name FROM events WHERE event_id = ?";
        $eventStmt = prepareAndExecute($eventSql, 'i', [$eventId]);
        $eventName = '';
        
        if ($eventStmt) {
            $eventResult = $eventStmt->get_result();
            if ($eventResult->num_rows > 0) {
                $eventRow = $eventResult->fetch_assoc();
                $eventName = $eventRow['event_name'];
            }
        }
        
        // 获取参赛者信息
        $participantSql = "SELECT p.name, c.class_name FROM participants p JOIN classes c ON p.class_id = c.class_id WHERE p.participant_id = ?";
        $participantStmt = prepareAndExecute($participantSql, 'i', [$participantId]);
        $participantName = '';
        $className = '';
        
        if ($participantStmt) {
            $participantResult = $participantStmt->get_result();
            if ($participantResult->num_rows > 0) {
                $participantRow = $participantResult->fetch_assoc();
                $participantName = $participantRow['name'];
                $className = $participantRow['class_name'];
            }
        }
        
        // 检查是否破纪录
        $isRecordBreaking = isRecordBreaking($eventName, $score);
        
        // 如果没有指定分数或分数为0，根据排名自动计算
        if ($points <= 0) {
            // 基础积分计算：9、7、6、5、4、3、2、1
            if ($ranking <= 8) {
                $pointsMap = [9, 7, 6, 5, 4, 3, 2, 1];
                $points = $pointsMap[$ranking - 1];
            } else {
                $points = 0;
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
            
            // 如果破记录，分数翻倍
            if ($isRecordBreaking) {
                $points *= 2;
            }
        }
        
        // 添加成绩
        $insertSql = "INSERT INTO results (event_id, participant_id, score, ranking, points, is_record_breaking) VALUES (?, ?, ?, ?, ?, ?)";
        $insertStmt = prepareAndExecute($insertSql, 'iisiii', [$eventId, $participantId, $score, $ranking, $points, $isRecordBreaking]);
        
        if ($insertStmt) {
            $alertMessage = '成绩添加成功';
            $alertType = 'success';
            
            // 如果破纪录，添加新纪录
            if ($isRecordBreaking) {
                addNewRecord($eventName, $score, $participantName, $className);
                $alertMessage .= '，并且打破了原有记录！';
            }
            
            // 更新比赛状态为公布成绩
            $updateSql = "UPDATE events SET status = '公布成绩' WHERE event_id = ?";
            prepareAndExecute($updateSql, 'i', [$eventId]);
            
            // 重新计算班级团体总分
            calculateClassTotalScores();
            
            // 获取添加的成绩详情，用于生成AI报告
            $resultDetailSql = "SELECT r.*, p.name as participant_name, c.class_name, e.event_name 
                                FROM results r
                                JOIN participants p ON r.participant_id = p.participant_id
                                JOIN classes c ON p.class_id = c.class_id
                                JOIN events e ON r.event_id = e.event_id
                                WHERE r.event_id = ?
                                ORDER BY r.ranking ASC";
            $resultDetailStmt = prepareAndExecute($resultDetailSql, 'i', [$eventId]);
            
            $results = [];
            if ($resultDetailStmt) {
                $resultSet = $resultDetailStmt->get_result();
                while ($row = $resultSet->fetch_assoc()) {
                    $results[] = $row;
                }
                
                // 如果有足够的成绩数据（至少前三名），则生成AI报告
                if (count($results) >= 3) {
                    $eventName = $results[0]['event_name'];
                    // 异步调用AI生成报告 - 在实际环境中应该使用队列系统或者异步处理
                    generateAIReport($eventId, $eventName, $results);
                }
            }
        } else {
            $alertMessage = '成绩添加失败';
            $alertType = 'danger';
        }
    }
}

// 处理编辑成绩请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_result') {
    $resultId = isset($_POST['result_id']) ? (int)$_POST['result_id'] : 0;
    $score = isset($_POST['score']) ? trim($_POST['score']) : '';
    $ranking = isset($_POST['ranking']) ? (int)$_POST['ranking'] : 0;
    $points = isset($_POST['points']) ? (int)$_POST['points'] : 0;
    
    if ($resultId <= 0 || empty($score) || $ranking <= 0) {
        $alertMessage = '请填写所有必填字段';
        $alertType = 'danger';
    } else {
        // 获取成绩信息
        $resultSql = "SELECT r.*, e.event_name, p.name as participant_name, c.class_name 
                      FROM results r 
                      JOIN events e ON r.event_id = e.event_id 
                      JOIN participants p ON r.participant_id = p.participant_id 
                      JOIN classes c ON p.class_id = c.class_id 
                      WHERE r.result_id = ?";
        $resultStmt = prepareAndExecute($resultSql, 'i', [$resultId]);
        
        if ($resultStmt) {
            $resultResult = $resultStmt->get_result();
            
            if ($resultResult->num_rows > 0) {
                $resultRow = $resultResult->fetch_assoc();
                $eventName = $resultRow['event_name'];
                $participantName = $resultRow['participant_name'];
                $className = $resultRow['class_name'];
                
                // 检查是否破纪录
                $isRecordBreaking = isRecordBreaking($eventName, $score);
                
                // 如果没有指定分数或分数为0，根据排名自动计算
                if ($points <= 0) {
                    // 基础积分计算：9、7、6、5、4、3、2、1
                    if ($ranking <= 8) {
                        $pointsMap = [9, 7, 6, 5, 4, 3, 2, 1];
                        $points = $pointsMap[$ranking - 1];
                    } else {
                        $points = 0;
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
                    
                    // 如果破记录，分数翻倍
                    if ($isRecordBreaking) {
                        $points *= 2;
                    }
                }
                
                // 更新成绩
                $updateSql = "UPDATE results SET score = ?, ranking = ?, points = ?, is_record_breaking = ? WHERE result_id = ?";
                $updateStmt = prepareAndExecute($updateSql, 'siiii', [$score, $ranking, $points, $isRecordBreaking, $resultId]);
                
                if ($updateStmt) {
                    $alertMessage = '成绩更新成功';
                    $alertType = 'success';
                    
                    // 如果破纪录，添加新纪录
                    if ($isRecordBreaking && !$resultRow['is_record_breaking']) {
                        addNewRecord($eventName, $score, $participantName, $className);
                        $alertMessage .= '，并且打破了原有记录！';
                    }
                    
                    // 重新计算班级团体总分
                    calculateClassTotalScores();
                } else {
                    $alertMessage = '成绩更新失败';
                    $alertType = 'danger';
                }
            } else {
                $alertMessage = '找不到要编辑的成绩';
                $alertType = 'danger';
            }
        }
    }
}

// 处理删除成绩请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $resultId = (int)$_GET['id'];
    
    // 删除成绩
    $deleteSql = "DELETE FROM results WHERE result_id = ?";
    $deleteStmt = prepareAndExecute($deleteSql, 'i', [$resultId]);
    
    if ($deleteStmt) {
        $alertMessage = '成绩删除成功';
        $alertType = 'success';
        
        // 重新计算班级团体总分
        calculateClassTotalScores();
    } else {
        $alertMessage = '成绩删除失败';
        $alertType = 'danger';
    }
}

// 处理批量添加成绩请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_add') {
    $eventId = isset($_POST['batch_event_id']) ? (int)$_POST['batch_event_id'] : 0;
    $data = isset($_POST['batch_data']) ? trim($_POST['batch_data']) : '';
    
    if ($eventId <= 0 || empty($data)) {
        $alertMessage = '请选择比赛并输入成绩数据';
        $alertType = 'danger';
    } else {
        // 获取比赛信息
        $eventSql = "SELECT event_name FROM events WHERE event_id = ?";
        $eventStmt = prepareAndExecute($eventSql, 'i', [$eventId]);
        $eventName = '';
        
        if ($eventStmt) {
            $eventResult = $eventStmt->get_result();
            if ($eventResult->num_rows > 0) {
                $eventRow = $eventResult->fetch_assoc();
                $eventName = $eventRow['event_name'];
            }
        }
        
        $lines = explode("\n", $data);
        $successCount = 0;
        $failCount = 0;
        $recordBreakingCount = 0;
        $addedEventIds = [];
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // 解析格式：参赛者ID 成绩 名次 得分
            $parts = preg_split('/\s+/', $line, 4);
            
            if (count($parts) >= 3) {
                $participantId = (int)$parts[0];
                $score = trim($parts[1]);
                $ranking = (int)$parts[2];
                $points = count($parts) >= 4 ? (int)$parts[3] : 0;
                
                // 验证参赛者ID和成绩
                if ($participantId <= 0 || empty($score) || $ranking <= 0) {
                    $failCount++;
                    continue;
                }
                
                // 如果没有指定分数，根据名次自动计算
                if (count($parts) < 4 || $points <= 0) {
                    // 基础积分计算：9、7、6、5、4、3、2、1
                    if ($ranking <= 8) {
                        $pointsMap = [9, 7, 6, 5, 4, 3, 2, 1];
                        $points = $pointsMap[$ranking - 1];
                    } else {
                        $points = 0;
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
                }
                
                // 获取参赛者信息
                $participantSql = "SELECT p.name, c.class_name FROM participants p JOIN classes c ON p.class_id = c.class_id WHERE p.participant_id = ?";
                $participantStmt = prepareAndExecute($participantSql, 'i', [$participantId]);
                $participantName = '';
                $className = '';
                
                if ($participantStmt) {
                    $participantResult = $participantStmt->get_result();
                    if ($participantResult->num_rows > 0) {
                        $participantRow = $participantResult->fetch_assoc();
                        $participantName = $participantRow['name'];
                        $className = $participantRow['class_name'];
                    }
                }
                
                // 检查是否破纪录
                $isRecordBreaking = isRecordBreaking($eventName, $score);
                
                // 如果破记录，分数翻倍
                if ($isRecordBreaking) {
                    $points *= 2;
                }
                
                // 添加成绩
                $insertSql = "INSERT INTO results (event_id, participant_id, score, ranking, points, is_record_breaking) VALUES (?, ?, ?, ?, ?, ?)";
                $insertStmt = prepareAndExecute($insertSql, 'iisiii', [$eventId, $participantId, $score, $ranking, $points, $isRecordBreaking]);
                
                if ($insertStmt) {
                    $successCount++;
                    
                    // 如果破纪录，添加新纪录
                    if ($isRecordBreaking) {
                        addNewRecord($eventName, $score, $participantName, $className);
                        $recordBreakingCount++;
                    }
                    
                    $addedEventIds[] = $eventId;
                } else {
                    $failCount++;
                }
            } else {
                $failCount++;
            }
        }
        
        // 批量添加成绩成功后生成AI报告
        if ($successCount > 0) {
            $alertMessage = "成功添加 {$successCount} 条成绩";
            if ($failCount > 0) {
                $alertMessage .= "，{$failCount} 条失败";
            }
            if ($recordBreakingCount > 0) {
                $alertMessage .= "，其中 {$recordBreakingCount} 条破纪录";
            }
            $alertType = $failCount > 0 ? 'warning' : 'success';
            
            // 重新计算班级总分
            calculateClassTotalScores();
            
            // 为每个有足够成绩的项目生成AI报告
            $processedEvents = [];
            foreach ($addedEventIds as $addedEventId) {
                if (in_array($addedEventId, $processedEvents)) {
                    continue;
                }
                
                $resultDetailSql = "SELECT r.*, p.name as participant_name, c.class_name, e.event_name 
                                    FROM results r
                                    JOIN participants p ON r.participant_id = p.participant_id
                                    JOIN classes c ON p.class_id = c.class_id
                                    JOIN events e ON r.event_id = e.event_id
                                    WHERE r.event_id = ?
                                    ORDER BY r.ranking ASC";
                $resultDetailStmt = prepareAndExecute($resultDetailSql, 'i', [$addedEventId]);
                
                $results = [];
                if ($resultDetailStmt) {
                    $resultSet = $resultDetailStmt->get_result();
                    while ($row = $resultSet->fetch_assoc()) {
                        $results[] = $row;
                    }
                    
                    // 如果有足够的成绩数据（至少前三名），则生成AI报告
                    if (count($results) >= 3) {
                        $eventName = $results[0]['event_name'];
                        // 异步调用AI生成报告
                        generateAIReport($addedEventId, $eventName, $results);
                    }
                }
                
                $processedEvents[] = $addedEventId;
            }
        } else {
            $alertMessage = '所有成绩添加失败';
            $alertType = 'danger';
        }
    }
}

// 分页和搜索功能
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 搜索条件
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterEvent = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$filterClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// 获取所有比赛（用于筛选）
$eventsSql = "SELECT event_id, event_name FROM events ORDER BY event_time DESC";
$events = [];
$eventsResult = query($eventsSql);

if ($eventsResult && $eventsResult->num_rows > 0) {
    while ($row = $eventsResult->fetch_assoc()) {
        $events[] = $row;
    }
}

// 获取所有班级（用于筛选）
$classesSql = "SELECT class_id, grade, class_name FROM classes ORDER BY grade, class_name";
$classes = [];
$classesResult = query($classesSql);

if ($classesResult && $classesResult->num_rows > 0) {
    while ($row = $classesResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

// 构建查询条件
$whereConditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "(e.event_name LIKE ? OR p.name LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'ss';
}

if ($filterEvent > 0) {
    $whereConditions[] = "r.event_id = ?";
    $params[] = $filterEvent;
    $types .= 'i';
}

if ($filterClass > 0) {
    $whereConditions[] = "p.class_id = ?";
    $params[] = $filterClass;
    $types .= 'i';
}

$whereClause = empty($whereConditions) ? '' : " WHERE " . implode(' AND ', $whereConditions);

// 获取成绩总数
$countSql = "SELECT COUNT(*) as total 
             FROM results r 
             JOIN events e ON r.event_id = e.event_id 
             JOIN participants p ON r.participant_id = p.participant_id"
             . $whereClause;
$countStmt = !empty($params) ? prepareAndExecute($countSql, $types, $params) : query($countSql);

$total = 0;
if ($countStmt) {
    if (is_a($countStmt, 'mysqli_stmt')) {
        $result = $countStmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $total = $row['total'];
        }
    } else if (is_a($countStmt, 'mysqli_result')) {
        if ($row = $countStmt->fetch_assoc()) {
            $total = $row['total'];
        }
    }
}

// 获取成绩列表
$sql = "SELECT r.*, e.event_name, p.name as participant_name, c.class_name, c.grade
        FROM results r 
        JOIN events e ON r.event_id = e.event_id 
        JOIN participants p ON r.participant_id = p.participant_id 
        JOIN classes c ON p.class_id = c.class_id"
        . $whereClause . 
        " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$results = [];
$stmt = prepareAndExecute($sql, $types, $params);

if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>成绩管理</h2>
        <div>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="addResultModal">添加成绩</button>
            <button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="batchAddModal">批量添加</button>
        </div>
    </div>
    
    <!-- 搜索和筛选 -->
    <div class="search-form">
        <form action="" method="get" class="search-input">
            <input type="text" name="search" placeholder="搜索比赛/参赛者..." value="<?php echo h($search); ?>">
            
            <select name="event_id" class="form-control" style="width: auto; flex: 0 0 200px;">
                <option value="0">所有比赛</option>
                <?php foreach ($events as $event): ?>
                    <option value="<?php echo $event['event_id']; ?>" <?php echo $filterEvent == $event['event_id'] ? 'selected' : ''; ?>>
                        <?php echo h($event['event_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="class_id" class="form-control" style="width: auto; flex: 0 0 150px;">
                <option value="0">所有班级</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['class_id']; ?>" <?php echo $filterClass == $class['class_id'] ? 'selected' : ''; ?>>
                        <?php echo h($class['grade'] . ' ' . $class['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" class="btn btn-primary">搜索</button>
            
            <?php if (!empty($search) || $filterEvent > 0 || $filterClass > 0): ?>
                <a href="results.php" class="btn btn-secondary">清除筛选</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- 成绩列表 -->
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>比赛</th>
                <th>参赛者</th>
                <th>班级</th>
                <th>成绩</th>
                <th>名次</th>
                <th>得分</th>
                <th>破纪录</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($results)): ?>
                <tr>
                    <td colspan="9" class="text-center">暂无成绩数据</td>
                </tr>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <tr>
                        <td><?php echo h($result['result_id']); ?></td>
                        <td><?php echo h($result['event_name']); ?></td>
                        <td><?php echo h($result['participant_name']); ?></td>
                        <td><?php echo h($result['grade'] . ' ' . $result['class_name']); ?></td>
                        <td><?php echo h($result['score']); ?></td>
                        <td><?php echo h($result['ranking']); ?></td>
                        <td><?php echo h($result['points']); ?></td>
                        <td>
                            <?php if ($result['is_record_breaking']): ?>
                                <span class="record-badge">破纪录</span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-secondary btn-sm edit-result-btn" 
                                    data-id="<?php echo $result['result_id']; ?>"
                                    data-score="<?php echo h($result['score']); ?>"
                                    data-ranking="<?php echo $result['ranking']; ?>"
                                    data-points="<?php echo $result['points']; ?>">编辑</button>
                            <a href="?action=delete&id=<?php echo $result['result_id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               data-confirm="确定要删除这条成绩记录吗？">删除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- 分页 -->
    <?php 
    $queryParams = [];
    if (!empty($search)) $queryParams[] = 'search=' . urlencode($search);
    if ($filterEvent > 0) $queryParams[] = 'event_id=' . $filterEvent;
    if ($filterClass > 0) $queryParams[] = 'class_id=' . $filterClass;
    
    $queryString = empty($queryParams) ? '' : '?' . implode('&', $queryParams) . '&';
    echo generatePagination($total, $page, $perPage, 'results.php' . $queryString);
    ?>
</div>

<!-- 添加成绩模态框 -->
<div class="modal" id="addResultModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">添加成绩</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="results.php" method="post">
            <input type="hidden" name="action" value="add_result">
            <div class="modal-body">
                <div class="form-group">
                    <label for="event_id">比赛</label>
                    <select id="event_id" name="event_id" class="form-control" required>
                        <option value="">请选择比赛</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['event_id']; ?>">
                                <?php echo h($event['event_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="participant_id">参赛者</label>
                    <select id="participant_id" name="participant_id" class="form-control" required>
                        <option value="">请先选择比赛</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="score">成绩</label>
                            <input type="text" id="score" name="score" class="form-control" required>
                            <small>例如：10.5（秒）或 5.75（米）</small>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="ranking">名次</label>
                            <input type="number" id="ranking" name="ranking" class="form-control" min="1" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="points">得分</label>
                    <input type="number" id="points" name="points" class="form-control" min="0" value="0">
                    <small>按照名次自动计算：前八名分别为9、7、6、5、4、3、2、1分。接力项目和800米项目翻倍计分。</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑成绩模态框 -->
<div class="modal" id="editResultModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">编辑成绩</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="results.php" method="post">
            <input type="hidden" name="action" value="edit_result">
            <input type="hidden" name="result_id" id="edit_result_id">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_score">成绩</label>
                            <input type="text" id="edit_score" name="score" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="edit_ranking">名次</label>
                            <input type="number" id="edit_ranking" name="ranking" class="form-control" min="1" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_points">得分</label>
                    <input type="number" id="edit_points" name="points" class="form-control" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量添加成绩模态框 -->
<div class="modal" id="batchAddModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">批量添加成绩</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="results.php" method="post">
            <input type="hidden" name="action" value="batch_add">
            <div class="modal-body">
                <div class="form-group">
                    <label for="batch_event_id">选择比赛</label>
                    <select id="batch_event_id" name="batch_event_id" class="form-control" required>
                        <option value="">请选择比赛</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['event_id']; ?>">
                                <?php echo h($event['event_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="batch_data">成绩数据</label>
                    <textarea id="batch_data" name="batch_data" class="form-control" rows="10" required placeholder="格式：参赛者ID 成绩 名次 得分&#10;例如：1 10.5 1 9"></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>格式说明：</strong>
                    <p>每行一条成绩，格式为：参赛者ID 成绩 名次 得分</p>
                    <p>例如：1 10.5 1 9</p>
                    <p>得分可以省略，系统将根据名次自动计算：</p>
                    <ul>
                        <li>前八名按9、7、6、5、4、3、2、1记分</li>
                        <li>接力项目和800米项目分数翻倍</li>
                        <li>800米项目前八名之后完成比赛者得1分</li>
                        <li>破校记录积分翻倍</li>
                    </ul>
                    <p>选择比赛后，可以点击下方按钮获取该比赛的参赛者列表及ID</p>
                </div>
                
                <button type="button" class="btn btn-secondary" id="getParticipantsBtn">获取参赛者列表</button>
                <div id="participantsList" style="margin-top: 10px; max-height: 200px; overflow-y: auto; display: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>姓名</th>
                                <th>班级</th>
                            </tr>
                        </thead>
                        <tbody id="participantsListBody">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">批量添加</button>
            </div>
        </form>
    </div>
</div>

<?php
// 页面特定的脚本
$pageScript = <<<SCRIPT
// 初始化编辑按钮
document.querySelectorAll('.edit-result-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const score = this.getAttribute('data-score');
        const ranking = this.getAttribute('data-ranking');
        const points = this.getAttribute('data-points');
        
        // 填充表单
        document.getElementById('edit_result_id').value = id;
        document.getElementById('edit_score').value = score;
        document.getElementById('edit_ranking').value = ranking;
        document.getElementById('edit_points').value = points;
        
        // 显示模态框
        openModal(document.getElementById('editResultModal'));
    });
});

// 编辑表单中的自动计分
document.getElementById('edit_ranking').addEventListener('change', function() {
    const ranking = parseInt(this.value);
    const pointsInput = document.getElementById('edit_points');
    
    // 获取当前编辑的比赛名称（从表格中）
    const resultId = document.getElementById('edit_result_id').value;
    // 查找包含该结果ID的行
    let eventName = '';
    document.querySelectorAll('.data-table tr').forEach(function(row) {
        const btn = row.querySelector('button[data-id="' + resultId + '"]');
        if (btn) {
            // 获取该行中的第2列（比赛名称）
            eventName = row.querySelector('td:nth-child(2)').textContent.trim();
        }
    });
    
    if (ranking > 0) {
        // 基础积分计算：9、7、6、5、4、3、2、1
        let points = 0;
        
        if (ranking <= 8) {
            const pointsMap = [9, 7, 6, 5, 4, 3, 2, 1];
            points = pointsMap[ranking - 1];
        }
        
        // 接力项目分数翻倍
        if (eventName.includes('接力') || eventName.includes('4x')) {
            points *= 2;
        }
        
        // 长跑项目（800米）双倍计分
        if (eventName.includes('800米')) {
            points *= 2;
            
            // 前八名之后完成比赛者加1分
            if (ranking > 8) {
                points = 1;
            }
        }
        
        pointsInput.value = points;
    }
});

// 监听比赛选择变化，加载对应的参赛者
document.getElementById('event_id').addEventListener('change', function() {
    const eventId = this.value;
    const participantSelect = document.getElementById('participant_id');
    
    if (eventId) {
        // 清空参赛者下拉框
        participantSelect.innerHTML = '<option value="">加载中...</option>';
        
        // 发送 AJAX 请求获取参赛者
        ajax('api/get_event_participants.php?event_id=' + eventId, 'GET', null, function(error, response) {
            if (error) {
                participantSelect.innerHTML = '<option value="">加载失败</option>';
                return;
            }
            
            if (response.success) {
                let options = '<option value="">请选择参赛者</option>';
                
                response.participants.forEach(function(participant) {
                    options += '<option value="' + participant.id + '">' + participant.name + ' (' + participant.class_name + ')</option>';
                });
                
                participantSelect.innerHTML = options;
            } else {
                participantSelect.innerHTML = '<option value="">暂无参赛者</option>';
            }
        });
    } else {
        participantSelect.innerHTML = '<option value="">请先选择比赛</option>';
    }
});

// 自动计算得分
document.getElementById('ranking').addEventListener('change', function() {
    const ranking = parseInt(this.value);
    const pointsInput = document.getElementById('points');
    const eventSelect = document.getElementById('event_id');
    const eventName = eventSelect.options[eventSelect.selectedIndex].text;
    
    if (ranking > 0) {
        // 基础积分计算：9、7、6、5、4、3、2、1
        let points = 0;
        
        if (ranking <= 8) {
            const pointsMap = [9, 7, 6, 5, 4, 3, 2, 1];
            points = pointsMap[ranking - 1];
        }
        
        // 接力项目分数翻倍
        if (eventName.includes('接力') || eventName.includes('4x')) {
            points *= 2;
        }
        
        // 长跑项目（800米）双倍计分
        if (eventName.includes('800米')) {
            points *= 2;
            
            // 前八名之后完成比赛者加1分
            if (ranking > 8) {
                points = 1;
            }
        }
        
        pointsInput.value = points;
    }
});

// 获取参赛者列表按钮
document.getElementById('getParticipantsBtn').addEventListener('click', function() {
    const eventId = document.getElementById('batch_event_id').value;
    const participantsListBody = document.getElementById('participantsListBody');
    const participantsList = document.getElementById('participantsList');
    
    if (eventId) {
        // 显示加载中
        participantsListBody.innerHTML = '<tr><td colspan="3">加载中...</td></tr>';
        participantsList.style.display = 'block';
        
        // 发送 AJAX 请求获取参赛者
        ajax('api/get_event_participants.php?event_id=' + eventId, 'GET', null, function(error, response) {
            if (error) {
                participantsListBody.innerHTML = '<tr><td colspan="3">加载失败</td></tr>';
                return;
            }
            
            if (response.success && response.participants.length > 0) {
                let html = '';
                
                response.participants.forEach(function(participant) {
                    html += '<tr>';
                    html += '<td>' + participant.id + '</td>';
                    html += '<td>' + participant.name + '</td>';
                    html += '<td>' + participant.class_name + '</td>';
                    html += '</tr>';
                });
                
                participantsListBody.innerHTML = html;
            } else {
                participantsListBody.innerHTML = '<tr><td colspan="3">暂无参赛者</td></tr>';
            }
        });
    } else {
        alert('请先选择比赛');
    }
});
SCRIPT;

// 包含底部模板
include('templates/footer.php');
?> 