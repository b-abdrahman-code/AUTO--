<?php
require_once 'config.php';
requireAdmin();

$invoice_id = (int)($_GET['id'] ?? 0);

if (!$invoice_id) {
    header('Location: invoices.php');
    exit();
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("
        SELECT i.*, r.problem_description, r.start_date, r.completion_date, r.wash_cost,
               c.name as client_name, c.phone as client_phone, c.address as client_address,
               v.license_plate, v.brand, v.model, v.type, v.year
        FROM Invoices i
        JOIN Repairs r ON i.repair_id = r.repair_id
        JOIN Vehicles v ON r.vehicle_id = v.vehicle_id
        JOIN Clients c ON v.client_id = c.client_id
        WHERE i.invoice_id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        header('Location: invoices.php');
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT t.description, t.duration, t.cost
        FROM Tasks t
        WHERE t.repair_id = ?
        ORDER BY t.created_at
    ");
    $stmt->execute([$invoice['repair_id']]);
    $tasks = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT p.part_reference, p.designation, rp.quantity_used, rp.price_at_time_of_use,
               (rp.quantity_used * rp.price_at_time_of_use) as total_cost
        FROM Repair_Parts rp
        JOIN Parts p ON rp.part_id = p.part_id
        WHERE rp.repair_id = ?
        ORDER BY rp.used_at
    ");
    $stmt->execute([$invoice['repair_id']]);
    $parts = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $invoice['invoice_id'] ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            .card { border: 1px solid #dee2e6 !important; }
            body { font-size: 12px; }
        }
        
        .invoice-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px 10px 0 0;
        }
        
        .invoice-body {
            padding: 2rem;
        }
        
        .company-info {
            text-align: right;
        }
        
        .invoice-table th {
            background-color: #f8f9fa;
            border-top: none;
        }
        
        .total-section {
            background-color: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container my-4">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <!-- Print Button -->
                <div class="text-end mb-3 no-print">
                    <button onclick="window.print()" class="btn btn-primary me-2">
                        <i class="fas fa-print me-1"></i>
                        Print Invoice
                    </button>
                    <a href="invoices.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Back to Invoices
                    </a>
                </div>
                
                <!-- Invoice Card -->
                <div class="card shadow">
                    <!-- Header -->
                    <div class="invoice-header">
                        <div class="row">
                            <div class="col-md-6">
                                <h1 class="mb-0">
                                    <i class="fas fa-tools me-2"></i>
                                    <?= SITE_NAME ?>
                                </h1>
                                <p class="mb-0">Professional Auto Repair Services</p>
                            </div>
                            <div class="col-md-6 company-info">
                                <h2 class="mb-3">INVOICE</h2>
                                <p class="mb-1"><strong>Invoice #: <?= $invoice['invoice_id'] ?></strong></p>
                                <p class="mb-1">Issue Date: <?= formatDate($invoice['issue_date']) ?></p>
                                <?php if ($invoice['payment_date']): ?>
                                <p class="mb-0">Paid Date: <?= formatDate($invoice['payment_date']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="invoice-body">
                        <!-- Company & Client Info -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5>From:</h5>
                                <address>
                                    <strong><?= SITE_NAME ?></strong><br>
                                    123 Auto Repair Street<br>
                                    City, State 12345<br>
                                    Phone: (555) 123-4567<br>
                                    Email: info@garage.com
                                </address>
                            </div>
                            <div class="col-md-6">
                                <h5>Bill To:</h5>
                                <address>
                                    <strong><?= htmlspecialchars($invoice['client_name']) ?></strong><br>
                                    <?php if ($invoice['client_address']): ?>
                                        <?= nl2br(htmlspecialchars($invoice['client_address'])) ?><br>
                                    <?php endif; ?>
                                    Phone: <?= htmlspecialchars($invoice['client_phone']) ?>
                                </address>
                            </div>
                        </div>
                        
                        <!-- Vehicle Info -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-car me-2"></i>
                                            Vehicle Information
                                        </h6>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>License Plate:</strong><br>
                                                <?= htmlspecialchars($invoice['license_plate']) ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Make & Model:</strong><br>
                                                <?= htmlspecialchars($invoice['brand'] . ' ' . $invoice['model']) ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Year:</strong><br>
                                                <?= $invoice['year'] ?: 'N/A' ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Type:</strong><br>
                                                <?= htmlspecialchars($invoice['type']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Service Period -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <strong>Service Period:</strong><br>
                                <?= formatDate($invoice['start_date']) ?> - <?= formatDate($invoice['completion_date']) ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Problem Description:</strong><br>
                                <?= htmlspecialchars(substr($invoice['problem_description'], 0, 100)) ?>
                                <?= strlen($invoice['problem_description']) > 100 ? '...' : '' ?>
                            </div>
                        </div>
                        
                        <!-- Labor Section -->
                        <?php if (!empty($tasks)): ?>
                        <h5 class="mb-3">Labor & Services</h5>
                        <div class="table-responsive mb-4">
                            <table class="table invoice-table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-center">Duration</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tasks as $task): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($task['description']) ?></td>
                                        <td class="text-center"><?= $task['duration'] ? $task['duration'] . 'h' : '-' ?></td>
                                        <td class="text-end"><?= formatCurrency($task['cost']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Parts Section -->
                        <?php if (!empty($parts)): ?>
                        <h5 class="mb-3">Parts & Materials</h5>
                        <div class="table-responsive mb-4">
                            <table class="table invoice-table">
                                <thead>
                                    <tr>
                                        <th>Part #</th>
                                        <th>Description</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Unit Price</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($parts as $part): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($part['part_reference']) ?></td>
                                        <td><?= htmlspecialchars($part['designation']) ?></td>
                                        <td class="text-center"><?= $part['quantity_used'] ?></td>
                                        <td class="text-end"><?= formatCurrency($part['price_at_time_of_use']) ?></td>
                                        <td class="text-end"><?= formatCurrency($part['total_cost']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Wash Service Section -->
                        <?php if ($invoice['wash_cost'] > 0): ?>
                        <h5 class="mb-3">Other Services</h5>
                        <div class="table-responsive mb-4">
                            <table class="table invoice-table">
                                <thead>
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Car Wash Service</td>
                                        <td class="text-end"><?= formatCurrency($invoice['wash_cost']) ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>

                        <!-- Totals -->
                        <div class="row">
                            <div class="col-md-6"></div>
                            <div class="col-md-6">
                                <div class="total-section">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <span><?= formatCurrency($invoice['amount']) ?></span>
                                    </div>
                                    <?php if ($invoice['tax_amount'] > 0): ?>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Tax (10%):</span>
                                        <span><?= formatCurrency($invoice['tax_amount']) ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total Amount:</strong>
                                        <strong class="h5"><?= formatCurrency($invoice['total_amount']) ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Payment Status -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="alert alert-<?= $invoice['payment_status'] == 'Paid' ? 'success' : ($invoice['payment_status'] == 'Overdue' ? 'danger' : 'warning') ?>">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <strong>Payment Status: <?= $invoice['payment_status'] ?></strong>
                                            <?php if ($invoice['payment_method']): ?>
                                            <br>Payment Method: <?= htmlspecialchars($invoice['payment_method']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-6 text-md-end">
                                            <?php if ($invoice['payment_status'] != 'Paid'): ?>
                                            <strong>Amount Due: <?= formatCurrency($invoice['total_amount']) ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Footer -->
                        <div class="row mt-4">
                            <div class="col-12">
                                <hr>
                                <div class="text-center text-muted">
                                    <p class="mb-1">Thank you for choosing <?= SITE_NAME ?>!</p>
                                    <p class="mb-0">For questions about this invoice, please contact us at (555) 123-4567</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>