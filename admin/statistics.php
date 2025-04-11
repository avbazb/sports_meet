<?php
/**
 * 统计图表页面
 */
require_once 'auth.php';

// 页面标题
$pageTitle = '统计图表';

// 获取班级成绩数据
$classScoresSql = "SELECT c.class_id, c.class_name, c.grade, c.total_score, 
                   COUNT(DISTINCT r.result_id) as result_count 
                   FROM classes c
                   LEFT JOIN participants p ON c.class_id = p.class_id
                   LEFT JOIN results r ON p.participant_id = r.participant_id
                   GROUP BY c.class_id
                   ORDER BY c.grade ASC, c.class_name ASC";
$classScoresResult = query($classScoresSql);
$classScores = [];

if ($classScoresResult) {
    while ($row = $classScoresResult->fetch_assoc()) {
        $classScores[] = $row;
    }
}

// 获取比赛完成情况
$eventStatusSql = "SELECT status, COUNT(*) as count FROM events GROUP BY status";
$eventStatusResult = query($eventStatusSql);
$eventStatus = [
    '未开始' => 0,
    '检录中' => 0,
    '比赛中' => 0,
    '公布成绩' => 0,
    '已结束' => 0
];

if ($eventStatusResult) {
    while ($row = $eventStatusResult->fetch_assoc()) {
        $eventStatus[$row['status']] = (int)$row['count'];
    }
}

// 获取每天的比赛数量
$eventsByDaySql = "SELECT event_day, COUNT(*) as count FROM events WHERE parent_event_id IS NULL GROUP BY event_day ORDER BY event_day";
$eventsByDayResult = query($eventsByDaySql);
$eventsByDay = [];

if ($eventsByDayResult) {
    while ($row = $eventsByDayResult->fetch_assoc()) {
        $eventsByDay[$row['event_day']] = (int)$row['count'];
    }
}

// 获取记录数量
$recordsSql = "SELECT COUNT(*) as count FROM records";
$recordsResult = query($recordsSql);
$recordsCount = 0;

if ($recordsResult && $row = $recordsResult->fetch_assoc()) {
    $recordsCount = (int)$row['count'];
}

// 包含头部模板
include('templates/header.php');
?>

<div class="card">
    <div class="card-title">
        <h2>统计图表</h2>
    </div>
    
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="stats-card" style="background-color: #f5f7ff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);">
            <h3 style="margin-top: 0; color: #0071e3;">班级总数</h3>
            <p style="font-size: 24px; font-weight: 600;"><?php echo count($classScores); ?></p>
        </div>
        
        <div class="stats-card" style="background-color: #f5f7ff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);">
            <h3 style="margin-top: 0; color: #0071e3;">比赛项目数</h3>
            <p style="font-size: 24px; font-weight: 600;"><?php echo array_sum($eventsByDay); ?></p>
        </div>
        
        <div class="stats-card" style="background-color: #f5f7ff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);">
            <h3 style="margin-top: 0; color: #0071e3;">已完成比赛</h3>
            <p style="font-size: 24px; font-weight: 600;"><?php echo $eventStatus['已结束'] + $eventStatus['公布成绩']; ?></p>
        </div>
        
        <div class="stats-card" style="background-color: #f5f7ff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);">
            <h3 style="margin-top: 0; color: #0071e3;">历史纪录数</h3>
            <p style="font-size: 24px; font-weight: 600;"><?php echo $recordsCount; ?></p>
        </div>
    </div>
    
    <div class="chart-container" style="height: 400px; margin-bottom: 30px;">
        <h3>班级总分排名</h3>
        <canvas id="classScoreChart"></canvas>
    </div>
    
    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
        <div class="chart-container" style="flex: 1; min-width: 300px; height: 400px;">
            <h3>比赛状态分布</h3>
            <canvas id="eventStatusChart"></canvas>
        </div>
        
        <div class="chart-container" style="flex: 1; min-width: 300px; height: 400px;">
            <h3>每日比赛分布</h3>
            <canvas id="eventsByDayChart"></canvas>
        </div>
    </div>
    
    <div class="chart-container" style="height: 500px; margin-bottom: 30px;">
        <h3>班级成绩详情</h3>
        <canvas id="classDetailChart"></canvas>
    </div>
</div>

<script>
// 班级总分排名图表
const classScoreCtx = document.getElementById('classScoreChart').getContext('2d');
new Chart(classScoreCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($classScores, 'class_name')); ?>,
        datasets: [{
            label: '总分',
            data: <?php echo json_encode(array_column($classScores, 'total_score')); ?>,
            backgroundColor: 'rgba(0, 113, 227, 0.7)',
            borderColor: 'rgba(0, 113, 227, 1)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            title: {
                display: true,
                text: '班级总分排名'
            }
        }
    }
});

// 比赛状态分布图表
const eventStatusCtx = document.getElementById('eventStatusChart').getContext('2d');
new Chart(eventStatusCtx, {
    type: 'doughnut',
    data: {
        labels: Object.keys(<?php echo json_encode($eventStatus); ?>),
        datasets: [{
            data: Object.values(<?php echo json_encode($eventStatus); ?>),
            backgroundColor: [
                'rgba(0, 113, 227, 0.7)',   // 未开始
                'rgba(142, 68, 255, 0.7)',  // 检录中
                'rgba(255, 149, 0, 0.7)',   // 比赛中
                'rgba(88, 86, 214, 0.7)',   // 公布成绩
                'rgba(52, 199, 89, 0.7)'    // 已结束
            ],
            borderColor: [
                'rgba(0, 113, 227, 1)',
                'rgba(142, 68, 255, 1)',
                'rgba(255, 149, 0, 1)',
                'rgba(88, 86, 214, 1)',
                'rgba(52, 199, 89, 1)'
            ],
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
            },
            title: {
                display: true,
                text: '比赛状态分布'
            }
        }
    }
});

// 每日比赛分布图表
const eventsByDayCtx = document.getElementById('eventsByDayChart').getContext('2d');
new Chart(eventsByDayCtx, {
    type: 'bar',
    data: {
        labels: Object.keys(<?php echo json_encode($eventsByDay); ?>).map(day => `第${day}天`),
        datasets: [{
            label: '比赛数量',
            data: Object.values(<?php echo json_encode($eventsByDay); ?>),
            backgroundColor: 'rgba(255, 149, 0, 0.7)',
            borderColor: 'rgba(255, 149, 0, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            title: {
                display: true,
                text: '每日比赛分布'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// 班级成绩详情图表
const classDetailCtx = document.getElementById('classDetailChart').getContext('2d');
new Chart(classDetailCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($classScores, 'class_name')); ?>,
        datasets: [
            {
                label: '总分',
                data: <?php echo json_encode(array_column($classScores, 'total_score')); ?>,
                backgroundColor: 'rgba(0, 113, 227, 0.7)',
                borderColor: 'rgba(0, 113, 227, 1)',
                borderWidth: 1,
                yAxisID: 'y'
            },
            {
                label: '参赛项目数',
                data: <?php echo json_encode(array_column($classScores, 'result_count')); ?>,
                backgroundColor: 'rgba(52, 199, 89, 0.7)',
                borderColor: 'rgba(52, 199, 89, 1)',
                borderWidth: 1,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            title: {
                display: true,
                text: '班级成绩详情'
            }
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: '总分'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: '参赛项目数'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});
</script>

<?php
// 包含底部模板
include('templates/footer.php');
?> 