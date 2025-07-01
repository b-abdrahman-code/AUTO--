<?php
require_once 'config.php';
requireLogin();

$page_title = 'Gestion des réparations';
$current_user = getCurrentUser();

try {
    $pdo = getDBConnection();
    
    $base_query = "
        SELECT r.*, c.name as client_name, v.license_plate, v.brand, v.model, m.name as mechanic_name
        FROM Repairs r
        JOIN Vehicles v ON r.vehicle_id = v.vehicle_id
        JOIN Clients c ON v.client_id = c.client_id
        LEFT JOIN Mechanics m ON r.mechanic_id = m.mechanic_id
    ";
    
    if ($current_user['role'] == 'admin') {
        $stmt = $pdo->query($base_query . " ORDER BY r.start_date DESC");
    } elseif ($current_user['role'] == 'user') {
        $query = $base_query . " WHERE c.user_id = ? ORDER BY r.start_date DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$current_user['user_id']]);
    } else {
        $stmt_mech_id = $pdo->prepare("SELECT mechanic_id FROM Mechanics WHERE user_id = ?");
        $stmt_mech_id->execute([$current_user['user_id']]);
        $mechanic = $stmt_mech_id->fetch();
        
        $mechanic_id = $mechanic ? $mechanic['mechanic_id'] : -1;
        
        $query = $base_query . " WHERE r.mechanic_id = ? ORDER BY r.start_date DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$mechanic_id]);
    }
    
    $repairs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

include 'includes/header.php';

function renderRepairTable($repairs, $user_role) {
    if (empty($repairs)) {
        return '<div class="card"><div class="card-body text-center"><p class="text-muted py-4">Aucune réparation trouvée.</p></div></div>';
    }
    ob_start();
?>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-hover">
    <thead><tr>
        <th>ID</th>
        <?php if ($user_role != 'user'): ?><th>Client</th><?php endif; ?>
        <th>Véhicule</th>
        <?php if ($user_role == 'admin'): ?><th>Assigned To</th><?php endif; ?>
        <th>Problème</th><th>Statut</th><th>Date</th><th>Coût</th><th>Actions</th>
    </tr></thead>
    <tbody>
    <?php foreach ($repairs as $repair): ?>
        <tr>
            <td>#<?= $repair['repair_id'] ?></td>
            <?php if ($user_role != 'user'): ?><td><?= htmlspecialchars($repair['client_name']) ?></td><?php endif; ?>
            <td><strong><?= htmlspecialchars($repair['license_plate']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($repair['brand'] . ' ' . $repair['model']) ?></small></td>
            <?php if ($user_role == 'admin'): ?><td><?= htmlspecialchars($repair['mechanic_name'] ?? 'Unassigned') ?></td><?php endif; ?>
            <td><span title="<?= htmlspecialchars($repair['problem_description']) ?>"><?= htmlspecialchars(substr($repair['problem_description'], 0, 40)) ?>...</span></td>
            <td><span class="badge badge-status status-<?= strtolower(str_replace(' ', '-', $repair['status'])) ?>"><?= $repair['status'] ?></span></td>
            <td><?= formatDate($repair['start_date']) ?></td>
            <td><?= formatCurrency($repair['total_cost']) ?></td>
            <td><a href="repair.php?id=<?= $repair['repair_id'] ?>" class="btn btn-sm btn-outline-primary" title="Voir détails"><i class="fas fa-eye"></i></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div></div>
<?php return ob_get_clean(); }
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-wrench me-2"></i><?= $current_user['role'] == 'user' ? 'Mes Réparations' : 'Gestion des réparations'; ?></h1>
    <?php if ($current_user['role'] == 'admin'): ?>
    <a href="new_repair.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i>Nouvelle réparation</a>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs mb-3" id="repairTabs">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#all-tab-pane">Toutes</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#pending-tab-pane">En attente</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#progress-tab-pane">En cours</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#completed-tab-pane">Terminées</button></li>
</ul>

<div class="tab-content" id="repairTabContent">
    <div class="tab-pane fade show active" id="all-tab-pane"><?= renderRepairTable($repairs, $current_user['role']) ?></div>
    <div class="tab-pane fade" id="pending-tab-pane"><?= renderRepairTable(array_filter($repairs, fn($r) => $r['status'] == 'Pending'), $current_user['role']) ?></div>
    <div class="tab-pane fade" id="progress-tab-pane"><?= renderRepairTable(array_filter($repairs, fn($r) => $r['status'] == 'In Progress'), $current_user['role']) ?></div>
    <div class="tab-pane fade" id="completed-tab-pane"><?= renderRepairTable(array_filter($repairs, fn($r) => $r['status'] == 'Completed'), $current_user['role']) ?></div>
</div>

<?php include 'includes/footer.php'; ?>