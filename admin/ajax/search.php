<?php
// admin/ajax/search.php
// Global search handler

require_once dirname(__DIR__, 2) . '/includes/init.php';

if (!Auth::check()) {
    echo json_encode([]);
    exit;
}

$query = $_GET['q'] ?? '';
$query = '%' . $query . '%';

$results = [];

// Search projects
$results['projects'] = db()->fetchAll("
    SELECT id, title, category 
    FROM projects 
    WHERE title LIKE ? OR short_description LIKE ? OR technologies LIKE ?
    LIMIT 5
", [$query, $query, $query]) ?? [];

// Search clients
$results['clients'] = db()->fetchAll("
    SELECT id, company_name, contact_person, email 
    FROM clients 
    WHERE company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?
    LIMIT 5
", [$query, $query, $query]) ?? [];

// Search invoices
$results['invoices'] = db()->fetchAll("
    SELECT i.id, i.invoice_number, i.total, i.status, c.company_name 
    FROM project_invoices i
    JOIN clients c ON i.client_id = c.id
    WHERE i.invoice_number LIKE ? OR c.company_name LIKE ?
    LIMIT 5
", [$query, $query]) ?? [];

echo json_encode($results);
?>