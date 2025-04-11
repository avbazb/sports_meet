<?php
/**
 * 赛事管理页面
 */
require_once 'auth.php';

// 更新所有比赛状态
updateAllEventStatus();

// 页面标题
$pageTitle = '赛事管理';

// 消息提示
$alertMessage = '';
$alertType = '';

// 获取当前设置的运动会天数
$settingsQuery = "SELECT * FROM sports_settings WHERE is_active = 1 LIMIT 1";
$settingsResult = query($settingsQuery);
$settings = null;
$maxDays = 2; // 默认为2天

if ($settingsResult && $settingsResult->num_rows > 0) {
    $settings = $settingsResult->fetch_assoc();
    $maxDays = (int)$settings['days'];
}

// 处理添加赛事请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_event') {
    $eventName = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
    $participantCount = isset($_POST['participant_count']) ? (int)$_POST['participant_count'] : 0;
    $groupCount = isset($_POST['group_count']) ? (int)$_POST['group_count'] : 1;
    $eventTime = isset($_POST['event_time']) ? trim($_POST['event_time']) : '';
    $eventDay = isset($_POST['event_day']) ? (int)$_POST['event_day'] : 1;
    
    if (empty($eventName) || empty($eventTime) || $participantCount <= 0 || $groupCount <= 0 || $eventDay <= 0) {
        $alertMessage = '请填写所有必填字段';
        $alertType = 'danger';
    } else {
        // 创建比赛分组
        $result = createEventGroups($eventName, $groupCount, $participantCount, $eventTime, $eventDay);
        
        if ($result) {
            $alertMessage = '赛事添加成功';
            $alertType = 'success';
        } else {
            $alertMessage = '赛事添加失败';
            $alertType = 'danger';
        }
    }
}

// 处理删除赛事请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $eventId = (int)$_GET['id'];
    
    // 检查是否有关联的成绩
    $checkSql = "SELECT COUNT(*) as count FROM results WHERE event_id = ?";
    $checkStmt = prepareAndExecute($checkSql, 'i', [$eventId]);
    
    if ($checkStmt) {
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        
        if ($row['count'] > 0) {
            $alertMessage = '无法删除已有成绩的赛事';
            $alertType = 'danger';
        } else {
            // 删除赛事
            $deleteSql = "DELETE FROM events WHERE event_id = ? OR parent_event_id = ?";
            $deleteStmt = prepareAndExecute($deleteSql, 'ii', [$eventId, $eventId]);
            
            if ($deleteStmt) {
                $alertMessage = '赛事删除成功';
                $alertType = 'success';
            } else {
                $alertMessage = '赛事删除失败';
                $alertType = 'danger';
            }
        }
    }
}

// 处理更新状态请求
if (isset($_GET['action']) && $_GET['action'] === 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $eventId = (int)$_GET['id'];
    $status = $_GET['status'];
    
    // 验证状态值
    $validStatuses = ['未开始', '进行中', '已结束'];
    
    if (in_array($status, $validStatuses)) {
        $updateSql = "UPDATE events SET status = ? WHERE event_id = ? OR parent_event_id = ?";
        $updateStmt = prepareAndExecute($updateSql, 'sii', [$status, $eventId, $eventId]);
        
        if ($updateStmt) {
            $alertMessage = '赛事状态更新成功';
            $alertType = 'success';
        } else {
            $alertMessage = '赛事状态更新失败';
            $alertType = 'danger';
        }
    } else {
        $alertMessage = '无效的状态值';
        $alertType = 'danger';
    }
}

// 处理批量添加赛事请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_add') {
    $data = isset($_POST['batch_data']) ? trim($_POST['batch_data']) : '';
    
    if (empty($data)) {
        $alertMessage = '请输入赛事数据';
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
            
            // 解析格式：比赛项目 人数 组数 比赛时间 第几天
            $parts = preg_split('/\s+/', $line, 5);
            
            if (count($parts) >= 4) {
                $eventName = trim($parts[0]);
                $participantCount = (int)$parts[1];
                $groupCount = (int)$parts[2];
                $eventTime = trim($parts[3]);
                $eventDay = count($parts) >= 5 ? (int)$parts[4] : 1;
                
                // 将比赛时间转换为完整日期时间格式
                if (strpos($eventTime, ':') !== false && strpos($eventTime, '-') === false) {
                    // 只有时间，添加今天的日期
                    $eventTime = date('Y-m-d') . ' ' . $eventTime;
                }
                
                $result = createEventGroups($eventName, $groupCount, $participantCount, $eventTime, $eventDay);
                
                if ($result) {
                    $successCount++;
                } else {
                    $failCount++;
                }
            } else {
                $failCount++;
            }
        }
        
        if ($successCount > 0) {
            $alertMessage = "成功添加 {$successCount} 个赛事" . ($failCount > 0 ? "，{$failCount} 个失败" : "");
            $alertType = $failCount > 0 ? 'warning' : 'success';
        } else {
            $alertMessage = '所有赛事添加失败';
            $alertType = 'danger';
        }
    }
}

// 获取赛事列表
$events = [];

// 分页
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// 搜索功能
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$whereClause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $whereClause = " WHERE event_name LIKE ? ";
    $params[] = "%{$search}%";
    $types .= 's';
}

// 获取总记录数
$countSql = "SELECT COUNT(*) as total FROM events WHERE parent_event_id IS NULL" . $whereClause;
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

// 获取赛事列表
$sql = "SELECT * FROM events WHERE parent_event_id IS NULL" . $whereClause . " ORDER BY event_day ASC, event_time ASC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = prepareAndExecute($sql, $types, $params);

if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>赛事管理</h2>
        <div>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="addEventModal" onclick="console.log('点击添加赛事按钮')">添加赛事</button>
            <button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="batchAddModal">批量添加</button>
        </div>
    </div>
    
    <div class="search-form">
        <form action="" method="get" class="search-input">
            <input type="text" name="search" placeholder="搜索赛事名称..." value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-primary">搜索</button>
            <?php if (!empty($search)): ?>
                <a href="events.php" class="btn btn-secondary">清除</a>
            <?php endif; ?>
        </form>
    </div>
    
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>赛事名称</th>
                <th>参赛人数</th>
                <th>组数</th>
                <th>比赛日</th>
                <th>比赛时间</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($events)): ?>
                <tr>
                    <td colspan="8" class="text-center">暂无赛事数据</td>
                </tr>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?php echo h($event['event_id']); ?></td>
                        <td><?php echo h($event['event_name']); ?></td>
                        <td><?php echo h($event['participant_count']); ?></td>
                        <td><?php echo h($event['total_groups']); ?></td>
                        <td>第<?php echo h($event['event_day'] ?? 1); ?>天</td>
                        <td><?php echo date('H:i', strtotime($event['event_time'])); ?></td>
                        <td>
                            <?php 
                            $statusClass = '';
                            switch ($event['status']) {
                                case '未开始':
                                    $statusClass = 'status-waiting';
                                    break;
                                case '进行中':
                                    $statusClass = 'status-ongoing';
                                    break;
                                case '已结束':
                                    $statusClass = 'status-finished';
                                    break;
                            }
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo h($event['status']); ?></span>
                        </td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-secondary btn-sm" onclick="showChangeStatusModal(<?php echo $event['event_id']; ?>, '<?php echo h($event['event_name']); ?>')">修改状态</button>
                                <a href="event_participants.php?event_id=<?php echo $event['event_id']; ?>" class="btn btn-secondary btn-sm">管理参赛者</a>
                                <a href="?action=delete&id=<?php echo $event['event_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('确定要删除这个赛事吗？所有关联的分组赛事也将被删除。')">删除</a>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php 
    // 生成分页链接
    echo generatePagination($total, $page, $perPage, 'events.php' . (!empty($search) ? '?search=' . urlencode($search) . '&' : '?'));
    ?>
</div>

<!-- 添加赛事模态框 -->
<div class="modal" id="addEventModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">添加赛事</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="events.php" method="post">
            <input type="hidden" name="action" value="add_event">
            <div class="modal-body">
                <div class="form-group">
                    <label for="event_name">赛事名称</label>
                    <input type="text" id="event_name" name="event_name" class="form-control" required>
                    <small>例如：初二女子100米预赛</small>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="participant_count">参赛人数</label>
                            <input type="number" id="participant_count" name="participant_count" class="form-control" min="1" required>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="group_count">组数</label>
                            <input type="number" id="group_count" name="group_count" class="form-control" min="1" value="1" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-col">
                        <div class="form-group">
                            <label for="event_day">比赛日期（第几天）</label>
                            <select id="event_day" name="event_day" class="form-control" required>
                                <?php for ($i = 1; $i <= $maxDays; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo "第{$i}天"; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-col">
                        <div class="form-group">
                            <label for="event_time">比赛时间</label>
                            <input type="time" id="event_time" name="event_time" class="form-control" required>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-btn">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 批量添加赛事模态框 -->
<div class="modal" id="batchAddModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">批量添加赛事</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="events.php" method="post">
            <input type="hidden" name="action" value="batch_add">
            <div class="modal-body">
                <div class="form-group">
                    <label for="batch_data">赛事数据</label>
                    <textarea id="batch_data" name="batch_data" class="form-control" rows="10" required placeholder="格式：比赛项目 人数 组数 比赛时间 第几天&#10;例如：初二女子100米预赛 28 4 9:00 1"></textarea>
                </div>
                <div class="alert alert-info">
                    <strong>格式说明：</strong>
                    <p>每行一个赛事，格式为：比赛项目 人数 组数 比赛时间 [第几天]</p>
                    <p>例如：初二女子100米预赛 28 4 9:00 1</p>
                    <p>时间只需填写时分（如 9:00）</p>
                    <p>最后一个参数是比赛第几天，可选，默认为第1天</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary close-btn">取消</button>
                <button type="submit" class="btn btn-primary">批量添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 修改状态模态框 -->
<div class="modal" id="changeStatusModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">修改赛事状态</h3>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <p>请选择赛事 <strong id="eventName"></strong> 的新状态:</p>
            <div class="form-group">
                <div class="btn-group" style="display: flex; gap: 10px; margin-top: 20px;">
                    <a href="#" class="btn btn-secondary change-status-btn" data-status="未开始">未开始</a>
                    <a href="#" class="btn btn-secondary change-status-btn" data-status="进行中">进行中</a>
                    <a href="#" class="btn btn-secondary change-status-btn" data-status="已结束">已结束</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// 显示修改状态模态框
function showChangeStatusModal(eventId, eventName) {
    document.getElementById('eventName').textContent = eventName;
    
    // 更新所有状态按钮的链接
    const buttons = document.querySelectorAll('.change-status-btn');
    buttons.forEach(function(button) {
        const status = button.getAttribute('data-status');
        button.href = 'events.php?action=update_status&id=' + eventId + '&status=' + encodeURIComponent(status);
    });
    
    // 显示模态框
    document.getElementById('changeStatusModal').classList.add('show');
}
</script>

<?php
// 包含底部模板
include('templates/footer.php');
?> 