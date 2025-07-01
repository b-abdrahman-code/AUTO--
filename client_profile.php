<?php
require_once 'config.php';
requireAdmin();

$page_title = 'Client Profile';
$message = '';
$error = '';
$client_id = (int)($_GET['id'] ?? 0);

if (!$client_id) {
    header('Location: clients.php');
    exit();
}

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_vehicle':
                    $license_plate = sanitizeInput($_POST['license_plate']);
                    $type = sanitizeInput($_POST['type']);
                    $brand = sanitizeInput($_POST['brand']);
                    $model = sanitizeInput($_POST['model']);
                    $year = (int)$_POST['year'];
                    
                    if (!empty($license_plate) && !empty($type) && !empty($brand)) {
                        $stmt = $pdo->prepare("INSERT INTO Vehicles (license_plate, type, brand, model, year, client_id) VALUES (?, ?, ?, ?, ?, ?)");
                        if ($stmt->execute([$license_plate, $type, $brand, $model, $year, $client_id])) {
                            $message = "Vehicle added successfully!";
                        } else {
                            $error = "Failed to add vehicle. License plate might already exist.";
                        }
                    } else {
                        $error = "License plate, type, and brand are required.";
                    }
                    break;
                    
                case 'edit_vehicle':
                    $vehicle_id = (int)$_POST['vehicle_id'];
                    $license_plate = sanitizeInput($_POST['license_plate']);
                    $type = sanitizeInput($_POST['type']);
                    $brand = sanitizeInput($_POST['brand']);
                    $model = sanitizeInput($_POST['model']);
                    $year = (int)$_POST['year'];
                    
                    if (!empty($license_plate) && !empty($type) && !empty($brand)) {
                        $stmt = $pdo->prepare("UPDATE Vehicles SET license_plate = ?, type = ?, brand = ?, model = ?, year = ? WHERE vehicle_id = ? AND client_id = ?");
                        if ($stmt->execute([$license_plate, $type, $brand, $model, $year, $vehicle_id, $client_id])) {
                            $message = "Vehicle updated successfully!";
                        } else {
                            $error = "Failed to update vehicle.";
                        }
                    } else {
                        $error = "License plate, type, and brand are required.";
                    }
                    break;
                    
                case 'delete_vehicle':
                    $vehicle_id = (int)$_POST['vehicle_id'];
                    $stmt = $pdo->prepare("DELETE FROM Vehicles WHERE vehicle_id = ? AND client_id = ?");
                    if ($stmt->execute([$vehicle_id, $client_id])) {
                        $message = "Vehicle deleted successfully!";
                    } else {
                        $error = "Failed to delete vehicle.";
                    }
                    break;
            }
        }
    }
    
    $stmt = $pdo->prepare("SELECT * FROM Clients WHERE client_id = ?");
    $stmt->execute([$client_id]);
    $client = $stmt->fetch();
    
    if (!$client) {
        header('Location: clients.php');
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT v.*, COUNT(r.repair_id) as repair_count
        FROM Vehicles v
        LEFT JOIN Repairs r ON v.vehicle_id = r.vehicle_id
        WHERE v.client_id = ?
        GROUP BY v.vehicle_id
        ORDER BY v.created_at DESC
    ");
    $stmt->execute([$client_id]);
    $vehicles = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT r.*, v.license_plate, v.brand, v.model
        FROM Repairs r
        JOIN Vehicles v ON r.vehicle_id = v.vehicle_id
        WHERE v.client_id = ?
        ORDER BY r.start_date DESC
        LIMIT 10
    ");
    $stmt->execute([$client_id]);
    $repairs = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT v.vehicle_id) as total_vehicles,
            COUNT(r.repair_id) as total_repairs,
            COALESCE(SUM(r.total_cost), 0) as total_spent,
            COUNT(CASE WHEN r.status = 'In Progress' THEN 1 END) as active_repairs
        FROM Vehicles v
        LEFT JOIN Repairs r ON v.vehicle_id = r.vehicle_id
        WHERE v.client_id = ?
    ");
    $stmt->execute([$client_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-user me-2"></i>
        <?= htmlspecialchars($client['name']) ?>
        <small class="text-muted">#<?= $client['client_id'] ?></small>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="clients.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                Back to Clients
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                <i class="fas fa-car me-1"></i>
                Add Vehicle
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

<!-- Client Information Card -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    Client Information
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Name:</strong><br>
                    <?= htmlspecialchars($client['name']) ?>
                </div>
                <div class="mb-3">
                    <strong>Phone:</strong><br>
                    <a href="tel:<?= htmlspecialchars($client['phone']) ?>" class="text-decoration-none">
                        <i class="fas fa-phone me-1"></i>
                        <?= htmlspecialchars($client['phone']) ?>
                    </a>
                </div>
                <div class="mb-3">
                    <strong>Address:</strong><br>
                    <?= htmlspecialchars($client['address'] ?: 'Not provided') ?>
                </div>
                <div class="mb-0">
                    <strong>Member Since:</strong><br>
                    <?= formatDate($client['created_at']) ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="col-md-8">
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Total Vehicles
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-white">
                                    <?= $stats['total_vehicles'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-car fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card stat-card success">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Total Repairs
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-white">
                                    <?= $stats['total_repairs'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-wrench fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card stat-card warning">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Active Repairs
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-white">
                                    <?= $stats['active_repairs'] ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-cog fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 mb-3">
                <div class="card stat-card danger">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Total Spent
                                </div>
                                <div class="h5 mb-0 font-weight-bold text-white">
                                    <?= formatCurrency($stats['total_spent']) ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-dollar-sign fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vehicles Section -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-car me-2"></i>
            Vehicles (<?= count($vehicles) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($vehicles)): ?>
        <p class="text-muted">No vehicles registered for this client. Add their first vehicle using the button above.</p>
        <?php else: ?>
        <div class="row">
            <?php foreach ($vehicles as $vehicle): ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-car me-2"></i>
                            <?= htmlspecialchars($vehicle['license_plate']) ?>
                        </h6>
                        <p class="card-text">
                            <strong><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></strong><br>
                            <small class="text-muted">
                                <?= htmlspecialchars($vehicle['type']) ?> 
                                <?= $vehicle['year'] ? '(' . $vehicle['year'] . ')' : '' ?>
                            </small>
                        </p>
                        <div class="mb-2">
                            <span class="badge bg-info"><?= $vehicle['repair_count'] ?> repairs</span>
                        </div>
                        <div class="btn-group w-100" role="group">
                            <a href="new_repair.php?vehicle_id=<?= $vehicle['vehicle_id'] ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Repair
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" 
                                    onclick="editVehicle(<?= htmlspecialchars(json_encode($vehicle)) ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                    onclick="deleteVehicle(<?= $vehicle['vehicle_id'] ?>, '<?= htmlspecialchars($vehicle['license_plate']) ?>')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Repair History Section -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-history me-2"></i>
            Recent Repair History
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($repairs)): ?>
        <p class="text-muted">No repair history found for this client.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Repair ID</th>
                        <th>Vehicle</th>
                        <th>Problem</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($repairs as $repair): ?>
                    <tr>
                        <td>#<?= $repair['repair_id'] ?></td>
                        <td>
                            <?= htmlspecialchars($repair['license_plate']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($repair['brand'] . ' ' . $repair['model']) ?></small>
                        </td>
                        <td>
                            <span title="<?= htmlspecialchars($repair['problem_description']) ?>">
                                <?= htmlspecialchars(substr($repair['problem_description'], 0, 50)) ?>
                                <?= strlen($repair['problem_description']) > 50 ? '...' : '' ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-status status-<?= strtolower(str_replace(' ', '-', $repair['status'])) ?>">
                                <?= $repair['status'] ?>
                            </span>
                        </td>
                        <td><?= formatDate($repair['start_date']) ?></td>
                        <td><?= formatCurrency($repair['total_cost']) ?></td>
                        <td>
                            <a href="repair.php?id=<?= $repair['repair_id'] ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Vehicle Modal -->
<div class="modal fade" id="addVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-car me-2"></i>
                    Add New Vehicle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_vehicle">
                    
                    <div class="mb-3">
                        <label for="license_plate" class="form-label">License Plate *</label>
                        <input type="text" class="form-control" id="license_plate" name="license_plate" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="type" class="form-label">Vehicle Type *</label>
                                <select class="form-select" id="type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="Car">Car</option>
                                    <option value="SUV">SUV</option>
                                    <option value="Truck">Truck</option>
                                    <option value="Van">Van</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="year" class="form-label">Year</label>
                                <input type="number" class="form-control" id="year" name="year" min="1900" max="<?= date('Y') + 1 ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="brand" class="form-label">Brand *</label>
                                <input type="text" class="form-control" id="brand" name="brand" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="model" name="model">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Add Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Vehicle Modal -->
<div class="modal fade" id="editVehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Vehicle
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_vehicle">
                    <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                    
                    <div class="mb-3">
                        <label for="edit_license_plate" class="form-label">License Plate *</label>
                        <input type="text" class="form-control" id="edit_license_plate" name="license_plate" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_type" class="form-label">Vehicle Type *</label>
                                <select class="form-select" id="edit_type" name="type" required>
                                    <option value="">Select Type</option>
                                    <option value="Car">Car</option>
                                    <option value="SUV">SUV</option>
                                    <option value="Truck">Truck</option>
                                    <option value="Van">Van</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_year" class="form-label">Year</label>
                                <input type="number" class="form-control" id="edit_year" name="year" min="1900" max="<?= date('Y') + 1 ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_brand" class="form-label">Brand *</label>
                                <input type="text" class="form-control" id="edit_brand" name="brand" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="edit_model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="edit_model" name="model">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Update Vehicle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Vehicle Form (Hidden) -->
<form id="deleteVehicleForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_vehicle">
    <input type="hidden" name="vehicle_id" id="delete_vehicle_id">
</form>

<script>
function editVehicle(vehicle) {
    document.getElementById('edit_vehicle_id').value = vehicle.vehicle_id;
    document.getElementById('edit_license_plate').value = vehicle.license_plate;
    document.getElementById('edit_type').value = vehicle.type;
    document.getElementById('edit_brand').value = vehicle.brand;
    document.getElementById('edit_model').value = vehicle.model || '';
    document.getElementById('edit_year').value = vehicle.year || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editVehicleModal'));
    modal.show();
}

function deleteVehicle(vehicleId, licensePlate) {
    if (confirmDelete('Are you sure you want to delete vehicle "' + licensePlate + '"? This will also delete all repair history for this vehicle.')) {
        document.getElementById('delete_vehicle_id').value = vehicleId;
        document.getElementById('deleteVehicleForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>