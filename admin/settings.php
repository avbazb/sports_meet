<?php
/**
 * 运动会设置页面
 */
require_once 'auth.php';

// 页面标题
$pageTitle = '运动会设置';

// 消息提示
$alertMessage = '';
$alertType = '';

// 获取当前设置
$settingsQuery = "SELECT * FROM sports_settings WHERE is_active = 1 LIMIT 1";
$settingsResult = query($settingsQuery);
$settings = null;

if ($settingsResult && $settingsResult->num_rows > 0) {
    $settings = $settingsResult->fetch_assoc();
}

// 处理更新设置请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sportsName = isset($_POST['sports_name']) ? trim($_POST['sports_name']) : '';
    $startDate = isset($_POST['start_date']) ? trim($_POST['start_date']) : '';
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 2;
    $currentDay = isset($_POST['current_day']) ? (int)$_POST['current_day'] : 1;
    
    if (empty($sportsName) || empty($startDate) || $days <= 0) {
        $alertMessage = '请填写所有必填字段';
        $alertType = 'danger';
    } else {
        if ($settings) {
            // 更新设置
            $updateSql = "UPDATE sports_settings SET 
                sports_name = ?, 
                start_date = ?, 
                days = ?, 
                current_day = ?, 
                updated_at = CURRENT_TIMESTAMP
                WHERE setting_id = ?";
            $updateStmt = prepareAndExecute($updateSql, 'ssiis', [
                $sportsName,
                $startDate,
                $days,
                $currentDay,
                $settings['setting_id']
            ]);
            
            if ($updateStmt) {
                $alertMessage = '设置更新成功';
                $alertType = 'success';
                
                // 重新获取设置
                $settingsResult = query($settingsQuery);
                if ($settingsResult && $settingsResult->num_rows > 0) {
                    $settings = $settingsResult->fetch_assoc();
                }
            } else {
                $alertMessage = '设置更新失败';
                $alertType = 'danger';
            }
        } else {
            // 创建新设置
            $insertSql = "INSERT INTO sports_settings 
                (sports_name, start_date, days, current_day, is_active) 
                VALUES (?, ?, ?, ?, 1)";
            $insertStmt = prepareAndExecute($insertSql, 'ssii', [
                $sportsName,
                $startDate,
                $days,
                $currentDay
            ]);
            
            if ($insertStmt) {
                $alertMessage = '设置创建成功';
                $alertType = 'success';
                
                // 获取新设置
                $settingsResult = query($settingsQuery);
                if ($settingsResult && $settingsResult->num_rows > 0) {
                    $settings = $settingsResult->fetch_assoc();
                }
            } else {
                $alertMessage = '设置创建失败';
                $alertType = 'danger';
            }
        }
        
        // 更新比赛状态（基于新的日期设置）
        updateEventStatus();
    }
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>运动会设置</h2>
    </div>
    
    <form action="settings.php" method="post" class="settings-form">
        <div class="form-group">
            <label for="sports_name">运动会名称</label>
            <input type="text" id="sports_name" name="sports_name" class="form-control" value="<?php echo $settings ? h($settings['sports_name']) : ''; ?>" required>
        </div>
        
        <div class="form-row">
            <div class="form-col">
                <div class="form-group">
                    <label for="start_date">开始日期</label>
                    <input type="date" id="start_date" name="start_date" class="form-control" value="<?php echo $settings ? $settings['start_date'] : date('Y-m-d'); ?>" required>
                    <small>运动会的第一天日期</small>
                </div>
            </div>
            
            <div class="form-col">
                <div class="form-group">
                    <label for="days">持续天数</label>
                    <input type="number" id="days" name="days" class="form-control" min="1" max="10" value="<?php echo $settings ? $settings['days'] : 2; ?>" required>
                    <small>运动会总共持续的天数</small>
                </div>
            </div>
        </div>
        
        <div class="form-group">
            <label for="current_day">当前日期（第几天）</label>
            <select id="current_day" name="current_day" class="form-control" required>
                <?php for ($i = 1; $i <= ($settings ? $settings['days'] : 2); $i++): ?>
                    <option value="<?php echo $i; ?>" <?php echo $settings && $settings['current_day'] == $i ? 'selected' : ''; ?>>
                        第<?php echo $i; ?>天
                    </option>
                <?php endfor; ?>
            </select>
            <small>根据当前进行的天数设置，会自动更新比赛状态</small>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </form>
    
    <?php if ($settings): ?>
    <div class="settings-summary" style="margin-top: 30px; padding: 20px; background-color: #f5f7ff; border-radius: 8px;">
        <h3>当前运动会信息</h3>
        <p><strong>运动会名称：</strong><?php echo h($settings['sports_name']); ?></p>
        <p><strong>开始日期：</strong><?php echo date('Y年m月d日', strtotime($settings['start_date'])); ?></p>
        <p><strong>持续天数：</strong><?php echo $settings['days']; ?>天</p>
        <p><strong>当前日期：</strong>第<?php echo $settings['current_day']; ?>天</p>
        
        <?php
        // 计算各天的日期
        $startDate = new DateTime($settings['start_date']);
        for ($i = 1; $i <= $settings['days']; $i++) {
            $date = clone $startDate;
            $date->modify('+' . ($i - 1) . ' days');
            
            // 确定当前日期的状态
            $status = '';
            if ($i < $settings['current_day']) {
                $status = '（已结束）';
                $statusClass = 'status-finished';
            } else if ($i == $settings['current_day']) {
                $status = '（进行中）';
                $statusClass = 'status-ongoing';
            } else {
                $status = '（未开始）';
                $statusClass = 'status-waiting';
            }
            
            echo '<p>第' . $i . '天日期：' . $date->format('Y年m月d日') . ' <span class="status-badge ' . $statusClass . '">' . $status . '</span></p>';
        }
        ?>
        
        <div style="margin-top: 20px;">
            <h4>状态自动更新逻辑</h4>
            <ul>
                <li>当前日期之前的比赛：状态设为 <span class="status-badge status-finished">已结束</span></li>
                <li>当前日期的比赛：状态设为 <span class="status-badge status-ongoing">进行中</span></li>
                <li>当前日期之后的比赛：状态设为 <span class="status-badge status-waiting">未开始</span></li>
                <li>比赛开始前20分钟：状态自动变为 <span class="status-badge status-checkin">检录中</span></li>
                <li>比赛开始时间到：状态自动变为 <span class="status-badge status-ongoing">比赛中</span></li>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// 当天数变化时，更新当前日期选择器的选项
document.getElementById('days').addEventListener('change', function() {
    const daysValue = parseInt(this.value);
    const currentDaySelect = document.getElementById('current_day');
    const currentSelectedValue = currentDaySelect.value;
    
    // 清空现有选项
    currentDaySelect.innerHTML = '';
    
    // 添加新选项
    for (let i = 1; i <= daysValue; i++) {
        const option = document.createElement('option');
        option.value = i;
        option.textContent = `第${i}天`;
        
        // 保持之前选中的值，如果在范围内
        if (i == currentSelectedValue && i <= daysValue) {
            option.selected = true;
        }
        
        currentDaySelect.appendChild(option);
    }
});
</script>

<?php
// 包含底部模板
include('templates/footer.php');
?> 