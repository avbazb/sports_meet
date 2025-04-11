<?php
/**
 * 参赛人员管理页面
 */
require_once 'auth.php';

// 页面标题
$pageTitle = '参赛人员管理';

// 消息提示
$alertMessage = '';
$alertType = '';

// 获取班级列表
$classes = [];
$classesSql = "SELECT * FROM classes ORDER BY grade, class_name";
$classesResult = query($classesSql);

if ($classesResult && $classesResult->num_rows > 0) {
    while ($row = $classesResult->fetch_assoc()) {
        $classes[] = $row;
    }
}

// 处理添加班级请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_class') {
    $className = isset($_POST['class_name']) ? trim($_POST['class_name']) : '';
    $grade = isset($_POST['grade']) ? trim($_POST['grade']) : '';
    
    if (empty($className) || empty($grade)) {
        $alertMessage = '请填写班级名称和年级';
        $alertType = 'danger';
    } else {
        // 检查班级是否已存在
        $checkSql = "SELECT COUNT(*) as count FROM classes WHERE class_name = ? AND grade = ?";
        $checkStmt = prepareAndExecute($checkSql, 'ss', [$className, $grade]);
        
        if ($checkStmt) {
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            
            if ($row['count'] > 0) {
                $alertMessage = '该班级已存在';
                $alertType = 'danger';
            } else {
                // 添加班级
                $insertSql = "INSERT INTO classes (class_name, grade) VALUES (?, ?)";
                $insertStmt = prepareAndExecute($insertSql, 'ss', [$className, $grade]);
                
                if ($insertStmt) {
                    $alertMessage = '班级添加成功';
                    $alertType = 'success';
                    
                    // 重新获取班级列表
                    $classesResult = query($classesSql);
                    $classes = [];
                    
                    if ($classesResult && $classesResult->num_rows > 0) {
                        while ($row = $classesResult->fetch_assoc()) {
                            $classes[] = $row;
                        }
                    }
                } else {
                    $alertMessage = '班级添加失败';
                    $alertType = 'danger';
                }
            }
        }
    }
}

// 处理添加参赛人员请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_participant') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    
    if (empty($name) || empty($gender) || $classId <= 0) {
        $alertMessage = '请填写所有必填字段';
        $alertType = 'danger';
    } else {
        // 添加参赛人员
        $insertSql = "INSERT INTO participants (name, gender, class_id) VALUES (?, ?, ?)";
        $insertStmt = prepareAndExecute($insertSql, 'ssi', [$name, $gender, $classId]);
        
        if ($insertStmt) {
            $alertMessage = '参赛人员添加成功';
            $alertType = 'success';
        } else {
            $alertMessage = '参赛人员添加失败';
            $alertType = 'danger';
        }
    }
}

// 处理编辑参赛人员请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_participant') {
    $participantId = isset($_POST['participant_id']) ? (int)$_POST['participant_id'] : 0;
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
    $classId = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    
    if ($participantId <= 0 || empty($name) || empty($gender) || $classId <= 0) {
        $alertMessage = '请填写所有必填字段';
        $alertType = 'danger';
    } else {
        // 更新参赛人员
        $updateSql = "UPDATE participants SET name = ?, gender = ?, class_id = ? WHERE participant_id = ?";
        $updateStmt = prepareAndExecute($updateSql, 'ssii', [$name, $gender, $classId, $participantId]);
        
        if ($updateStmt) {
            $alertMessage = '参赛人员更新成功';
            $alertType = 'success';
        } else {
            $alertMessage = '参赛人员更新失败';
            $alertType = 'danger';
        }
    }
}

// 处理删除参赛人员请求
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $participantId = (int)$_GET['id'];
    
    // 检查是否有关联的比赛
    $checkSql = "SELECT COUNT(*) as count FROM event_participants WHERE participant_id = ?";
    $checkStmt = prepareAndExecute($checkSql, 'i', [$participantId]);
    
    if ($checkStmt) {
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $alertMessage = '无法删除已参赛的运动员';
            $alertType = 'danger';
        } else {
            // 删除参赛人员
            $deleteSql = "DELETE FROM participants WHERE participant_id = ?";
            $deleteStmt = prepareAndExecute($deleteSql, 'i', [$participantId]);
            
            if ($deleteStmt) {
                $alertMessage = '参赛人员删除成功';
                $alertType = 'success';
            } else {
                $alertMessage = '参赛人员删除失败';
                $alertType = 'danger';
            }
        }
    }
}

// 处理批量添加参赛人员请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'batch_add') {
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
                        
                        // 添加参赛人员
                        $insertSql = "INSERT INTO participants (name, gender, class_id) VALUES (?, ?, ?)";
                        $insertStmt = prepareAndExecute($insertSql, 'ssi', [$name, $gender, $classId]);
                        
                        if ($insertStmt) {
                            $successCount++;
                        } else {
                            $failCount++;
                        }
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
                                
                                // 添加参赛人员
                                $insertSql = "INSERT INTO participants (name, gender, class_id) VALUES (?, ?, ?)";
                                $insertStmt = prepareAndExecute($insertSql, 'ssi', [$name, $gender, $classId]);
                                
                                if ($insertStmt) {
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
                    }
                } else {
                    $failCount++;
                }
            } else {
                $failCount++;
            }
        }
        
        if ($successCount > 0) {
            $alertMessage = "成功添加 {$successCount} 名参赛人员" . ($failCount > 0 ? "，{$failCount} 名失败" : "");
            $alertType = $failCount > 0 ? 'warning' : 'success';
        } else {
            $alertMessage = '所有参赛人员添加失败';
            $alertType = 'danger';
        }
    }
}

// 分页和搜索
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 搜索条件
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterClass = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$filterGender = isset($_GET['gender']) ? $_GET['gender'] : '';

// 构建查询条件
$whereConditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $whereConditions[] = "p.name LIKE ?";
    $params[] = "%{$search}%";
    $types .= 's';
}

if ($filterClass > 0) {
    $whereConditions[] = "p.class_id = ?";
    $params[] = $filterClass;
    $types .= 'i';
}

if (!empty($filterGender)) {
    $whereConditions[] = "p.gender = ?";
    $params[] = $filterGender;
    $types .= 's';
}

$whereClause = empty($whereConditions) ? '' : " WHERE " . implode(' AND ', $whereConditions);

// 获取参赛人员总数
$countSql = "SELECT COUNT(*) as total FROM participants p" . $whereClause;
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

// 获取参赛人员列表
$sql = "SELECT p.*, c.class_name, c.grade 
        FROM participants p 
        LEFT JOIN classes c ON p.class_id = c.class_id" 
        . $whereClause . 
        " ORDER BY p.participant_id DESC LIMIT ? OFFSET ?";

$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$participants = [];
$stmt = prepareAndExecute($sql, $types, $params);

if ($stmt) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $participants[] = $row;
    }
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>参赛人员管理</h2>
        <div>
            <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="addParticipantModal">添加参赛人员</button>
            <button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="batchAddModal">批量添加</button>
            <button class="btn btn-secondary btn-sm" data-toggle="modal" data-target="addClassModal">添加班级</button>
        </div>
    </div>
    
    <!-- 搜索和筛选 -->
    <div class="search-form">
        <form action="" method="get" class="search-input">
            <input type="text" name="search" placeholder="搜索姓名..." value="<?php echo h($search); ?>">
            
            <select name="class_id" class="form-control" style="width: auto; flex: 0 0 200px;">
                <option value="0">所有班级</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['class_id']; ?>" <?php echo $filterClass == $class['class_id'] ? 'selected' : ''; ?>>
                        <?php echo h($class['grade'] . ' ' . $class['class_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <select name="gender" class="form-control" style="width: auto; flex: 0 0 100px;">
                <option value="">所有性别</option>
                <option value="男" <?php echo $filterGender === '男' ? 'selected' : ''; ?>>男</option>
                <option value="女" <?php echo $filterGender === '女' ? 'selected' : ''; ?>>女</option>
            </select>
            
            <button type="submit" class="btn btn-primary">搜索</button>
            
            <?php if (!empty($search) || $filterClass > 0 || !empty($filterGender)): ?>
                <a href="participants.php" class="btn btn-secondary">清除筛选</a>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- 参赛人员列表 -->
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>姓名</th>
                <th>性别</th>
                <th>班级</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($participants)): ?>
                <tr>
                    <td colspan="5" class="text-center">暂无参赛人员数据</td>
                </tr>
            <?php else: ?>
                <?php foreach ($participants as $participant): ?>
                    <tr>
                        <td><?php echo h($participant['participant_id']); ?></td>
                        <td><?php echo h($participant['name']); ?></td>
                        <td><?php echo h($participant['gender']); ?></td>
                        <td><?php echo h($participant['grade'] . ' ' . $participant['class_name']); ?></td>
                        <td>
                            <button class="btn btn-secondary btn-sm edit-participant-btn" 
                                    data-id="<?php echo $participant['participant_id']; ?>"
                                    data-name="<?php echo h($participant['name']); ?>"
                                    data-gender="<?php echo h($participant['gender']); ?>"
                                    data-class-id="<?php echo $participant['class_id']; ?>">编辑</button>
                            <a href="?action=delete&id=<?php echo $participant['participant_id']; ?>" 
                               class="btn btn-danger btn-sm" 
                               data-confirm="确定要删除这名参赛人员吗？">删除</a>
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
    if ($filterClass > 0) $queryParams[] = 'class_id=' . $filterClass;
    if (!empty($filterGender)) $queryParams[] = 'gender=' . urlencode($filterGender);
    
    $queryString = empty($queryParams) ? '' : '?' . implode('&', $queryParams) . '&';
    echo generatePagination($total, $page, $perPage, 'participants.php' . $queryString);
    ?>
</div>

<!-- 添加参赛人员模态框 -->
<div class="modal" id="addParticipantModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">添加参赛人员</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="participants.php" method="post">
            <input type="hidden" name="action" value="add_participant">
            <div class="modal-body">
                <div class="form-group">
                    <label for="name">姓名</label>
                    <input type="text" id="name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="gender">性别</label>
                    <select id="gender" name="gender" class="form-control" required>
                        <option value="">请选择性别</option>
                        <option value="男">男</option>
                        <option value="女">女</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="class_id">班级</label>
                    <select id="class_id" name="class_id" class="form-control" required>
                        <option value="">请选择班级</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo h($class['grade'] . ' ' . $class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">添加</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑参赛人员模态框 -->
<div class="modal" id="editParticipantModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">编辑参赛人员</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="participants.php" method="post">
            <input type="hidden" name="action" value="edit_participant">
            <input type="hidden" name="participant_id" id="edit_participant_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_name">姓名</label>
                    <input type="text" id="edit_name" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_gender">性别</label>
                    <select id="edit_gender" name="gender" class="form-control" required>
                        <option value="">请选择性别</option>
                        <option value="男">男</option>
                        <option value="女">女</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_class_id">班级</label>
                    <select id="edit_class_id" name="class_id" class="form-control" required>
                        <option value="">请选择班级</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['class_id']; ?>">
                                <?php echo h($class['grade'] . ' ' . $class['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">取消</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 添加班级模态框 -->
<div class="modal" id="addClassModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">添加班级</h3>
            <span class="modal-close">&times;</span>
        </div>
        <form action="participants.php" method="post">
            <input type="hidden" name="action" value="add_class">
            <div class="modal-body">
                <div class="form-group">
                    <label for="grade">年级</label>
                    <input type="text" id="grade" name="grade" class="form-control" required placeholder="例如：初一、初二、高一">
                </div>
                
                <div class="form-group">
                    <label for="class_name">班级名称</label>
                    <input type="text" id="class_name" name="class_name" class="form-control" required placeholder="例如：1班">
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
        <form action="participants.php" method="post">
            <input type="hidden" name="action" value="batch_add">
            <div class="modal-body">
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
                <button type="submit" class="btn btn-primary">批量添加</button>
            </div>
        </form>
    </div>
</div>

<?php
// 页面特定的脚本
$pageScript = <<<SCRIPT
// 初始化编辑按钮
document.querySelectorAll('.edit-participant-btn').forEach(function(button) {
    button.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        const name = this.getAttribute('data-name');
        const gender = this.getAttribute('data-gender');
        const classId = this.getAttribute('data-class-id');
        
        // 填充表单
        document.getElementById('edit_participant_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_gender').value = gender;
        document.getElementById('edit_class_id').value = classId;
        
        // 显示模态框
        openModal(document.getElementById('editParticipantModal'));
    });
});
SCRIPT;

// 包含底部模板
include('templates/footer.php');
?> 