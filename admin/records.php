<?php
/**
 * 破纪录管理页面
 */
require_once 'auth.php';

// 页面标题
$pageTitle = '破纪录管理';

// 消息提示
$alertMessage = '';
$alertType = '';

// 处理添加纪录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_record') {
    $eventName = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
    $recordScore = isset($_POST['record_score']) ? trim($_POST['record_score']) : '';
    $participantName = isset($_POST['participant_name']) ? trim($_POST['participant_name']) : '';
    $className = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
    $recordDate = isset($_POST['record_date']) ? trim($_POST['record_date']) : '';
    
    if (empty($eventName) || empty($recordScore)) {
        $alertMessage = '请填写项目名称和纪录成绩';
        $alertType = 'danger';
    } else {
        $sql = "INSERT INTO records (event_name, record_score, participant_name, class_name, record_date) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = prepareAndExecute($sql, 'sssss', [$eventName, $recordScore, $participantName, $className, $recordDate]);
        
        if ($stmt) {
            $alertMessage = '纪录添加成功';
            $alertType = 'success';
        } else {
            $alertMessage = '纪录添加失败';
            $alertType = 'danger';
        }
    }
}

// 处理编辑纪录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_record') {
    $recordId = isset($_POST['record_id']) ? (int)$_POST['record_id'] : 0;
    $eventName = isset($_POST['event_name']) ? trim($_POST['event_name']) : '';
    $recordScore = isset($_POST['record_score']) ? trim($_POST['record_score']) : '';
    $participantName = isset($_POST['participant_name']) ? trim($_POST['participant_name']) : '';
    $className = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
    $recordDate = isset($_POST['record_date']) ? trim($_POST['record_date']) : '';
    
    if ($recordId <= 0 || empty($eventName) || empty($recordScore)) {
        $alertMessage = '请填写项目名称和纪录成绩';
        $alertType = 'danger';
    } else {
        $sql = "UPDATE records SET event_name = ?, record_score = ?, participant_name = ?, class_name = ?, record_date = ? WHERE record_id = ?";
        $stmt = prepareAndExecute($sql, 'sssssi', [$eventName, $recordScore, $participantName, $className, $recordDate, $recordId]);
        
        if ($stmt) {
            $alertMessage = '纪录更新成功';
            $alertType = 'success';
        } else {
            $alertMessage = '纪录更新失败';
            $alertType = 'danger';
        }
    }
}

// 处理删除纪录请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $recordId = (int)$_GET['id'];
    
    $sql = "DELETE FROM records WHERE record_id = ?";
    $stmt = prepareAndExecute($sql, 'i', [$recordId]);
    
    if ($stmt) {
        $alertMessage = '纪录删除成功';
        $alertType = 'success';
    } else {
        $alertMessage = '纪录删除失败';
        $alertType = 'danger';
    }
}

// 分页功能
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 搜索条件
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// 构建查询条件
$whereClause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $whereClause = " WHERE event_name LIKE ? OR participant_name LIKE ?";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'ss';
}

// 获取记录总数
$countSql = "SELECT COUNT(*) as total FROM records" . $whereClause;
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

// 获取所有比赛项目记录
$sql = "SELECT * FROM records" . $whereClause . " ORDER BY event_name ASC, record_date DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$records = [];
$stmt = prepareAndExecute($sql, $types, $params);

if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>破纪录管理</h2>
        <div>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="addRecordModal">添加纪录</button>
        </div>
    </div>
    
    <!-- 搜索表单 -->
    <div class="search-form">
        <form action="" method="get" class="search-input">
            <input type="text" name="search" placeholder="搜索项目/人员名称..." value="<?php echo h($search); ?>">
            <button type="submit" class="btn btn-primary">搜索</button>
            
            <?php if (!empty($search)): ?>
                <a href="records.php" class="btn btn-secondary">清除筛选</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- 纪录列表 -->
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>比赛项目</th>
                <th>纪录成绩</th>
                <th>纪录保持者</th>
                <th>班级</th>
                <th>创造日期</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($records)): ?>
                <tr>
                    <td colspan="7" class="text-center">暂无纪录数据</td>
                </tr>
            <?php else: ?>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo h($record['record_id']); ?></td>
                        <td><?php echo h($record['event_name']); ?></td>
                        <td><?php echo h($record['record_score']); ?></td>
                        <td><?php echo h($record['participant_name']); ?></td>
                        <td><?php echo h($record['class_name']); ?></td>
                        <td><?php echo $record['record_date'] ? date('Y-m-d', strtotime($record['record_date'])) : ''; ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm edit-record-btn" 
                                    data-id="<?php echo $record['record_id']; ?>"
                                    data-event-name="<?php echo h($record['event_name']); ?>"
                                    data-record-score="<?php echo h($record['record_score']); ?>"
                                    data-participant-name="<?php echo h($record['participant_name']); ?>"
                                    data-class-name="<?php echo h($record['class_name']); ?>"
                                    data-record-date="<?php echo $record['record_date']; ?>">编辑</button>
                            <a href="?action=delete&id=<?php echo $record['record_id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               data-confirm="确定要删除这条纪录吗？">删除</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- 分页 -->
    <?php 
    $queryString = !empty($search) ? '?search=' . urlencode($search) . '&' : '?';
    echo generatePagination($total, $page, $perPage, 'records.php' . $queryString);
    ?>
</div>

<!-- 添加纪录模态框 -->
<div class="modal" id="addRecordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">添加纪录</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="records.php" method="post">
            <input type="hidden" name="action" value="add_record">
            <div class="modal-body">
                <div class="form-group">
                    <label for="event_name">比赛项目</label>
                    <input type="text" id="event_name" name="event_name" class="form-control" required>
                    <small>例如：100米、跳远</small>
                </div>
                
                <div class="form-group">
                    <label for="record_score">纪录成绩</label>
                    <input type="text" id="record_score" name="record_score" class="form-control" required>
                    <small>例如：10.5（秒）或 5.75（米）</small>
                </div>
                
                <div class="form-group">
                    <label for="participant_name">纪录保持者</label>
                    <input type="text" id="participant_name" name="participant_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="class_name">班级</label>
                    <input type="text" id="class_name" name="class_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="record_date">创造日期</label>
                    <input type="date" id="record_date" name="record_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑纪录模态框 -->
<div class="modal" id="editRecordModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">编辑纪录</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="records.php" method="post">
            <input type="hidden" name="action" value="edit_record">
            <input type="hidden" name="record_id" id="edit_record_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_event_name">比赛项目</label>
                    <input type="text" id="edit_event_name" name="event_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_record_score">纪录成绩</label>
                    <input type="text" id="edit_record_score" name="record_score" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_participant_name">纪录保持者</label>
                    <input type="text" id="edit_participant_name" name="participant_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="edit_class_name">班级</label>
                    <input type="text" id="edit_class_name" name="class_name" class="form-control">
                </div>
                
                <div class="form-group">
                    <label for="edit_record_date">创造日期</label>
                    <input type="date" id="edit_record_date" name="record_date" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<?php
// 页面特定的脚本
$pageScript = <<<SCRIPT
// 初始化编辑按钮
document.querySelectorAll('.edit-record-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const eventName = this.getAttribute('data-event-name');
        const recordScore = this.getAttribute('data-record-score');
        const participantName = this.getAttribute('data-participant-name');
        const className = this.getAttribute('data-class-name');
        const recordDate = this.getAttribute('data-record-date');
        
        // 填充表单
        document.getElementById('edit_record_id').value = id;
        document.getElementById('edit_event_name').value = eventName;
        document.getElementById('edit_record_score').value = recordScore;
        document.getElementById('edit_participant_name').value = participantName;
        document.getElementById('edit_class_name').value = className;
        document.getElementById('edit_record_date').value = recordDate ? recordDate.substring(0, 10) : '';
        
        // 显示模态框
        openModal(document.getElementById('editRecordModal'));
    });
});
SCRIPT;

// 包含底部模板
include('templates/footer.php');
?> 