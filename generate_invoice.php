<?php
require_once 'config.php';
requireAdmin();

$repair_id = (int)($_GET['repair_id'] ?? 0);

if (!$repair_id) {
    header('Location: repairs.php');
    exit();
}

try {
    $pdo = getDBConnection();
    
    $stmt = $pdo->prepare("SELECT status FROM Repairs WHERE repair_id = ?");
    $stmt->execute([$repair_id]);
    $repair = $stmt->fetch();
    
    if (!$repair || $repair['status'] != 'Completed') {
        header('Location: repair.php?id=' . $repair_id);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT invoice_id FROM Invoices WHERE repair_id = ?");
    $stmt->execute([$repair_id]);
    $existing_invoice = $stmt->fetch();
    
    if ($existing_invoice) {
        header('Location: print_invoice.php?id=' . $existing_invoice['invoice_id']);
        exit();
    }
    
    $stmt = $pdo->prepare("
        SELECT
          (SELECT COALESCE(SUM(cost), 0) FROM Tasks WHERE repair_id = :repair_id) as labor_cost,
          (SELECT COALESCE(SUM(quantity_used * price_at_time_of_use), 0) FROM Repair_Parts WHERE repair_id = :repair_id) as parts_cost,
          wash_cost
        FROM Repairs
        WHERE repair_id = :repair_id
    ");
    $stmt->execute(['repair_id' => $repair_id]);
    $costs = $stmt->fetch();
        
    $subtotal = $costs['labor_cost'] + $costs['parts_cost'] + $costs['wash_cost'];
    $tax_rate = 0; 
    $tax_amount = $subtotal * $tax_rate;
    $total_amount = $subtotal + $tax_amount;
    
    $stmt = $pdo->prepare("
        INSERT INTO Invoices (repair_id, amount, tax_amount, total_amount, issue_date, payment_status)
        VALUES (?, ?, ?, ?, CURDATE(), 'Unpaid')
    ");
    
    if ($stmt->execute([$repair_id, $subtotal, $tax_amount, $total_amount])) {
        $invoice_id = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("UPDATE Repairs SET total_cost = ? WHERE repair_id = ?");
        $stmt->execute([$total_amount, $repair_id]);
        
        header('Location: print_invoice.php?id=' . $invoice_id);
        exit();
    } else {
        $error = "Failed to generate invoice.";
    }
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

header('Location: repair.php?id=' . $repair_id . '&error=' . urlencode($error ?? 'Unknown error'));
exit();
?>