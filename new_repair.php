<?php
require_once 'config.php';
requireAdmin();

$page_title = 'New Repair Order';
$message = '';
$error = '';
$vehicle_id = (int)($_GET['vehicle_id'] ?? 0);

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_repair') {
        $vehicle_id = (int)$_POST['vehicle_id'];
        $mechanic_id = !empty($_POST['mechanic_id']) ? (int)$_POST['mechanic_id'] : null;
        $problem_description = sanitizeInput($_POST['problem_description']);
        $start_date = sanitizeInput($_POST['start_date']);
        
        if (!empty($problem_description) && !empty($start_date) && $vehicle_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO Repairs (vehicle_id, mechanic_id, problem_description, start_date, status) VALUES (?, ?, ?, ?, 'Pending')");
            if ($stmt->execute([$vehicle_id, $mechanic_id, $problem_description, $start_date])) {
                $repair_id = $pdo->lastInsertId();
                header("Location: repair.php?id=$repair_id");
                exit();
            } else {
                $error = "Failed to create repair order.";
            }
        } else {
            $error = "Vehicle, start date, and problem description are required.";
        }
    }
    
    $stmt = $pdo->query("
        SELECT c.client_id, c.name as client_name, 
               v.vehicle_id, v.license_plate, v.brand, v.model
        FROM Clients c
        JOIN Vehicles v ON c.client_id = v.client_id
        ORDER BY c.name, v.license_plate
    ");
    $vehicles = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT 
            m.mechanic_id, 
            m.name,
            COUNT(r.repair_id) as active_repairs
        FROM Mechanics m
        LEFT JOIN Repairs r ON m.mechanic_id = r.mechanic_id AND r.status IN ('Pending', 'In Progress')
        GROUP BY m.mechanic_id, m.name
        ORDER BY m.name
    ");
    $mechanics = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-plus me-2"></i>New Repair Order</h1>
</div>

<?php if ($error): ?>
<div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="create_repair">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Repair Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="vehicle_id" class="form-label">Select Vehicle *</label>
                        <select class="form-select" id="vehicle_id" name="vehicle_id" required>
                            <option value="">Choose a vehicle...</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?= $vehicle['vehicle_id'] ?>" <?= $vehicle_id == $vehicle['vehicle_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($vehicle['license_plate'] . ' - ' . $vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['client_name'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="mechanic_id" class="form-label">Assign to Mechanic</label>
                        <select class="form-select" id="mechanic_id" name="mechanic_id">
                            <option value="">(Unassigned)</option>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <option value="<?= $mechanic['mechanic_id'] ?>">
                                    <?= htmlspecialchars($mechanic['name']) ?> (<?= $mechanic['active_repairs'] ?> en cours)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="start_date" class="form-label">Start Date *</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="problem_description" class="form-label">Problem Description *</label>
                        <textarea class="form-control" id="problem_description" name="problem_description" rows="5" required></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
             <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-1"></i>
                    Create Repair Order
                </button>
                <a href="repairs.php" class="btn btn-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<?php include 'includes/footer.php'; ?>