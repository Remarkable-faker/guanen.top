<?php
/**
 * 抽奖数据导出工具
 * 
 * 职责：
 * 1. 提供抽奖记录、奖品统计、系统汇总的 CSV/Excel 导出功能。
 * 2. 规范化改造：使用核心库，加强权限验证，确保 PHP 5.6 兼容性。
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/core/session.php';
require_once dirname(__DIR__) . '/core/db.php';

// 权限检查：必须是管理员
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: user_login.php");
    exit();
}

$conn = db_connect();

// 获取导出参数
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$export_type = isset($_GET['type']) ? $_GET['type'] : 'records';

// 根据导出类型执行相应逻辑
switch ($export_type) {
    case 'records':
        exportRecords($conn, $format);
        break;
    case 'prizes':
        exportPrizes($conn, $format);
        break;
    case 'summary':
        exportSummary($conn, $format);
        break;
    default:
        exportRecords($conn, $format);
}

$conn->close();

/**
 * 导出抽奖记录
 */
function exportRecords($conn, $format) {
    // 基础查询
    $sql = "SELECT lr.id, lr.user_id, u.username, lr.prize_name, lr.prize_value, 
                   CASE WHEN lr.is_win = 1 THEN '是' ELSE '否' END as is_win,
                   lr.draw_time
            FROM lottery_records lr 
            LEFT JOIN users u ON lr.user_id = u.id 
            WHERE lr.user_id != 0
            ORDER BY lr.draw_time DESC";
    
    $result = $conn->query($sql);
    
    $data = array();
    $headers = array('记录ID', '用户ID', '用户名', '奖品名称', '奖品描述', '是否中奖', '抽奖时间');
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = array(
                $row['id'],
                $row['user_id'],
                isset($row['username']) ? $row['username'] : 'N/A',
                $row['prize_name'],
                $row['prize_value'],
                $row['is_win'],
                $row['draw_time']
            );
        }
    }
    
    $filename = '抽奖记录_' . date('Ymd_His');
    doExport($data, $headers, $filename, $format);
}

/**
 * 导出奖品统计
 */
function exportPrizes($conn, $format) {
    $sql = "SELECT prize_name, prize_value,
                   COUNT(*) as total_count,
                   SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as win_count
            FROM lottery_records 
            WHERE user_id != 0
            GROUP BY prize_name, prize_value
            ORDER BY total_count DESC";
    
    $result = $conn->query($sql);
    
    $data = array();
    $headers = array('奖品名称', '奖品描述', '出现次数', '中奖次数', '中奖率(%)');
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $total = (int)$row['total_count'];
            $wins = (int)$row['win_count'];
            $rate = $total > 0 ? round(($wins / $total) * 100, 2) : 0;
            
            $data[] = array(
                $row['prize_name'],
                $row['prize_value'],
                $total,
                $wins,
                $rate
            );
        }
    }
    
    $filename = '奖品统计_' . date('Ymd_His');
    doExport($data, $headers, $filename, $format);
}

/**
 * 导出汇总统计
 */
function exportSummary($conn, $format) {
    $today = date('Y-m-d');
    
    // 获取基础统计
    $stats = array();
    
    $res = $conn->query("SELECT COUNT(*) as count FROM lottery_records WHERE user_id != 0");
    $stats['总抽奖次数'] = $res ? $res->fetch_assoc()['count'] : 0;
    
    $res = $conn->query("SELECT COUNT(*) as count FROM lottery_records WHERE DATE(draw_time) = '$today' AND user_id != 0");
    $stats['今日抽奖次数'] = $res ? $res->fetch_assoc()['count'] : 0;
    
    $res = $conn->query("SELECT SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as count FROM lottery_records WHERE user_id != 0");
    $stats['总中奖次数'] = $res ? $res->fetch_assoc()['count'] : 0;
    
    $res = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM lottery_records WHERE user_id != 0");
    $stats['参与用户数'] = $res ? $res->fetch_assoc()['count'] : 0;
    
    // 按日期统计
    $date_stats = $conn->query("
        SELECT DATE(draw_time) as date,
               COUNT(*) as total,
               SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as wins
        FROM lottery_records
        WHERE user_id != 0
        GROUP BY DATE(draw_time)
        ORDER BY date DESC
        LIMIT 30
    ");
    
    $data = array();
    $headers = array('统计项', '数值');
    
    foreach ($stats as $key => $value) {
        $data[] = array($key, $value);
    }
    
    $data[] = array('', ''); // 分隔行
    $data[] = array('最近30天趋势', '');
    $data[] = array('日期', '总次数', '中奖数', '中奖率(%)');
    
    if ($date_stats) {
        while ($row = $date_stats->fetch_assoc()) {
            $total = (int)$row['total'];
            $wins = (int)$row['wins'];
            $rate = $total > 0 ? round(($wins / $total) * 100, 2) : 0;
            
            $data[] = array(
                $row['date'],
                $total,
                $wins,
                $rate
            );
        }
    }
    
    $filename = '抽奖汇总_' . date('Ymd_His');
    doExport($data, $headers, $filename, $format);
}

/**
 * 执行导出
 */
function doExport($data, $headers, $filename, $format) {
    if ($format == 'excel') {
        exportExcel($data, $headers, $filename);
    } else {
        exportCSV($data, $headers, $filename);
    }
}

/**
 * 导出为 CSV
 */
function exportCSV($data, $headers, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // 添加 BOM 头 (UTF-8)
    fwrite($output, "\xEF\xBB\xBF");
    
    // 写入表头
    fputcsv($output, $headers);
    
    // 写入数据
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * 导出为 Excel (HTML 兼容模式)
 */
function exportExcel($data, $headers, $filename) {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>table{border-collapse:collapse;} th,td{border:1px solid #ccc;padding:5px;}</style></head><body><table>';
    
    // 表头
    echo '<tr>';
    foreach ($headers as $h) {
        echo '<th style="background:#eee;">' . htmlspecialchars($h) . '</th>';
    }
    echo '</tr>';
    
    // 数据
    foreach ($data as $row) {
        echo '<tr>';
        foreach ($row as $cell) {
            echo '<td>' . htmlspecialchars($cell) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table></body></html>';
    exit;
}
?>
