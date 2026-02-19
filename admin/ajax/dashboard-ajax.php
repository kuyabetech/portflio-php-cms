<?php
// admin/ajax/dashboard-ajax.php
// AJAX handler for dashboard operations

require_once dirname(__DIR__, 2) . '/includes/init.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'add_widget':
        $widgetId = (int)($_POST['widget_id'] ?? 0);
        $positionX = (int)($_POST['position_x'] ?? 0);
        $positionY = (int)($_POST['position_y'] ?? 0);
        $width = (int)($_POST['width'] ?? 2);
        $height = (int)($_POST['height'] ?? 1);
        
        if (!$widgetId) {
            echo json_encode(['success' => false, 'error' => 'Invalid widget ID']);
            exit;
        }
        
        // Check if widget exists
        $widget = db()->fetch("SELECT id FROM dashboard_widgets WHERE id = ?", [$widgetId]);
        if (!$widget) {
            echo json_encode(['success' => false, 'error' => 'Widget not found']);
            exit;
        }
        
        // Check if already added
        $existing = db()->fetch("SELECT id FROM user_dashboards WHERE user_id = ? AND widget_id = ?", 
            [$_SESSION['user_id'], $widgetId]);
        
        if ($existing) {
            echo json_encode(['success' => false, 'error' => 'Widget already added']);
            exit;
        }
        
        // Add widget
        $result = db()->insert('user_dashboards', [
            'user_id' => $_SESSION['user_id'],
            'widget_id' => $widgetId,
            'position_x' => $positionX,
            'position_y' => $positionY,
            'width' => $width,
            'height' => $height,
            'is_visible' => 1
        ]);
        
        if ($result) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add widget']);
        }
        break;
        
    case 'remove_widget':
        $widgetId = (int)($_POST['widget_id'] ?? 0);
        
        if (!$widgetId) {
            echo json_encode(['success' => false, 'error' => 'Invalid widget ID']);
            exit;
        }
        
        db()->delete('user_dashboards', 'user_id = ? AND widget_id = ?', 
            [$_SESSION['user_id'], $widgetId]);
        
        echo json_encode(['success' => true]);
        break;
        
    case 'save_layout':
        $input = json_decode(file_get_contents('php://input'), true);
        $layout = $input['layout'] ?? [];
        
        foreach ($layout as $item) {
            db()->update('user_dashboards', [
                'position_x' => $item['position_x'],
                'position_y' => $item['position_y'],
                'width' => $item['width'],
                'height' => $item['height']
            ], 'user_id = ? AND widget_id = ?', 
            [$_SESSION['user_id'], $item['widget_id']]);
        }
        
        echo json_encode(['success' => true]);
        break;
        
    case 'get_widget_data':
        $widgetId = (int)($_GET['widget_id'] ?? 0);
        $data = getWidgetData($widgetId);
        echo json_encode($data);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}

function getWidgetData($widgetId) {
    $widget = db()->fetch("SELECT * FROM dashboard_widgets WHERE id = ?", [$widgetId]);
    if (!$widget) return null;
    
    switch ($widget['widget_key']) {
        case 'stats_overview':
            return db()->fetch("
                SELECT 
                    (SELECT COUNT(*) FROM projects) as total_projects,
                    (SELECT COUNT(*) FROM clients) as total_clients,
                    (SELECT COUNT(*) FROM contact_messages WHERE is_read = 0) as unread_messages,
                    (SELECT COUNT(*) FROM project_tasks WHERE status != 'completed') as pending_tasks,
                    (SELECT SUM(total) FROM project_invoices WHERE status = 'paid') as total_revenue,
                    (SELECT SUM(balance_due) FROM project_invoices WHERE status != 'paid') as outstanding_revenue
            ");
            
        case 'recent_projects':
            return db()->fetchAll("
                SELECT p.*, c.company_name 
                FROM projects p
                LEFT JOIN clients c ON p.client_id = c.id
                ORDER BY p.created_at DESC
                LIMIT 5
            ");
            
        case 'recent_messages':
            return db()->fetchAll("
                SELECT * FROM contact_messages 
                ORDER BY created_at DESC 
                LIMIT 5
            ");
            
        case 'task_summary':
            return db()->fetch("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
                    SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high,
                    SUM(CASE WHEN priority = 'medium' THEN 1 ELSE 0 END) as medium,
                    SUM(CASE WHEN priority = 'low' THEN 1 ELSE 0 END) as low,
                    SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue
                FROM project_tasks
                WHERE status != 'completed'
            ");
            
        case 'system_health':
            $health = [
                'php_version' => phpversion(),
                'mysql_version' => db()->fetch("SELECT VERSION() as version")['version'],
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'upload_max_size' => ini_get('upload_max_filesize'),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'disk_free' => disk_free_space(ROOT_PATH),
                'disk_total' => disk_total_space(ROOT_PATH)
            ];
            
            $health['disk_used_percent'] = round((1 - $health['disk_free'] / $health['disk_total']) * 100, 2);
            $health['disk_free_formatted'] = formatBytes($health['disk_free']);
            $health['disk_total_formatted'] = formatBytes($health['disk_total']);
            
            return $health;
            
        case 'recent_activity':
            return db()->fetchAll("
                SELECT * FROM project_activity_log 
                ORDER BY created_at DESC 
                LIMIT 10
            ");
            
        default:
            return null;
    }
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>