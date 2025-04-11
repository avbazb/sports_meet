<?php
/**
 * AI报告相关API接口
 */
require_once '../includes/db.php';
require_once '../includes/functions.php';

// 设置返回类型为JSON
header('Content-Type: application/json');

// 处理跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// 获取AI生成的报告
apiGetAIReports();

/**
 * 获取AI生成的赛事报告
 */
function apiGetAIReports() {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    
    // 调用includes/functions.php中的函数
    $reports = getLatestAIReports($limit);
    
    $formattedReports = [];
    foreach ($reports as $report) {
        $formattedReports[] = [
            'report_id' => (int)$report['report_id'],
            'event_id' => (int)$report['event_id'],
            'event_name' => $report['event_name'],
            'content' => $report['content'],
            'created_at' => $report['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'message' => '获取成功',
        'reports' => $formattedReports
    ]);
} 