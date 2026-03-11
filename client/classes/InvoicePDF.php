<?php
// admin/classes/InvoicePDF.php
// Professional PDF Invoice Generator

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePDF {
    private $invoice;
    private $items;
    private $company;
    private $client;
    
    public function __construct($invoice, $items, $company, $client) {
        $this->invoice = $invoice;
        $this->items = $items;
        $this->company = $company;
        $this->client = $client;
    }
    
    public function generate() {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');
        $options->set('isFontSubsettingEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->getHTML());
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf;
    }
    
    private function getHTML() {
        $invoice = $this->invoice;
        $items = $this->items;
        $company = $this->company;
        $client = $this->client;
        
        $status = ucfirst($invoice['status']);
        $statusColor = $this->getStatusColor($invoice['status']);
        
        $totalPaid = (float)($invoice['paid_amount'] ?? 0);
        $balanceDue = (float)($invoice['balance_due'] ?? ($invoice['total'] - $totalPaid));
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Invoice <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
            <style>
                @page {
                    margin: 2cm;
                }
                
                body {
                    font-family: 'Helvetica', 'Arial', sans-serif;
                    margin: 0;
                    padding: 0;
                    color: #2c3e50;
                    line-height: 1.5;
                    font-size: 11pt;
                }
                
                .invoice-container {
                    max-width: 100%;
                    margin: 0 auto;
                }
                
                /* Header Section */
                .invoice-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 30px;
                    padding-bottom: 20px;
                    border-bottom: 3px solid #3498db;
                }
                
                .company-info h1 {
                    color: #2c3e50;
                    font-size: 24pt;
                    margin: 0 0 10px 0;
                    font-weight: 300;
                    letter-spacing: 1px;
                }
                
                .company-info p {
                    margin: 3px 0;
                    color: #7f8c8d;
                    font-size: 9pt;
                }
                
                .invoice-title {
                    text-align: right;
                }
                
                .invoice-title .invoice-label {
                    color: #3498db;
                    font-size: 28pt;
                    font-weight: 700;
                    margin: 0;
                    line-height: 1;
                    text-transform: uppercase;
                    letter-spacing: 2px;
                }
                
                .invoice-title .invoice-number {
                    color: #2c3e50;
                    font-size: 14pt;
                    margin: 5px 0 0 0;
                    font-weight: 400;
                }
                
                /* Status Badge */
                .status-badge {
                    display: inline-block;
                    padding: 6px 15px;
                    background-color: <?php echo $statusColor; ?>;
                    color: white;
                    border-radius: 30px;
                    font-size: 10pt;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                    margin-top: 10px;
                }
                
                /* Info Grid */
                .info-grid {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                    background: #f8fafc;
                    padding: 20px;
                    border-radius: 8px;
                }
                
                .info-box {
                    flex: 1;
                }
                
                .info-box .label {
                    color: #7f8c8d;
                    font-size: 8pt;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    margin-bottom: 5px;
                }
                
                .info-box .value {
                    color: #2c3e50;
                    font-size: 11pt;
                    font-weight: 600;
                }
                
                /* Addresses */
                .addresses {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                }
                
                .address-box {
                    flex: 1;
                }
                
                .address-box h3 {
                    color: #2c3e50;
                    font-size: 11pt;
                    margin: 0 0 10px 0;
                    padding-bottom: 5px;
                    border-bottom: 2px solid #3498db;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .address-box p {
                    color: #5a6a7a;
                    font-size: 10pt;
                    margin: 3px 0;
                    line-height: 1.6;
                }
                
                /* Items Table */
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                
                .items-table th {
                    background: #2c3e50;
                    color: white;
                    padding: 12px 10px;
                    text-align: left;
                    font-size: 9pt;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .items-table th:first-child {
                    border-top-left-radius: 8px;
                }
                
                .items-table th:last-child {
                    border-top-right-radius: 8px;
                }
                
                .items-table td {
                    padding: 12px 10px;
                    border-bottom: 1px solid #e2e8f0;
                    font-size: 10pt;
                    color: #2c3e50;
                }
                
                .items-table tbody tr:hover {
                    background-color: #f8fafc;
                }
                
                .items-table .text-right {
                    text-align: right;
                }
                
                .items-table .text-center {
                    text-align: center;
                }
                
                /* Summary Section */
                .summary-section {
                    display: flex;
                    justify-content: flex-end;
                    margin-bottom: 30px;
                }
                
                .summary-table {
                    width: 350px;
                    border-collapse: collapse;
                }
                
                .summary-table tr td {
                    padding: 8px 10px;
                    font-size: 10pt;
                }
                
                .summary-table tr td:first-child {
                    text-align: left;
                    color: #5a6a7a;
                }
                
                .summary-table tr td:last-child {
                    text-align: right;
                    font-weight: 600;
                    color: #2c3e50;
                }
                
                .summary-table .total-row {
                    background: #f8fafc;
                    font-size: 12pt;
                }
                
                .summary-table .total-row td {
                    padding: 12px 10px;
                    border-top: 2px solid #3498db;
                }
                
                .summary-table .total-row td:last-child {
                    color: #3498db;
                    font-size: 14pt;
                    font-weight: 700;
                }
                
                .summary-table .paid-row td:last-child {
                    color: #27ae60;
                }
                
                .summary-table .balance-row td:last-child {
                    color: #e74c3c;
                }
                
                /* Notes Section */
                .notes-section {
                    margin-top: 30px;
                    padding: 20px;
                    background: #f8fafc;
                    border-radius: 8px;
                }
                
                .notes-section h3 {
                    color: #2c3e50;
                    font-size: 11pt;
                    margin: 0 0 10px 0;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                
                .notes-section p {
                    color: #5a6a7a;
                    font-size: 10pt;
                    margin: 0;
                    line-height: 1.6;
                }
                
                /* Footer */
                .invoice-footer {
                    margin-top: 40px;
                    text-align: center;
                    color: #95a5a6;
                    font-size: 8pt;
                    padding-top: 20px;
                    border-top: 1px solid #e2e8f0;
                }
                
                .invoice-footer p {
                    margin: 3px 0;
                }
                
                /* Watermark */
                .watermark {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    opacity: 0.1;
                    font-size: 60pt;
                    color: #2c3e50;
                    transform: rotate(-15deg);
                    z-index: -1;
                }
            </style>
        </head>
        <body>
            <div class="invoice-container">
                <!-- Watermark -->
                <?php if ($invoice['status'] === 'paid'): ?>
                <div class="watermark">PAID</div>
                <?php endif; ?>
                
                <!-- Header -->
                <div class="invoice-header">
                    <div class="company-info">
                        <h1><?php echo htmlspecialchars($company['name']); ?></h1>
                        <p><?php echo nl2br(htmlspecialchars($company['address'])); ?></p>
                        <p>Email: <?php echo htmlspecialchars($company['email']); ?></p>
                        <p>Phone: <?php echo htmlspecialchars($company['phone']); ?></p>
                        <?php if (!empty($invoice['tax_id'])): ?>
                        <p>Tax ID: <?php echo htmlspecialchars($invoice['tax_id']); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="invoice-title">
                        <div class="invoice-label">INVOICE</div>
                        <div class="invoice-number">#<?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                        <div class="status-badge"><?php echo $status; ?></div>
                    </div>
                </div>
                
                <!-- Info Grid -->
                <div class="info-grid">
                    <div class="info-box">
                        <div class="label">Invoice Date</div>
                        <div class="value"><?php echo date('F d, Y', strtotime($invoice['invoice_date'])); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Due Date</div>
                        <div class="value"><?php echo date('F d, Y', strtotime($invoice['due_date'])); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="label">Payment Terms</div>
                        <div class="value"><?php echo ucfirst(str_replace('_', ' ', $invoice['payment_terms'] ?? 'Net 30')); ?></div>
                    </div>
                    <?php if (!empty($invoice['business_number'])): ?>
                    <div class="info-box">
                        <div class="label">Business #</div>
                        <div class="value"><?php echo htmlspecialchars($invoice['business_number']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Addresses -->
                <div class="addresses">
                    <div class="address-box">
                        <h3>Bill To</h3>
                        <p><?php echo nl2br(htmlspecialchars($invoice['bill_to'])); ?></p>
                    </div>
                    
                    <?php if (!empty($invoice['ship_to'])): ?>
                    <div class="address-box">
                        <h3>Ship To</h3>
                        <p><?php echo nl2br(htmlspecialchars($invoice['ship_to'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($client): ?>
                    <div class="address-box">
                        <h3>Client Details</h3>
                        <p>
                            <?php echo htmlspecialchars($client['contact_person'] ?? ''); ?><br>
                            <?php echo htmlspecialchars($client['email'] ?? ''); ?><br>
                            <?php echo htmlspecialchars($client['phone'] ?? ''); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Items Table -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th width="40%">Description</th>
                            <th width="10%" class="text-center">Qty</th>
                            <th width="15%" class="text-right">Unit Price</th>
                            <th width="10%" class="text-right">Discount</th>
                            <th width="10%" class="text-right">Tax</th>
                            <th width="15%" class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): 
                            $itemDiscount = (float)($item['discount'] ?? 0);
                            $itemTaxRate = (float)($item['tax_rate'] ?? 0);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['description'] ?? ''); ?></td>
                            <td class="text-center"><?php echo (float)($item['quantity'] ?? 0); ?></td>
                            <td class="text-right">$<?php echo number_format((float)($item['unit_price'] ?? 0), 2); ?></td>
                            <td class="text-right"><?php echo $itemDiscount > 0 ? '$' . number_format($itemDiscount, 2) : '-'; ?></td>
                            <td class="text-right"><?php echo $itemTaxRate > 0 ? $itemTaxRate . '%' : '-'; ?></td>
                            <td class="text-right"><strong>$<?php echo number_format((float)($item['total'] ?? 0), 2); ?></strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Summary -->
                <div class="summary-section">
                    <table class="summary-table">
                        <tr>
                            <td>Subtotal:</td>
                            <td>$<?php echo number_format((float)($invoice['subtotal'] ?? 0), 2); ?></td>
                        </tr>
                        
                        <?php if (!empty($invoice['tax_amount']) && $invoice['tax_amount'] > 0): ?>
                        <tr>
                            <td>Tax (<?php echo (float)($invoice['tax_rate'] ?? 0); ?>%):</td>
                            <td>$<?php echo number_format((float)$invoice['tax_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($invoice['discount_amount']) && $invoice['discount_amount'] > 0): ?>
                        <tr>
                            <td>Discount:</td>
                            <td>-$<?php echo number_format((float)$invoice['discount_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if (!empty($invoice['shipping_amount']) && $invoice['shipping_amount'] > 0): ?>
                        <tr>
                            <td>Shipping:</td>
                            <td>$<?php echo number_format((float)$invoice['shipping_amount'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <tr class="total-row">
                            <td><strong>Total Amount:</strong></td>
                            <td><strong>$<?php echo number_format((float)($invoice['total'] ?? 0), 2); ?></strong></td>
                        </tr>
                        
                        <?php if ($totalPaid > 0): ?>
                        <tr class="paid-row">
                            <td>Amount Paid:</td>
                            <td>$<?php echo number_format($totalPaid, 2); ?></td>
                        </tr>
                        <tr class="balance-row">
                            <td><strong>Balance Due:</strong></td>
                            <td><strong>$<?php echo number_format($balanceDue, 2); ?></strong></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <!-- Notes -->
                <?php if (!empty($invoice['notes']) || !empty($invoice['terms_conditions'])): ?>
                <div class="notes-section">
                    <?php if (!empty($invoice['notes'])): ?>
                    <h3>Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($invoice['notes'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if (!empty($invoice['terms_conditions'])): ?>
                    <h3 style="margin-top: 15px;">Terms & Conditions</h3>
                    <p><?php echo nl2br(htmlspecialchars($invoice['terms_conditions'])); ?></p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <!-- Footer -->
                <div class="invoice-footer">
                    <p>Thank you for your business!</p>
                    <p>This is a computer-generated invoice. No signature is required.</p>
                    <p>Generated on: <?php echo date('F d, Y \a\t h:i A'); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    private function getStatusColor($status) {
        switch ($status) {
            case 'paid':
                return '#27ae60';
            case 'sent':
                return '#3498db';
            case 'overdue':
                return '#e74c3c';
            case 'draft':
                return '#95a5a6';
            case 'cancelled':
                return '#7f8c8d';
            default:
                return '#95a5a6';
        }
    }
}