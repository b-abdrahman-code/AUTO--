<?php
require_once 'config.php';
requireAdmin();

$page_title = 'Inventory Management';
$message = '';
$error = '';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_part':
                    $part_reference = sanitizeInput($_POST['part_reference']);
                    $designation = sanitizeInput($_POST['designation']);
                    $quantity = (int)$_POST['quantity_in_stock'];
                    $price = (float)$_POST['price_per_unit'];
                    $min_stock = (int)$_POST['min_stock_level'];
                    
                    if (!empty($part_reference) && !empty($designation)) {
                        $stmt = $pdo->prepare("INSERT INTO Parts (part_reference, designation, quantity_in_stock, price_per_unit, min_stock_level) VALUES (?, ?, ?, ?, ?)");
                        if ($stmt->execute([$part_reference, $designation, $quantity, $price, $min_stock])) {
                            $message = "Part added successfully!";
                        } else {
                            $error = "Failed to add part. Part reference might already exist.";
                        }
                    } else {
                        $error = "Part reference and designation are required.";
                    }
                    break;
                    
                case 'edit_part':
                    $part_id = (int)$_POST['part_id'];
                    $part_reference = sanitizeInput($_POST['part_reference']);
                    $designation = sanitizeInput($_POST['designation']);
                    $quantity = (int)$_POST['quantity_in_stock'];
                    $price = (float)$_POST['price_per_unit'];
                    $min_stock = (int)$_POST['min_stock_level'];
                    
                    if (!empty($part_reference) && !empty($designation)) {
                        $stmt = $pdo->prepare("UPDATE Parts SET part_reference = ?, designation = ?, quantity_in_stock = ?, price_per_unit = ?, min_stock_level = ? WHERE part_id = ?");
                        if ($stmt->execute([$part_reference, $designation, $quantity, $price, $min_stock, $part_id])) {
                            $message = "Part updated successfully!";
                        } else {
                            $error = "Failed to update part.";
                        }
                    } else {
                        $error = "Part reference and designation are required.";
                    }
                    break;
                    
                case 'delete_part':
                    $part_id = (int)$_POST['part_id'];
                    $stmt = $pdo->prepare("DELETE FROM Parts WHERE part_id = ?");
                    if ($stmt->execute([$part_id])) {
                        $message = "Part deleted successfully!";
                    } else {
                        $error = "Failed to delete part. It might be used in repair orders.";
                    }
                    break;
                    
                case 'adjust_stock':
                    $part_id = (int)$_POST['part_id'];
                    $adjustment = (int)$_POST['adjustment'];
                    
                    $stmt = $pdo->prepare("UPDATE Parts SET quantity_in_stock = quantity_in_stock + ? WHERE part_id = ?");
                    if ($stmt->execute([$adjustment, $part_id])) {
                        $message = "Stock adjusted successfully!";
                    } else {
                        $error = "Failed to adjust stock.";
                    }
                    break;
            }
        }
    }
    
    $stmt = $pdo->query("SELECT * FROM Parts ORDER BY designation");
    $parts = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM Parts WHERE quantity_in_stock <= min_stock_level");
    $low_stock_count = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-boxes me-2"></i>
        Inventory Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
            <i class="fas fa-plus me-1"></i>
            Add New Part
        </button>
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

<?php if ($low_stock_count > 0): ?>
<div class="alert alert-warning" role="alert">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Low Stock Alert:</strong> <?= $low_stock_count ?> part(s) are at or below minimum stock level.
</div>
<?php endif; ?>

<!-- Inventory Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                            Total Parts
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-white">
                            <?= count($parts) ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-white-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card stat-card warning">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                            Low Stock
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-white">
                            <?= $low_stock_count ?>
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

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-list me-2"></i>
            Parts Inventory
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($parts)): ?>
        <p class="text-muted">No parts in inventory. Add your first part using the button above.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Name</th>
                        <th>Stock Level</th>
                        <th>Min Stock</th>
                        <th>Price per Unit</th>
                        <th>Total Value</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($parts as $part): ?>
                    <tr class="<?= $part['quantity_in_stock'] <= $part['min_stock_level'] ? 'table-warning' : '' ?>">
                        <td><strong><?= htmlspecialchars($part['part_reference']) ?></strong></td>
                        <td><?= htmlspecialchars($part['designation']) ?></td>
                        <td>
                            <span class="badge <?= $part['quantity_in_stock'] <= $part['min_stock_level'] ? 'bg-warning text-dark' : 'bg-success' ?>">
                                <?= $part['quantity_in_stock'] ?>
                            </span>
                        </td>
                        <td><?= $part['min_stock_level'] ?></td>
                        <td><?= formatCurrency($part['price_per_unit']) ?></td>
                        <td><?= formatCurrency($part['quantity_in_stock'] * $part['price_per_unit']) ?></td>
                        <td>
                            <?php if ($part['quantity_in_stock'] <= 0): ?>
                                <span class="badge bg-danger">Out of Stock</span>
                            <?php elseif ($part['quantity_in_stock'] <= $part['min_stock_level']): ?>
                                <span class="badge bg-warning text-dark">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        onclick="adjustStock(<?= $part['part_id'] ?>, '<?= htmlspecialchars($part['designation']) ?>')" title="Adjust Stock">
                                    <i class="fas fa-plus-minus"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="editPart(<?= htmlspecialchars(json_encode($part)) ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="deletePart(<?= $part['part_id'] ?>, '<?= htmlspecialchars($part['designation']) ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Part Modal -->
<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus me-2"></i>
                    Add New Part
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_part">
                    
                    <div class="mb-3">
                        <label for="part_reference" class="form-label">Part Reference *</label>
                        <input type="text" class="form-control" id="part_reference" name="part_reference" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="designation" class="form-label">Part Name *</label>
                        <input type="text" class="form-control" id="designation" name="designation" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="quantity_in_stock" class="form-label">Initial Quantity</label>
                                <input type="number" class="form-control" id="quantity_in_stock" name="quantity_in_stock" value="0" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="min_stock_level" class="form-label">Min Stock Level</label>
                                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="5" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="price_per_unit" class="form-label">Price per Unit</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="price_per_unit" name="price_per_unit" 
                                   step="0.01" min="0" oninput="formatCurrency(this)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Save Part
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Part Modal -->
<div class="modal fade" id="editPartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Part
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_part">
                    <input type="hidden" name="part_id" id="edit_part_id">
                    
                    <div class="mb-3">
                        <label for="edit_part_reference" class="form-label">Part Reference *</label>
                        <input type="text" class="form-control" id="edit_part_reference" name="part_reference" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_designation" class="form-label">Part Name *</label>
                        <input type="text" class="form-control" id="edit_designation" name="designation" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_quantity_in_stock" class="form-label">Current Quantity</label>
                                <input type="number" class="form-control" id="edit_quantity_in_stock" name="quantity_in_stock" min="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_min_stock_level" class="form-label">Min Stock Level</label>
                                <input type="number" class="form-control" id="edit_min_stock_level" name="min_stock_level" min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_price_per_unit" class="form-label">Price per Unit</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="edit_price_per_unit" name="price_per_unit" 
                                   step="0.01" min="0" oninput="formatCurrency(this)">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Update Part
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-plus-minus me-2"></i>
                    Adjust Stock Level
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="adjust_stock">
                    <input type="hidden" name="part_id" id="adjust_part_id">
                    
                    <p>Adjusting stock for: <strong id="adjust_part_name"></strong></p>
                    
                    <div class="mb-3">
                        <label for="adjustment" class="form-label">Adjustment (+ to add, - to remove)</label>
                        <input type="number" class="form-control" id="adjustment" name="adjustment" required>
                        <div class="form-text">Enter positive number to add stock, negative to remove stock</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Adjust Stock
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Part Form (Hidden) -->
<form id="deletePartForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_part">
    <input type="hidden" name="part_id" id="delete_part_id">
</form>

<script>
function editPart(part) {
    document.getElementById('edit_part_id').value = part.part_id;
    document.getElementById('edit_part_reference').value = part.part_reference;
    document.getElementById('edit_designation').value = part.designation;
    document.getElementById('edit_quantity_in_stock').value = part.quantity_in_stock;
    document.getElementById('edit_min_stock_level').value = part.min_stock_level;
    document.getElementById('edit_price_per_unit').value = part.price_per_unit;
    
    var modal = new bootstrap.Modal(document.getElementById('editPartModal'));
    modal.show();
}

function deletePart(partId, partName) {
    if (confirmDelete('Are you sure you want to delete "' + partName + '"? This action cannot be undone.')) {
        document.getElementById('delete_part_id').value = partId;
        document.getElementById('deletePartForm').submit();
    }
}

function adjustStock(partId, partName) {
    document.getElementById('adjust_part_id').value = partId;
    document.getElementById('adjust_part_name').textContent = partName;
    document.getElementById('adjustment').value = '';
    
    var modal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>