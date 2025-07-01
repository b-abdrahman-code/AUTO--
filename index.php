<?php
require_once 'config.php';
requireLogin();

$page_title = 'Tableau de bord';
$current_user = getCurrentUser();
$error = '';
$message = '';

try {
    $pdo = getDBConnection();
    
    if ($current_user['role'] == 'user' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            $client_id = $current_user['client_id'];
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
                            $message = "Véhicule ajouté avec succès!";
                        } else {
                            $error = "Échec de l'ajout du véhicule. La plaque d'immatriculation existe peut-être déjà.";
                        }
                    } else {
                        $error = "Plaque, type et marque sont requis.";
                    }
                    break;
                case 'request_repair':
                    $vehicle_id = (int)$_POST['vehicle_id'];
                    $problem_description = sanitizeInput($_POST['problem_description']);
                    
                    $stmt = $pdo->prepare("SELECT 1 FROM Vehicles WHERE vehicle_id = ? AND client_id = ?");
                    $stmt->execute([$vehicle_id, $client_id]);

                    if ($stmt->fetch() && !empty($problem_description)) {
                        $stmt = $pdo->prepare("INSERT INTO Repairs (vehicle_id, problem_description, start_date, status) VALUES (?, ?, ?, 'Pending')");
                        if ($stmt->execute([$vehicle_id, $problem_description, date('Y-m-d')])) {
                            $message = "Demande de réparation envoyée avec succès!";
                        } else {
                            $error = "Échec de la création de la demande de réparation.";
                        }
                    } else {
                        $error = "Véhicule non valide ou description manquante.";
                    }
                    break;
            }
        }
    }

    $stats = [];
    
    if ($current_user['role'] == 'admin') {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Repairs WHERE status = 'In Progress'");
        $stats['active_repairs'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Repairs WHERE status = 'Pending'");
        $stats['pending_repairs'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Repairs WHERE status = 'Completed' AND DATE(completion_date) = CURDATE()");
        $stats['completed_today'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Invoices WHERE payment_status = 'Unpaid'");
        $stats['unpaid_invoices'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM Parts WHERE quantity_in_stock <= min_stock_level");
        $stats['low_stock_parts'] = $stmt->fetch()['count'];
        $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as revenue FROM Invoices WHERE payment_status = 'Paid' AND MONTH(payment_date) = MONTH(CURDATE())");
        $stats['monthly_revenue'] = $stmt->fetch()['revenue'];
        
        $stmt = $pdo->query("
            SELECT r.repair_id, r.problem_description, r.status, r.start_date, 
                   c.name as client_name, v.license_plate, v.brand, v.model
            FROM Repairs r JOIN Vehicles v ON r.vehicle_id = v.vehicle_id JOIN Clients c ON v.client_id = c.client_id
            ORDER BY r.start_date DESC LIMIT 5
        ");
        $recent_repairs = $stmt->fetchAll();

    } elseif ($current_user['role'] == 'user') {
        $client_id = $current_user['client_id'];
        if ($client_id) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Vehicles WHERE client_id = ?");
            $stmt->execute([$client_id]);
            $stats['my_vehicles'] = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("SELECT COUNT(r.repair_id) as count FROM Repairs r JOIN Vehicles v ON r.vehicle_id = v.vehicle_id WHERE v.client_id = ? AND r.status IN ('Pending', 'In Progress')");
            $stmt->execute([$client_id]);
            $stats['my_active_repairs'] = $stmt->fetch()['count'];

            $stmt = $pdo->prepare("SELECT COALESCE(SUM(r.total_cost), 0) as total FROM Repairs r JOIN Vehicles v ON r.vehicle_id = v.vehicle_id WHERE v.client_id = ? AND r.status = 'Completed'");
            $stmt->execute([$client_id]);
            $stats['my_total_spent'] = $stmt->fetch()['total'];

            $stmt = $pdo->prepare("SELECT v.*, COUNT(r.repair_id) as repair_count FROM Vehicles v LEFT JOIN Repairs r ON v.vehicle_id = r.vehicle_id WHERE v.client_id = ? GROUP BY v.vehicle_id ORDER BY v.created_at DESC");
            $stmt->execute([$client_id]);
            $my_vehicles = $stmt->fetchAll();
        } else {
            $error = "Profil client non trouvé.";
            $my_vehicles = [];
        }
    } else {
        $stats = [
            'my_pending_repairs' => 0,
            'my_in_progress_repairs' => 0
        ];
        $my_tasks = [];
    
        $stmt = $pdo->prepare("SELECT mechanic_id FROM Mechanics WHERE user_id = ?");
        $stmt->execute([$current_user['user_id']]);
        $mechanic = $stmt->fetch();
        
        if ($mechanic) {
            $mechanic_id = $mechanic['mechanic_id'];

            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Repairs WHERE mechanic_id = ? AND status = 'Pending'");
            $stmt->execute([$mechanic_id]);
            $stats['my_pending_repairs'] = $stmt->fetch()['count'];
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM Repairs WHERE mechanic_id = ? AND status = 'In Progress'");
            $stmt->execute([$mechanic_id]);
            $stats['my_in_progress_repairs'] = $stmt->fetch()['count'];
            
            $stmt = $pdo->prepare("
                SELECT r.repair_id, r.status, r.start_date, v.license_plate, v.brand, v.model, c.name as client_name
                FROM Repairs r
                JOIN Vehicles v ON r.vehicle_id = v.vehicle_id
                JOIN Clients c ON v.client_id = c.client_id
                WHERE r.mechanic_id = ? AND r.status IN ('Pending', 'In Progress')
                ORDER BY r.start_date ASC
                LIMIT 5
            ");
            $stmt->execute([$mechanic_id]);
            $my_tasks = $stmt->fetchAll();
        }
    }
    
} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-tachometer-alt me-2"></i>
        Tableau de bord
        <small class="text-muted">
            - <?= ucfirst($current_user['role'] == 'user' ? 'Client' : $current_user['role']) ?>
        </small>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($current_user['role'] == 'admin'): ?>
            <a href="new_repair.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Nouvelle réparation</a>
        <?php elseif ($current_user['role'] == 'user'): ?>
            <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#addVehicleModal"><i class="fas fa-car me-1"></i> Ajouter un véhicule</button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestRepairModal"><i class="fas fa-wrench me-1"></i> Demander une réparation</button>
        <?php else:   ?>
            <a href="repairs.php" class="btn btn-primary"><i class="fas fa-wrench me-1"></i> Voir toutes mes réparations</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>


<?php if ($current_user['role'] == 'admin'): ?>
<!-- ADMIN DASHBOARD -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-4"><div class="card stat-card"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Réparations actives</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['active_repairs'] ?></div></div><div class="col-auto"><i class="fas fa-wrench fa-2x text-white-50"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card stat-card warning"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">En attente</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['pending_repairs'] ?></div></div><div class="col-auto"><i class="fas fa-clock fa-2x text-white-50"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card stat-card success"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Terminées aujourd'hui</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['completed_today'] ?></div></div><div class="col-auto"><i class="fas fa-check-circle fa-2x text-white-50"></i></div></div></div></div></div>
    <div class="col-xl-3 col-md-6 mb-4"><div class="card stat-card danger"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Factures impayées</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['unpaid_invoices'] ?></div></div><div class="col-auto"><i class="fas fa-file-invoice-dollar fa-2x text-white-50"></i></div></div></div></div></div>
</div>
<div class="card"><div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-history me-2"></i>Réparations récentes</h5></div><div class="card-body">
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>ID</th><th>Client</th><th>Véhicule</th><th>Problème</th><th>Statut</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($recent_repairs as $repair): ?>
    <tr><td>#<?= $repair['repair_id'] ?></td><td><?= htmlspecialchars($repair['client_name']) ?></td><td><?= htmlspecialchars($repair['license_plate']) ?></td><td><?= htmlspecialchars(substr($repair['problem_description'], 0, 50)) ?>...</td><td><span class="badge badge-status status-<?= strtolower(str_replace(' ', '-', $repair['status'])) ?>"><?= $repair['status'] ?></span></td><td><a href="repair.php?id=<?= $repair['repair_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div></div>

<?php elseif ($current_user['role'] == 'user'): ?>
<!-- CLIENT DASHBOARD -->
<div class="row mb-4">
    <div class="col-md-4 mb-4"><div class="card stat-card"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Mes Véhicules</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['my_vehicles'] ?? 0 ?></div></div><div class="col-auto"><i class="fas fa-car fa-2x text-white-50"></i></div></div></div></div></div>
    <div class="col-md-4 mb-4"><div class="card stat-card warning"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Réparations en cours</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['my_active_repairs'] ?? 0 ?></div></div><div class="col-auto"><i class="fas fa-cog fa-spin fa-2x text-white-50"></i></div></div></div></div></div>
    <div class="col-md-4 mb-4"><div class="card stat-card success"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Total Dépensé</div><div class="h5 mb-0 font-weight-bold text-white"><?= formatCurrency($stats['my_total_spent'] ?? 0) ?></div></div><div class="col-auto"><i class="fas fa-euro-sign fa-2x text-white-50"></i></div></div></div></div></div>
</div>
<div class="card"><div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-car me-2"></i>Mon Garage</h5></div><div class="card-body">
    <?php if (empty($my_vehicles)): ?>
        <p class="text-muted text-center py-4">Vous n'avez pas encore de véhicule. Ajoutez votre premier véhicule!</p>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Plaque</th><th>Marque & Modèle</th><th>Type</th><th>Année</th><th>Réparations</th><th>Actions</th></tr></thead><tbody>
        <?php foreach ($my_vehicles as $vehicle): ?>
        <tr>
            <td><strong><?= htmlspecialchars($vehicle['license_plate']) ?></strong></td>
            <td><?= htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model']) ?></td>
            <td><?= htmlspecialchars($vehicle['type']) ?></td>
            <td><?= $vehicle['year'] ?></td>
            <td><span class="badge bg-info"><?= $vehicle['repair_count'] ?></span></td>
            <td><a href="repairs.php?vehicle_id=<?= $vehicle['vehicle_id'] ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye me-1"></i>Voir réparations</a></td>
        </tr>
        <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div></div>

<?php else: ?>
<div class="row mb-4">
    <div class="col-md-6 mb-4"><div class="card stat-card warning"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Réparations en attente</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['my_pending_repairs'] ?></div></div><div class="col-auto"><i class="fas fa-clock fa-2x text-white-50"></i></div></div></div></div></div>
    <div class="col-md-6 mb-4"><div class="card stat-card info"><div class="card-body"><div class="row no-gutters align-items-center"><div class="col mr-2"><div class="text-xs font-weight-bold text-white text-uppercase mb-1">Réparations en cours</div><div class="h5 mb-0 font-weight-bold text-white"><?= $stats['my_in_progress_repairs'] ?></div></div><div class="col-auto"><i class="fas fa-wrench fa-2x text-white-50"></i></div></div></div></div></div>
</div>
<div class="card"><div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-clipboard-list me-2"></i>Mes Réparations Assignées</h5></div><div class="card-body">
    <?php if (empty($my_tasks)):  ?>
        <p class="text-muted text-center py-4">Aucune réparation active ne vous est assignée.</p>
    <?php else: ?>
    <div class="table-responsive"><table class="table table-hover"><thead><tr><th>Véhicule</th><th>Client</th><th>Date</th><th>Statut</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($my_tasks as $repair): ?>
        <tr>
            <td><strong><?= htmlspecialchars($repair['license_plate']) ?></strong><br><small><?= htmlspecialchars($repair['brand'].' '.$repair['model']) ?></small></td>
            <td><?= htmlspecialchars($repair['client_name']) ?></td>
            <td><?= formatDate($repair['start_date']) ?></td>
            <td><span class="badge badge-status status-<?= strtolower(str_replace(' ', '-', $repair['status'])) ?>"><?= $repair['status'] ?></span></td>
            <td><a href="repair.php?id=<?= $repair['repair_id'] ?>" class="btn btn-sm btn-outline-primary" title="Ouvrir la fiche de réparation"><i class="fas fa-eye"></i></a></td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <?php endif; ?>
</div></div>
<?php endif; ?>


<?php if ($current_user['role'] == 'user'): ?>

<div class="modal fade" id="addVehicleModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-car me-2"></i>Ajouter un nouveau véhicule</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="action" value="add_vehicle"><div class="mb-3"><label for="license_plate" class="form-label">Plaque d'immatriculation *</label><input type="text" class="form-control" id="license_plate" name="license_plate" required></div><div class="row"><div class="col-md-6 mb-3"><label for="type" class="form-label">Type *</label><select class="form-select" id="type" name="type" required><option value="">Select Type</option><option value="Car">Voiture</option><option value="SUV">SUV</option><option value="Truck">Camion</option><option value="Van">Fourgonnette</option><option value="Motorcycle">Moto</option></select></div><div class="col-md-6 mb-3"><label for="year" class="form-label">Année</label><input type="number" class="form-control" id="year" name="year" min="1900" max="<?= date('Y') + 1 ?>"></div></div><div class="row"><div class="col-md-6 mb-3"><label for="brand" class="form-label">Marque *</label><input type="text" class="form-control" id="brand" name="brand" required></div><div class="col-md-6 mb-3"><label for="model" class="form-label">Modèle</label><input type="text" class="form-control" id="model" name="model"></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Ajouter</button></div></form></div></div></div>


<div class="modal fade" id="requestRepairModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title"><i class="fas fa-wrench me-2"></i>Demander une réparation</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="action" value="request_repair"><div class="mb-3"><label for="vehicle_id" class="form-label">Sélectionner un véhicule *</label><select class="form-select" id="vehicle_id" name="vehicle_id" required><option value="">Choisir un véhicule...</option><?php foreach ($my_vehicles as $vehicle): ?><option value="<?= $vehicle['vehicle_id'] ?>"><?= htmlspecialchars($vehicle['license_plate'] . ' - ' . $vehicle['brand'] . ' ' . $vehicle['model']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label for="problem_description" class="form-label">Décrivez votre problème *</label><textarea class="form-control" id="problem_description" name="problem_description" rows="4" required placeholder="Ex: Bruit étrange au freinage, la voiture ne démarre pas..."></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i>Envoyer la demande</button></div></form></div></div></div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>