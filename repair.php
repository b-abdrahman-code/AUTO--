<?php
require_once 'config.php';
requireLogin();

$page_title = 'Détails de la réparation';
$current_user = getCurrentUser();
$repair_id = (int)($_GET['id'] ?? 0);

if (!$repair_id) {
    header('Location: repairs.php');
    exit();
}

$pdo = getDBConnection();
$message = '';
$error = '';

$loggedInMechanicId = null;
if ($current_user['role'] == 'mechanic') {
    $stmt = $pdo->prepare("SELECT mechanic_id FROM Mechanics WHERE user_id = ?");
    $stmt->execute([$current_user['user_id']]);
    $mechanic_user = $stmt->fetch();
    if ($mechanic_user) {
        $loggedInMechanicId = $mechanic_user['mechanic_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    $is_admin = $current_user['role'] == 'admin';

    $stmt = $pdo->prepare("SELECT mechanic_id, status FROM Repairs WHERE repair_id = ?");
    $stmt->execute([$repair_id]);
    $repair_context = $stmt->fetch();
    
    $is_assigned_mechanic_context = ($repair_context && $loggedInMechanicId && $repair_context['mechanic_id'] == $loggedInMechanicId);
    $mechanic_allowed = $is_assigned_mechanic_context && in_array($action, ['add_task', 'add_part', 'update_status']);
    $admin_allowed = $is_admin && in_array($action, ['assign_mechanic', 'update_wash_cost']);

    if ($mechanic_allowed) {
        switch ($action) {
            case 'update_status':
                $new_status = sanitizeInput($_POST['status']);
                if (in_array($new_status, ['In Progress', 'Completed'])) {
                    $completion_date = ($new_status == 'Completed') ? date('Y-m-d') : null;
                    $stmt = $pdo->prepare("UPDATE Repairs SET status = ?, completion_date = ? WHERE repair_id = ?");
                    if ($stmt->execute([$new_status, $completion_date, $repair_id])) {
                        $message = "Statut de la réparation mis à jour!";
                    } else { $error = "Échec de la mise à jour."; }
                }
                break;
            case 'add_task':
                if ($repair_context['status'] == 'In Progress') {
                    $description = sanitizeInput($_POST['description']);
                    $cost = (float)$_POST['cost'];
                    $stmt = $pdo->prepare("INSERT INTO Tasks (repair_id, description, cost) VALUES (?, ?, ?)");
                    if ($stmt->execute([$repair_id, $description, $cost])) { $message = "Tâche ajoutée avec succès!"; } else { $error = "Échec de l'ajout."; }
                } else { $error = "Vous ne pouvez ajouter des tâches que si la réparation est 'En cours'."; }
                break;
            case 'add_part':
                 if ($repair_context['status'] == 'In Progress') {
                    $part_id = (int)$_POST['part_id'];
                    $quantity = (int)$_POST['quantity'];
                    $stmt = $pdo->prepare("SELECT quantity_in_stock, price_per_unit FROM Parts WHERE part_id = ?");
                    $stmt->execute([$part_id]);
                    $part = $stmt->fetch();
                    if ($part && $part['quantity_in_stock'] >= $quantity) {
                         $stmt = $pdo->prepare("INSERT INTO Repair_Parts (repair_id, part_id, quantity_used, price_at_time_of_use) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity_used = quantity_used + VALUES(quantity_used)");
                         if ($stmt->execute([$repair_id, $part_id, $quantity, $part['price_per_unit']])) {
                             $stmt = $pdo->prepare("UPDATE Parts SET quantity_in_stock = quantity_in_stock - ? WHERE part_id = ?");
                             $stmt->execute([$quantity, $part_id]);
                             $message = "Pièce ajoutée!";
                         } else { $error = "Échec ajout pièce."; }
                    } else { $error = "Stock insuffisant."; }
                } else { $error = "Vous ne pouvez ajouter des pièces que si la réparation est 'En cours'."; }
                break;
        }
    } elseif ($admin_allowed) {
        if ($action == 'assign_mechanic') {
            $mechanic_id = !empty($_POST['mechanic_id']) ? (int)$_POST['mechanic_id'] : null;
            if ($repair_context['status'] == 'Pending') {
                $stmt = $pdo->prepare("UPDATE Repairs SET mechanic_id = ? WHERE repair_id = ?");
                if ($stmt->execute([$mechanic_id, $repair_id])) {
                    $message = "Mécanicien assigné avec succès!";
                } else { $error = "Échec de l'assignation."; }
            } else { $error = "Impossible de changer de mécanicien car la réparation n'est plus en attente."; }
        } elseif ($action == 'update_wash_cost') {
            $wash_cost = (float)($_POST['wash_cost'] ?? 0);
            $allowed_costs = [0, 700, 1200, 2000];

            if (in_array($wash_cost, $allowed_costs)) {
                $stmt = $pdo->prepare("UPDATE Repairs SET wash_cost = ? WHERE repair_id = ?");
                if ($stmt->execute([$wash_cost, $repair_id])) {
                    $message = "Option de lavage mise à jour avec succès!";
                } else {
                    $error = "Échec de la mise à jour de l'option de lavage.";
                }
            } else {
                $error = "Coût de lavage non valide.";
            }
        }
    } else {
        $error = "Action non autorisée.";
    }
}

try {
    $stmt = $pdo->prepare("
        SELECT r.*, c.name as client_name, c.phone as client_phone, c.user_id as client_user_id,
               v.license_plate, v.brand, v.model, m.name as mechanic_name
        FROM Repairs r 
        JOIN Vehicles v ON r.vehicle_id = v.vehicle_id 
        JOIN Clients c ON v.client_id = c.client_id
        LEFT JOIN Mechanics m ON r.mechanic_id = m.mechanic_id
        WHERE r.repair_id = ?
    ");
    $stmt->execute([$repair_id]);
    $repair = $stmt->fetch();
    
    if (!$repair) throw new Exception("Réparation non trouvée.");
    
    $is_assigned_mechanic = ($repair && $loggedInMechanicId && $repair['mechanic_id'] == $loggedInMechanicId);

    if ($current_user['role'] == 'mechanic' && !$is_assigned_mechanic && $current_user['role'] != 'admin') {
         header('Location: repairs.php'); exit();
    }
    if ($current_user['role'] == 'user' && $repair['client_user_id'] != $current_user['user_id']) {
        header('Location: repairs.php'); exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM Tasks WHERE repair_id = ? ORDER BY created_at");
    $stmt->execute([$repair_id]);
    $tasks = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("SELECT rp.*, p.designation FROM Repair_Parts rp JOIN Parts p ON rp.part_id = p.part_id WHERE rp.repair_id = ?");
    $stmt->execute([$repair_id]);
    $used_parts = $stmt->fetchAll();

    $parts = []; $mechanics = [];
    if ($current_user['role'] == 'mechanic') {
        $stmt_parts = $pdo->query("SELECT part_id, designation, quantity_in_stock FROM Parts WHERE quantity_in_stock > 0 ORDER BY designation");
        $parts = $stmt_parts->fetchAll();
    }
    if ($current_user['role'] == 'admin') {
        $stmt_mechanics = $pdo->query("
            SELECT m.mechanic_id, m.name, COUNT(r.repair_id) as active_repairs
            FROM Mechanics m
            LEFT JOIN Repairs r ON m.mechanic_id = r.mechanic_id AND r.status IN ('Pending', 'In Progress') AND r.repair_id != $repair_id
            GROUP BY m.mechanic_id, m.name ORDER BY m.name
        ");
        $mechanics = $stmt_mechanics->fetchAll();
    }

} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$labor_cost = array_sum(array_column($tasks, 'cost'));
$parts_cost = array_reduce($used_parts, fn($sum, $p) => $sum + ($p['quantity_used'] * $p['price_at_time_of_use']), 0);
$total_cost = $labor_cost + $parts_cost + $repair['wash_cost'];

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2"><i class="fas fa-wrench me-2"></i>Réparation #<?= $repair['repair_id'] ?><span class="badge badge-status status-<?= strtolower(str_replace(' ', '-', $repair['status'])) ?> ms-2"><?= $repair['status'] ?></span></h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if ($is_assigned_mechanic): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="update_status">
                <?php if ($repair['status'] == 'Pending'): ?>
                    <button type="submit" name="status" value="In Progress" class="btn btn-primary"><i class="fas fa-play me-1"></i> Démarrer la réparation</button>
                <?php elseif ($repair['status'] == 'In Progress'): ?>
                     <button type="submit" name="status" value="Completed" class="btn btn-success"><i class="fas fa-check me-1"></i> Terminer la réparation</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
        <?php if ($current_user['role'] == 'admin' && $repair['status'] == 'Completed'): ?>
            <a href="generate_invoice.php?repair_id=<?= $repair_id ?>" class="btn btn-success"><i class="fas fa-file-invoice-dollar me-1"></i> Générer la facture</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($message): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Repair Information Card -->
        <div class="card mb-4">
            <div class="card-header"><h5><i class="fas fa-info-circle me-2"></i>Informations sur la réparation</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Client:</strong> <?= htmlspecialchars($repair['client_name']) ?></p>
                        <p><strong>Véhicule:</strong> <?= htmlspecialchars($repair['brand'] . ' ' . $repair['model']) ?> (<?= htmlspecialchars($repair['license_plate']) ?>)</p>
                        <p><strong>Date:</strong> <?= formatDate($repair['start_date']) ?></p>
                    </div>
                    <div class="col-md-6">
                         <p><strong>Mécanicien:</strong> <span class="badge bg-info fs-6"><?= htmlspecialchars($repair['mechanic_name'] ?? 'Non assigné') ?></span></p>
                         <p><strong>Description du problème:</strong></p>
                         <div class="bg-light p-2 rounded" style="min-height: 50px;"><?= nl2br(htmlspecialchars($repair['problem_description'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Admin Actions Section -->
        <?php if ($current_user['role'] == 'admin'): ?>
        <div class="card mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-user-shield me-2"></i>Actions Administrateur</h5></div>
            <div class="card-body">
                <!-- Mechanic Assignment -->
                <div class="mb-4">
                    <label for="mechanic_id" class="form-label fw-bold">Assigner un mécanicien</label>
                    <?php if ($repair['status'] == 'Pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="assign_mechanic">
                            <div class="input-group">
                                <select class="form-select" id="mechanic_id" name="mechanic_id">
                                    <option value="">Non assigné</option>
                                    <?php foreach ($mechanics as $mechanic): ?>
                                        <option value="<?= $mechanic['mechanic_id'] ?>" <?= $mechanic['mechanic_id'] == $repair['mechanic_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($mechanic['name']) ?> (<?= $mechanic['active_repairs'] ?> en cours)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-outline-primary"><i class="fas fa-user-check me-1"></i> Mettre à jour</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <p class="text-muted mb-0">L'assignation ne peut être modifiée car la réparation est déjà en cours ou terminée.</p>
                    <?php endif; ?>
                </div>
                <!-- Wash Service Option -->
                <div>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_wash_cost">
                        <label for="wash_cost" class="form-label fw-bold">Option de lavage</label>
                        <div class="input-group">
                            <select class="form-select" id="wash_cost" name="wash_cost">
                                <option value="0" <?= $repair['wash_cost'] == 0 ? 'selected' : '' ?>>Pas de lavage</option>
                                <option value="700" <?= $repair['wash_cost'] == 700 ? 'selected' : '' ?>>Lavage Simple (<?= formatCurrency(700) ?>)</option>
                                <option value="1200" <?= $repair['wash_cost'] == 1200 ? 'selected' : '' ?>>Lavage Premium (<?= formatCurrency(1200) ?>)</option>
                                <option value="2000" <?= $repair['wash_cost'] == 2000 ? 'selected' : '' ?>>Lavage Complet Int/Ext (<?= formatCurrency(2000) ?>)</option>
                            </select>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i> Enregistrer</button>
                        </div>
                        <div class="form-text">Modifiable à tout moment. Ce coût sera ajouté à la facture finale.</div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tasks Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-tasks me-2"></i>Tâches effectuées (Main d'œuvre)</h5>
                <?php if ($is_assigned_mechanic && $repair['status'] == 'In Progress'): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal"><i class="fas fa-plus"></i> Ajouter</button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Description</th><th class="text-end">Coût</th></tr></thead><tbody>
                    <?php foreach ($tasks as $task): ?>
                        <tr><td><?= htmlspecialchars($task['description']) ?></td><td class="text-end"><?= formatCurrency($task['cost']) ?></td></tr>
                    <?php endforeach; if (empty($tasks)): ?><tr><td colspan="2" class="text-center text-muted py-3">Aucune tâche ajoutée.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </div>

        <!-- Parts Section -->
        <div class="card mb-4">
             <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-cogs me-2"></i>Pièces utilisées</h5>
                <?php if ($is_assigned_mechanic && $repair['status'] == 'In Progress'): ?>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal"><i class="fas fa-plus"></i> Ajouter</button>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Pièce</th><th class="text-center">Quantité</th><th class="text-end">Prix Unitaire</th><th class="text-end">Total</th></tr></thead><tbody>
                    <?php foreach ($used_parts as $part): ?>
                        <tr><td><?= htmlspecialchars($part['designation']) ?></td><td class="text-center"><?= $part['quantity_used'] ?></td><td class="text-end"><?= formatCurrency($part['price_at_time_of_use']) ?></td><td class="text-end"><?= formatCurrency($part['quantity_used'] * $part['price_at_time_of_use']) ?></td></tr>
                    <?php endforeach; if (empty($used_parts)): ?><tr><td colspan="4" class="text-center text-muted py-3">Aucune pièce ajoutée.</td></tr><?php endif; ?>
                </tbody></table></div>
            </div>
        </div>
    </div>

    <!-- Costs Summary Column -->
    <div class="col-lg-4">
        <div class="card sticky-top" style="top: 20px;">
            <div class="card-header"><h6><i class="fas fa-calculator me-2"></i>Résumé des coûts (préliminaire)</h6></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>Main-d'œuvre:</span><span class="fw-bold"><?= formatCurrency($labor_cost) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span>Pièces:</span><span class="fw-bold"><?= formatCurrency($parts_cost) ?></span></div>
                <?php if ($repair['wash_cost'] > 0): ?>
                <div class="d-flex justify-content-between mb-2"><span>Lavage:</span><span class="fw-bold"><?= formatCurrency($repair['wash_cost']) ?></span></div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between h5">
                    <strong>Total Estimé:</strong>
                    <strong><?= formatCurrency($total_cost) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals for Mechanic -->
<?php if ($is_assigned_mechanic): ?>
<div class="modal fade" id="addTaskModal"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Ajouter une Tâche</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="add_task"><div class="mb-3"><label class="form-label">Description</label><input type="text" class="form-control" name="description" required></div><div class="mb-3"><label class="form-label">Coût Main d'oeuvre</label><div class="input-group"><input type="number" step="0.01" class="form-control" name="cost" required><span class="input-group-text">€</span></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div></form></div></div></div>
<div class="modal fade" id="addPartModal"><div class="modal-dialog"><div class="modal-content"><form method="POST"><div class="modal-header"><h5 class="modal-title">Ajouter une Pièce</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="action" value="add_part"><div class="mb-3"><label class="form-label">Pièce</label><select class="form-select" name="part_id" required><option value="" selected disabled>Choisir une pièce...</option><?php foreach ($parts as $part) echo "<option value='{$part['part_id']}'>".htmlspecialchars($part['designation'])." (Stock: {$part['quantity_in_stock']})</option>"; ?></select></div><div class="mb-3"><label class="form-label">Quantité</label><input type="number" class="form-control" name="quantity" min="1" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button><button type="submit" class="btn btn-primary">Ajouter</button></div></form></div></div></div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>