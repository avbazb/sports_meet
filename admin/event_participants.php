<?php
/**
 * 比赛参赛人员管理页面
 */
require_once 'auth.php';

// 检查是否有比赛ID
if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    // 跳转回赛事管理页面
    header('Location: events.php');
    exit;
}

$eventId = (int)$_GET['event_id'];

// 获取比赛信息
$eventSql = "SELECT * FROM events WHERE event_id = ?";
$eventStmt = prepareAndExecute($eventSql, 'i', [$eventId]);

if (!$eventStmt) {
    // 查询失败，跳转回赛事管理页面
    header('Location: events.php');
    exit;
}

$eventResult = $eventStmt->get_result();
if ($eventResult->num_rows === 0) {
    // 比赛不存在，跳转回赛事管理页面
    header('Location: events.php');
    exit;
}

$event = $eventResult->fetch_assoc();

// 页面标题
$pageTitle = '参赛人员管理 - ' . $event['event_name'];

// 消息提示
$alertMessage = '';
$alertType = '';

// 处理添加参赛人员请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_participant') {
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
    $laneNumber = isset($_POST['lane_number']) ? (int)$_POST['lane_number'] : null;
    
    if ($participantId <= 0) {
        $alertMessage = '请选择参赛人员';
        $alertType = 'danger';
    } else {
        // 检查是否已经添加
        $checkSql = "SELECT COUNT(*) as count FROM event_participants WHERE event_id = ? AND participant_id = ?";
        $checkStmt = prepareAndExecute($checkSql, 'ii', [$eventId, $participantId]);
        
        if ($checkStmt) {
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $alertMessage = '该参赛人员已添加';
                $alertType = 'danger';
            } else {
                // 添加参赛人员
                $insertSql = "INSERT INTO event_participants (event_id, participant_id, lane_number) VALUES (?, ?, ?)";
                $insertStmt = prepareAndExecute($insertSql, 'iii', [$eventId, $participantId, $laneNumber]);
                
                if ($insertStmt) {
                    $alertMessage = '参赛人员添加成功';
                    $alertType = 'success';
                } else {
                    $alertMessage = '参赛人员添加失败';
                    $alertType = 'danger';
                }
            }
        }
    }
}

// 处理删除参赛人员请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['pid'])) {
    $participantId = (int)$_GET['pid'];
    
    // 删除参赛人员
    $deleteSql = "DELETE FROM event_participants WHERE event_id = ? AND participant_id = ?";
    $deleteStmt = prepareAndExecute($deleteSql, 'ii', [$eventId, $participantId]);
    
    if ($deleteStmt) {
        $alertMessage = '参赛人员移除成功';
        $alertType = 'success';
    } else {
        $alertMessage = '参赛人员移除失败';
        $alertType = 'danger';
    }
}

// 处理批量添加参赛人员请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_add') {
    $participantIds = isset($_POST['participant_ids']) ? $_POST['participant_ids'] : [];
    
    if (empty($participantIds)) {
        $alertMessage = '请选择至少一名参赛人员';
        $alertType = 'danger';
    } else {
        $successCount = 0;
        $failCount = 0;
        
        foreach ($participantIds as $participantId) {
            $participantId = (int)$participantId;
            
            // 检查是否已经添加
            $checkSql = "SELECT COUNT(*) as count FROM event_participants WHERE event_id = ? AND participant_id = ?";
            $checkStmt = prepareAndExecute($checkSql, 'ii', [$eventId, $participantId]);
            
            if ($checkStmt) {
                $result = $checkStmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] === 0) {
                    // 添加参赛人员
                    $insertSql = "INSERT INTO event_participants (event_id, participant_id) VALUES (?, ?)";
                    $insertStmt = prepareAndExecute($insertSql, 'ii', [$eventId, $participantId]);
                    
                    if ($insertStmt) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                }
            }
        }
        
        if ($successCount > 0) {
            $alertMessage = "成功添加 {$successCount} 名参赛人员" . ($failCount > 0 ? "，{$failCount} 名失败" : "");
            $alertType = $failCount > 0 ? 'warning' : 'success';
        } else {
            $alertMessage = '所有参赛人员添加失败或已存在';
            $alertType = 'danger';
        }
    }
}

// 处理批量创建并添加参赛人员请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_create') {
    $data = isset($_POST['batch_data']) ? trim($_POST['batch_data']) : '';
    
    if (empty($data)) {
        $alertMessage = '请输入参赛人员数据';
        $alertType = 'danger';
    } else {
        $lines = explode("\n", $data);
        $successCount = 0;
        $failCount = 0;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line)) {
                continue;
            }
            
            // 解析格式：姓名 性别 班级
            $parts = preg_split('/\s+/', $line, 3);
            
            if (count($parts) >= 3) {
                $name = trim($parts[0]);
                $gender = trim($parts[1]);
                $className = trim($parts[2]);
                
                // 验证性别
                if ($gender !== '男' && $gender !== '女') {
                    $failCount++;
                    continue;
                }
                
                // 查找班级ID
                $classIdSql = "SELECT class_id FROM classes WHERE class_name = ?";
                $classIdStmt = prepareAndExecute($classIdSql, 's', [$className]);
                
                if ($classIdStmt) {
                    $result = $classIdStmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $classRow = $result->fetch_assoc();
                        $classId = $classRow['class_id'];
                    } else {
                        // 班级不存在，尝试创建新班级
                        // 解析班级名称获取年级
                        if (preg_match('/^(小学|初[一二三]|高[一二三])/', $className, $matches)) {
                            $grade = $matches[1];
                            
                            // 添加班级
                            $insertClassSql = "INSERT INTO classes (class_name, grade) VALUES (?, ?)";
                            $insertClassStmt = prepareAndExecute($insertClassSql, 'ss', [$className, $grade]);
                            
                            if ($insertClassStmt) {
                                $classId = getInsertId();
                            } else {
                                $failCount++;
                                continue;
                            }
                        } else {
                            $failCount++;
                            continue;
                        }
                    }
                    
                    // 添加参赛人员
                    $insertSql = "INSERT INTO participants (name, gender, class_id) VALUES (?, ?, ?)";
                    $insertStmt = prepareAndExecute($insertSql, 'ssi', [$name, $gender, $classId]);
                    
                    if ($insertStmt) {
                        $participantId = getInsertId();
                        
                        // 将参赛人员添加到当前比赛
                        $insertEventParticipantSql = "INSERT INTO event_participants (event_id, participant_id) VALUES (?, ?)";
                        $insertEventParticipantStmt = prepareAndExecute($insertEventParticipantSql, 'ii', [$eventId, $participantId]);
                        
                        if ($insertEventParticipantStmt) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
                    } else {
                        $failCount++;
                    }
                } else {
                    $failCount++;
                }
            } else {
                $failCount++;
            }
        }
        
        if ($successCount > 0) {
            $alertMessage = "成功创建并添加 {$successCount} 名参赛人员" . ($failCount > 0 ? "，{$failCount} 名失败" : "");
            $alertType = $failCount > 0 ? 'warning' : 'success';
        } else {
            $alertMessage = '所有参赛人员创建失败';
            $alertType = 'danger';
        }
    }
}

// 获取已分配的参赛人员
$participantsSql = "SELECT ep.*, p.name, p.gender, c.class_name, c.grade 
                    FROM event_participants ep 
                    JOIN participants p ON ep.participant_id = p.participant_id 
                    JOIN classes c ON p.class_id = c.class_id 
                    WHERE ep.event_id = ? 
                    ORDER BY ep.lane_number IS NULL, ep.lane_number ASC, p.name ASC";
$participantsStmt = prepareAndExecute($participantsSql, 'i', [$eventId]);

$participants = [];
if ($participantsStmt) {
    $result = $participantsStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
}

// 获取所有可用的参赛人员（未分配到当前比赛的）
$availableParticipantsSql = "SELECT p.participant_id, p.name, p.gender, c.class_name, c.grade 
                            FROM participants p 
                            JOIN classes c ON p.class_id = c.class_id 
                            WHERE p.participant_id NOT IN (
                                SELECT participant_id FROM event_participants WHERE event_id = ?
                            ) 
                            ORDER BY p.name ASC";
$availableParticipantsStmt = prepareAndExecute($availableParticipantsSql, 'i', [$eventId]);

$availableParticipants = [];
if ($availableParticipantsStmt) {
    $result = $availableParticipantsStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $availableParticipants[] = $row;
    }
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>参赛人员管理 - <?php echo h($event['event_name']); ?></h2>
        <div>
            <a href="events.php" class="btn btn-secondary btn-sm">返回赛事列表</a>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="addParticipantModal">添加参赛人员</button>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="batchAddModal">批量添加</button>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="batchCreateModal">创建并添加</button>
        </div>
    </div>
    
    <!-- 比赛信息 -->
    <div class="event-info" style="margin-bottom: 20px; padding: 15px; background-color: #f5f7ff; border-radius: 8px;">
        <div style="display: flex; justify-content: space-between;">
            <div>
                <p><strong>比赛时间：</strong><?php echo date('Y-m-d H:i', strtotime($event['event_time'])); ?></p>
                <p><strong>状态：</strong><span class="status-badge status-<?php echo strtolower(str_replace('待', 'waiting', str_replace('中', 'ongoing', str_replace('公布成绩', 'finished', str_replace('检录', 'checkin', $event['status']))))); ?>"><?php echo h($event['status']); ?></span></p>
            </div>
            <div>
                <p><strong>组别：</strong><?php echo $event['group_number']; ?> / <?php echo $event['total_groups']; ?></p>
                <p><strong>已分配参赛人员：</strong><?php echo count($participants); ?> 人</p>
            </div>
        </div>
    </div>
    
    <!-- 参赛人员列表 -->
    <?php if (empty($participants)): ?>
        <div class="alert alert-info">
            暂无参赛人员，请点击"添加参赛人员"按钮添加。
        </div>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>姓名</th>
                    <th>性别</th>
                    <th>班级</th>
                    <th>道次</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td><?php echo h($participant['participant_id']); ?></td>
                        <td><?php echo h($participant['name']); ?></td>
                        <td><?php echo h($participant['gender']); ?></td>
                        <td><?php echo h($participant['grade'] . ' ' . $participant['class_name']); ?></td>
                        <td>
                            <form action="" method="post" style="display: flex; align-items: center;">
                                <input type="hidden" name="action" value="update_lane">
                                <input type="hidden" name="participant_id" value="<?php echo $participant['participant_id']; ?>">
                                <input type="number" name="lane_number" class="form-control" style="width: 70px;" value="<?php echo $participant['lane_number']; ?>" min="1" max="8">
                                <button type="submit" class="btn btn-secondary btn-sm" style="margin-left: 5px;">更新</button>
                            </form>
                        </td>
                        <td>
                            <a href="?event_id=<?php echo $eventId; ?>&action=delete&pid=<?php echo $participant['participant_id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               data-confirm="确定要移除这名参赛人员吗？">移除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- 添加参赛人员模态框 -->
<div class="modal" id="addParticipantModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">添加参赛人员</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="?event_id=<?php echo $eventId; ?>" method="post">
            <input type="hidden" name="action" value="add_participant">
            <div class="modal-body">
                <div class="form-group">
                    <label for="participant_id">参赛人员</label>
                    <select id="participant_id" name="participant_id" class="form-control" required>
                        <option value="">请选择参赛人员</option>
                        <?php foreach ($availableParticipants as $participant): ?>
                            <option value="<?php echo $participant['participant_id']; ?>">
                                <?php echo h($participant['name'] . ' (' . $participant['gender'] . ', ' . $participant['grade'] . ' ' . $participant['class_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="lane_number">道次（可选）</label>
                    <input type="number" id="lane_number" name="lane_number" class="form-control" min="1" max="8">
                    <small>根据比赛类型填写，例如短跑比赛的跑道号码</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量添加参赛人员模态框 -->
<div class="modal" id="batchAddModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">批量添加参赛人员</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="?event_id=<?php echo $eventId; ?>" method="post">
            <input type="hidden" name="action" value="batch_add">
            <div class="modal-body">
                <div class="alert alert-info">
                    请选择要添加到比赛《<?php echo h($event['event_name']); ?>》的参赛人员。
                </div>
                
                <div id="availableParticipantsListContainer" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; border-radius: 5px;">
                    <?php if (empty($availableParticipants)): ?>
                        <p>没有可添加的参赛人员</p>
                    <?php else: ?>
                        <div style="margin-bottom: 10px;">
                            <label>
                                <input type="checkbox" id="selectAll"> 全选
                            </label>
                            <div class="search-form" style="margin-top: 10px;">
                                <input type="text" id="searchParticipants" placeholder="搜索参赛人员..." class="form-control">
                            </div>
                        </div>
                        
                        <div class="participant-checkboxes">
                            <?php foreach ($availableParticipants as $participant): ?>
                                <div class="checkbox-item">
                                    <label>
                                        <input type="checkbox" name="participant_ids[]" value="<?php echo $participant['participant_id']; ?>">
                                        <?php echo h($participant['name'] . ' (' . $participant['gender'] . ', ' . $participant['grade'] . ' ' . $participant['class_name'] . ')'); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary" <?php echo empty($availableParticipants) ? 'disabled' : ''; ?>>批量添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量创建参赛人员模态框 -->
<div class="modal" id="batchCreateModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">批量创建并添加参赛人员</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="?event_id=<?php echo $eventId; ?>" method="post">
            <input type="hidden" name="action" value="batch_create">
            <div class="modal-body">
                <div class="alert alert-info">
                    批量创建参赛人员并添加到比赛《<?php echo h($event['event_name']); ?>》中。
                </div>
                
                <div class="form-group">
                    <label for="batch_data">参赛人员数据</label>
                    <textarea id="batch_data" name="batch_data" class="form-control" rows="10" required placeholder="格式：姓名 性别 班级&#10;例如：张三 男 初二1班"></textarea>
                </div>
                
                <div class="alert alert-info">
                    <strong>格式说明：</strong>
                    <p>每行一个参赛人员，格式为：姓名 性别 班级</p>
                    <p>例如：张三 男 初二1班</p>
                    <p>性别只能是"男"或"女"</p>
                    <p>如果班级不存在，系统将尝试自动创建（班级名称需包含年级信息，如"初二1班"）</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">批量创建并添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 更新道次处理 -->
<?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_lane'): ?>
    <?php
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
    $laneNumber = isset($_POST['lane_number']) ? (int)$_POST['lane_number'] : null;
    
    if ($participantId > 0) {
        $updateSql = "UPDATE event_participants SET lane_number = ? WHERE event_id = ? AND participant_id = ?";
        $updateStmt = prepareAndExecute($updateSql, 'iii', [$laneNumber ?: null, $eventId, $participantId]);
        
        if ($updateStmt) {
            echo '<script>alert("道次更新成功"); window.location.href = "?event_id=' . $eventId . '";</script>';
        } else {
            echo '<script>alert("道次更新失败"); window.location.href = "?event_id=' . $eventId . '";</script>';
        }
    }
    ?>
<?php endif; ?>

<?php
// 页面特定的脚本
$pageScript = <<<SCRIPT
// 全选功能
document.getElementById('selectAll')?.addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('input[name="participant_ids[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = document.getElementById('selectAll').checked;
    });
});

// 搜索功能
document.getElementById('searchParticipants')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const items = document.querySelectorAll('.checkbox-item');
    
    items.forEach(function(item) {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
});
SCRIPT;

// 包含底部模板
include('templates/footer.php');
?> 