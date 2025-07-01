<?php
require_once 'config.php';
requireAdmin();

$page_title = 'Invoice Management';
$message = '';
$error = '';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'mark_paid':
                    $invoice_id = (int)$_POST['invoice_id'];
                    $payment_method = sanitizeInput($_POST['payment_method']);
                    
                    $stmt = $pdo->prepare("UPDATE Invoices SET payment_status = 'Paid', payment_method = ?, payment_date = CURDATE() WHERE invoice_id = ?");
                    if ($stmt->execute([$payment_method, $invoice_id])) {
                        $message = "Invoice marked as paid successfully!";
                    } else {
                        $error = "Failed to update invoice status.";
                    }
                    break;
                    
                case 'mark_overdue':
                    $invoice_id = (int)$_POST['invoice_id'];
                    
                    $stmt = $pdo->prepare("UPDATE Invoices SET payment_status = 'Overdue' WHERE invoice_id = ?");
                    if ($stmt->execute([$invoice_id])) {
                        $message = "Invoice marked as overdue.";
                    } else {
                        $error = "Failed to update invoice status.";
                    }
                    break;
            }
        }
    }
    
    $stmt = $pdo->query("
        SELECT i.*, c.name as client_name, c.phone as client_phone,
               v.license_plate, v.brand, v.model, r.problem_description
        FROM Invoices i
        JOIN Repairs r ON i.repair_id = r.repair_id
        JOIN Vehicles v ON r.vehicle_id = v.vehicle_id
        JOIN Clients c ON v.client_id = c.client_id
        ORDER BY i.issue_date DESC
    ");
    $invoices = $stmt->fetchAll();
    
    // Get invoice statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_invoices,
            COUNT(CASE WHEN payment_status = 'Unpaid' THEN 1 END) as unpaid_count,
            COUNT(CASE WHEN payment_status = 'Paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN payment_status = 'Overdue' THEN 1 END) as overdue_count,
            COALESCE(SUM(CASE WHEN payment_status = 'Unpaid' THEN total_amount END), 0) as unpaid_amount,
            COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount END), 0) as paid_amount,
            COALESCE(SUM(total_amount), 0) as total_amount
        FROM Invoices
    ");
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-file-invoice-dollar me-2"></i>
        Invoice Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                <i class="fas fa-print me-1"></i>
                Print Report
            </button>
        </div>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="fas fa-check-circle me-2"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Invoice Statistics -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                            Total Invoices
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-white">
                            <?= $stats['total_invoices'] ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-file-invoice fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card success">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                            Paid
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-white">
                            <?= $stats['paid_count'] ?>
                        </div>
                        <div class="text-xs text-white-50">
                            <?= formatCurrency($stats['paid_amount']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-check-circle fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                            Unpaid
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-white">
                            <?= $stats['unpaid_count'] ?>
                        </div>
                        <div class="text-xs text-white-50">
                            <?= formatCurrency($stats['unpaid_amount']) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clock fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card danger">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                            Overdue
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-white">
                            <?= $stats['overdue_count'] ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Tabs -->
<ul class="nav nav-tabs mb-3" id="invoiceTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
            <i class="fas fa-list me-1"></i>
            All Invoices
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="unpaid-tab" data-bs-toggle="tab" data-bs-target="#unpaid" type="button" role="tab">
            <i class="fas fa-clock me-1"></i>
            Unpaid
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="paid-tab" data-bs-toggle="tab" data-bs-target="#paid" type="button" role="tab">
            <i class="fas fa-check me-1"></i>
            Paid
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="overdue-tab" data-bs-toggle="tab" data-bs-target="#overdue" type="button" role="tab">
            <i class="fas fa-exclamation-triangle me-1"></i>
            Overdue
        </button>
    </li>
</ul>

<div class="tab-content" id="invoiceTabContent">
    <!-- All Invoices Tab -->
    <div class="tab-pane fade show active" id="all" role="tabpanel">
        <?= renderInvoiceTable($invoices) ?>
    </div>
    
    <!-- Unpaid Invoices Tab -->
    <div class="tab-pane fade" id="unpaid" role="tabpanel">
        <?= renderInvoiceTable(array_filter($invoices, function($i) { return $i['payment_status'] == 'Unpaid'; })) ?>
    </div>
    
    <!-- Paid Invoices Tab -->
    <div class="tab-pane fade" id="paid" role="tabpanel">
        <?= renderInvoiceTable(array_filter($invoices, function($i) { return $i['payment_status'] == 'Paid'; })) ?>
    </div>
    
    <!-- Overdue Invoices Tab -->
    <div class="tab-pane fade" id="overdue" role="tabpanel">
        <?= renderInvoiceTable(array_filter($invoices, function($i) { return $i['payment_status'] == 'Overdue'; })) ?>
    </div>
</div>

<?php
function renderInvoiceTable($invoices) {
    if (empty($invoices)) {
        return '<div class="card"><div class="card-body"><p class="text-muted">No invoices found.</p></div></div>';
    }
    
    ob_start();
    ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Vehicle</th>
                            <th>Amount</th>
                            <th>Issue Date</th>
                            <th>Status</th>
                            <th>Payment Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                        <tr>
                            <td>#<?= $invoice['invoice_id'] ?></td>
                            <td>
                                <?= htmlspecialchars($invoice['client_name']) ?><br>
                                <small class="text-muted">
                                    <i class="fas fa-phone me-1"></i>
                                    <?= htmlspecialchars($invoice['client_phone']) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($invoice['license_plate']) ?></strong><br>
                                <small class="text-muted">
                                    <?= htmlspecialchars($invoice['brand'] . ' ' . $invoice['model']) ?>
                                </small>
                            </td>
                            <td>
                                <strong><?= formatCurrency($invoice['total_amount']) ?></strong><br>
                                <?php if ($invoice['tax_amount'] > 0): ?>
                                <small class="text-muted">
                                    Tax: <?= formatCurrency($invoice['tax_amount']) ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td><?= formatDate($invoice['issue_date']) ?></td>
                            <td>
                                <span class="badge badge-status status-<?= strtolower($invoice['payment_status']) ?>">
                                    <?= $invoice['payment_status'] ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($invoice['payment_date']): ?>
                                    <?= formatDate($invoice['payment_date']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($invoice['payment_method']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group" role="group">
                                    <a href="print_invoice.php?id=<?= $invoice['invoice_id'] ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View/Print" target="_blank">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($invoice['payment_status'] == 'Unpaid'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-success" 
                                            onclick="markPaid(<?= $invoice['invoice_id'] ?>)" title="Mark as Paid">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-warning" 
                                            onclick="markOverdue(<?= $invoice['invoice_id'] ?>)" title="Mark as Overdue">
                                        <i class="fas fa-exclamation-triangle"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>

<!-- Mark as Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-check me-2"></i>
                    Mark Invoice as Paid
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="invoice_id" id="paid_invoice_id">
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method *</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select payment method...</option>
                            <option value="Cash">Cash</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                            <option value="Check">Check</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Payment date will be set to today's date.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>
                        Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark as Overdue Form (Hidden) -->
<form id="markOverdueForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="mark_overdue">
    <input type="hidden" name="invoice_id" id="overdue_invoice_id">
</form>

<script>
function markPaid(invoiceId) {
    document.getElementById('paid_invoice_id').value = invoiceId;
    var modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
    modal.show();
}

function markOverdue(invoiceId) {
    if (confirm('Are you sure you want to mark this invoice as overdue?')) {
        document.getElementById('overdue_invoice_id').value = invoiceId;
        document.getElementById('markOverdueForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>