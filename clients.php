<?php
require_once 'config.php';
requireAdmin();

$page_title = 'Client Management';
$message = '';
$error = '';

try {
    $pdo = getDBConnection();
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_client':
                    $name = sanitizeInput($_POST['name']);
                    $phone = sanitizeInput($_POST['phone']);
                    $address = sanitizeInput($_POST['address']);
                    
                    if (!empty($name) && !empty($phone)) {
                        $stmt = $pdo->prepare("INSERT INTO Clients (name, phone, address) VALUES (?, ?, ?)");
                        if ($stmt->execute([$name, $phone, $address])) {
                            $message = "Client added successfully!";
                        } else {
                            $error = "Failed to add client.";
                        }
                    } else {
                        $error = "Name and phone are required.";
                    }
                    break;
                    
                case 'edit_client':
                    $client_id = (int)$_POST['client_id'];
                    $name = sanitizeInput($_POST['name']);
                    $phone = sanitizeInput($_POST['phone']);
                    $address = sanitizeInput($_POST['address']);
                    
                    if (!empty($name) && !empty($phone)) {
                        $stmt = $pdo->prepare("UPDATE Clients SET name = ?, phone = ?, address = ? WHERE client_id = ?");
                        if ($stmt->execute([$name, $phone, $address, $client_id])) {
                            $message = "Client updated successfully!";
                        } else {
                            $error = "Failed to update client.";
                        }
                    } else {
                        $error = "Name and phone are required.";
                    }
                    break;
                    
                case 'delete_client':
                    $client_id = (int)$_POST['client_id'];
                    $stmt = $pdo->prepare("DELETE FROM Clients WHERE client_id = ?");
                    if ($stmt->execute([$client_id])) {
                        $message = "Client deleted successfully!";
                    } else {
                        $error = "Failed to delete client.";
                    }
                    break;
            }
        }
    }
    
    $stmt = $pdo->query("
        SELECT c.*, COUNT(v.vehicle_id) as vehicle_count
        FROM Clients c
        LEFT JOIN Vehicles v ON c.client_id = v.client_id
        GROUP BY c.client_id
        ORDER BY c.name
    ");
    $clients = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="fas fa-users me-2"></i>
        Client Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClientModal">
            <i class="fas fa-user-plus me-1"></i>
            Add New Client
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

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-list me-2"></i>
            All Clients
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($clients)): ?>
        <p class="text-muted">No clients found. Add your first client using the button above.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Vehicles</th>
                        <th>Member Since</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td>#<?= $client['client_id'] ?></td>
                        <td><?= htmlspecialchars($client['name']) ?></td>
                        <td><?= htmlspecialchars($client['phone']) ?></td>
                        <td><?= htmlspecialchars($client['address'] ?: 'N/A') ?></td>
                        <td>
                            <span class="badge bg-info"><?= $client['vehicle_count'] ?></span>
                        </td>
                        <td><?= formatDate($client['created_at']) ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="client_profile.php?id=<?= $client['client_id'] ?>" 
                                   class="btn btn-sm btn-outline-primary" title="View Profile">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                        onclick="editClient(<?= htmlspecialchars(json_encode($client)) ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                        onclick="deleteClient(<?= $client['client_id'] ?>, '<?= htmlspecialchars($client['name']) ?>')" title="Delete">
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

<!-- Add Client Modal -->
<div class="modal fade" id="addClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-plus me-2"></i>
                    Add New Client
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_client">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="phone" name="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Save Client
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Client Modal -->
<div class="modal fade" id="editClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit me-2"></i>
                    Edit Client
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_client">
                    <input type="hidden" name="client_id" id="edit_client_id">
                    
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Full Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" id="edit_phone" name="phone" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_address" class="form-label">Address</label>
                        <textarea class="form-control" id="edit_address" name="address" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>
                        Update Client
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Client Form (Hidden) -->
<form id="deleteClientForm" method="POST" style="display: none;">
    <input type="hidden" name="action" value="delete_client">
    <input type="hidden" name="client_id" id="delete_client_id">
</form>

<script>
function editClient(client) {
    document.getElementById('edit_client_id').value = client.client_id;
    document.getElementById('edit_name').value = client.name;
    document.getElementById('edit_phone').value = client.phone;
    document.getElementById('edit_address').value = client.address || '';
    
    var modal = new bootstrap.Modal(document.getElementById('editClientModal'));
    modal.show();
}

function deleteClient(clientId, clientName) {
    if (confirmDelete('Are you sure you want to delete "' + clientName + '"? This will also delete all their vehicles and repair history.')) {
        document.getElementById('delete_client_id').value = clientId;
        document.getElementById('deleteClientForm').submit();
    }
}
</script>

<?php include 'includes/footer.php'; ?>