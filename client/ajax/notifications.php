<?php
/**
 * AJAX endpoint for client notifications
 * Handles real-time notification updates
 */

require_once dirname(__DIR__, 2) . '/includes/init.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if client is logged in
if (!isset($_SESSION['client_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$clientId = $_SESSION['client_id'];
$action = $_GET['action'] ?? '';

header('Content-Type: application/json');

switch ($action) {
    case 'get_unread_count':
        // Get unread notifications count
        $unreadCount = db()->fetch("
            SELECT COUNT(*) as count FROM client_notifications 
            WHERE client_id = ? AND is_read = 0
        ", [$clientId])['count'] ?? 0;
        
        echo json_encode(['count' => $unreadCount]);
        break;
        
    case 'get_recent':
        // Get recent notifications
        $notifications = db()->fetchAll("
            SELECT * FROM client_notifications 
            WHERE client_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ", [$clientId]);
        
        foreach ($notifications as &$note) {
            $note['time_ago'] = timeAgo($note['created_at']);
            $note['created_at_formatted'] = date('M d, Y h:i A', strtotime($note['created_at']));
        }
        
        echo json_encode(['notifications' => $notifications]);
        break;
        
    case 'mark_read':
        // Mark a notification as read
        $notificationId = (int)($_POST['id'] ?? 0);
        
        if ($notificationId > 0) {
            db()->update('client_notifications', 
                ['is_read' => 1], 
                'id = ? AND client_id = ?', 
                [$notificationId, $clientId]
            );
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid notification ID']);
        }
        break;
        
    case 'mark_all_read':
        // Mark all notifications as read
        db()->update('client_notifications', 
            ['is_read' => 1], 
            'client_id = ? AND is_read = 0', 
            [$clientId]
        );
        echo json_encode(['success' => true]);
        break;
        
    case 'get_unread_messages':
        // Get unread messages count
        $unreadMessages = db()->fetch("
            SELECT COUNT(*) as count FROM client_messages 
            WHERE client_id = ? AND sender = 'admin' AND status = 'unread'
        ", [$clientId])['count'] ?? 0;
        
        echo json_encode(['count' => $unreadMessages]);
        break;
        
    case 'get_recent_messages':
        // Get recent messages
        $messages = db()->fetchAll("
            SELECT id, subject, LEFT(message, 100) as preview, created_at,
                   CASE WHEN sender = 'admin' THEN 'Support Team' ELSE 'You' END as sender_name
            FROM client_messages 
            WHERE client_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ", [$clientId]);
        
        foreach ($messages as &$msg) {
            $msg['time_ago'] = timeAgo($msg['created_at']);
        }
        
        echo json_encode(['messages' => $messages]);
        break;
        
    case 'check_updates':
        // Comprehensive update check for dashboard
        $lastCheck = $_GET['last_check'] ?? 0;
        
        $newNotifications = db()->fetch("
            SELECT COUNT(*) as count FROM client_notifications 
            WHERE client_id = ? AND created_at > FROM_UNIXTIME(?)
        ", [$clientId, $lastCheck])['count'] ?? 0;
        
        $newMessages = db()->fetch("
            SELECT COUNT(*) as count FROM client_messages 
            WHERE client_id = ? AND sender = 'admin' AND status = 'unread' AND created_at > FROM_UNIXTIME(?)
        ", [$clientId, $lastCheck])['count'] ?? 0;
        
        $newInvoices = db()->fetch("
            SELECT COUNT(*) as count FROM project_invoices 
            WHERE client_id = ? AND status = 'pending' AND created_at > FROM_UNIXTIME(?)
        ", [$clientId, $lastCheck])['count'] ?? 0;
        
        echo json_encode([
            'has_updates' => ($newNotifications + $newMessages + $newInvoices) > 0,
            'new_notifications' => $newNotifications,
            'new_messages' => $newMessages,
            'new_invoices' => $newInvoices,
            'timestamp' => time()
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Invalid action']);
}