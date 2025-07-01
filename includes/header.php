<?php
$current_user = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Tableau de bord' ?> - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
           :root { --primary-color: #667eea; --secondary-color:rgb(75, 162, 155); --success-color: #28a745; --warning-color: #ffc107; --danger-color: #dc3545; --info-color: #17a2b8; --dark-color: #2c3e50; }
        body { background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; min-height: 100vh; position: relative; }
        body::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-image: url('images/garage-pattern.png'); background-size: 200px 200px; opacity: 0.03; z-index: -1; pointer-events: none; }
        .navbar-brand { font-weight: bold; color: white !important; font-size: 1.5rem; display: flex; align-items: center; }
        .navbar-brand img { width: 40px; height: 40px; margin-right: 10px; filter: brightness(0) invert(1); }
        .navbar-custom { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.15); border: none; backdrop-filter: blur(10px); }
        .sidebar { background: white; min-height: calc(100vh - 76px); box-shadow: 4px 0 20px rgba(0,0,0,0.1); border-right: none; border-radius: 0 20px 20px 0; position: relative; overflow: hidden; }
        .sidebar::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('images/sidebar-pattern.png') repeat-y; opacity: 0.05; pointer-events: none; }
        .sidebar .nav-link { color: #495057; padding: 1.2rem 1.5rem; border-radius: 0; transition: all 0.3s ease; margin: 0.3rem 0; border-left: 4px solid transparent; font-weight: 500; position: relative; overflow: hidden; }
        .sidebar .nav-link::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent); transition: left 0.5s; }
        .sidebar .nav-link:hover::before { left: 100%; }
        .sidebar .nav-link:hover { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); color: var(--primary-color); border-left: 4px solid var(--primary-color); transform: translateX(8px); box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2); }
        .sidebar .nav-link.active { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border-left: 4px solid var(--secondary-color); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3); transform: translateX(5px); }
        .sidebar .nav-link i { width: 25px; text-align: center; margin-right: 10px; font-size: 1.1rem; }
        .main-content { padding: 2rem; background: transparent; position: relative; }
        .card { border: none; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); transition: all 0.3s ease; background: white; overflow: hidden; position: relative; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); }
        .card:hover { transform: translateY(-8px); box-shadow: 0 20px 50px rgba(0,0,0,0.15); }
        .card-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-bottom: 1px solid #dee2e6; border-radius: 20px 20px 0 0 !important; padding: 1.5rem 2rem; position: relative; }
        .card-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 100%; background: url('images/card-pattern.png') repeat; opacity: 0.05; pointer-events: none; }
        .stat-card { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; overflow: hidden; position: relative; }
        .stat-card::before { content: ''; position: absolute; top: -50%; right: -50%; width: 100%; height: 100%; background: rgba(255,255,255,0.1); border-radius: 50%; transition: all 0.3s ease; }
        .stat-card:hover::before { transform: scale(1.3); }
        .stat-card.success { background: linear-gradient(135deg, #28a745 0%, #20c997 100%); }
        .stat-card.warning { background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%); }
        .stat-card.danger { background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%); }
        .stat-card.info { background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%); }
        .btn-primary { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border: none; border-radius: 12px; padding: 0.75rem 1.5rem; font-weight: 600; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .btn-primary::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s; }
        .btn-primary:hover::before { left: 100%; }
        .btn-primary:hover { background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%); transform: translateY(-3px); box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3); }
        .btn-outline-primary { border: 2px solid var(--primary-color); color: var(--primary-color); border-radius: 12px; font-weight: 600; transition: all 0.3s ease; }
        .btn-outline-primary:hover { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-color: var(--primary-color); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); }
        .table { border-radius: 15px; overflow: hidden; }
        .table th { border-top: none; font-weight: 600; color: #495057; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 1.2rem; position: relative; }
        .table td { padding: 1.2rem; vertical-align: middle; border-top: 1px solid rgba(0,0,0,0.05); }
        .table-hover tbody tr:hover { background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%); transform: scale(1.01); transition: all 0.3s ease; }
        .badge-status { padding: 0.6rem 1rem; border-radius: 25px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; position: relative; overflow: hidden; }
        .badge-status::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); transition: left 1s; }
        .badge-status:hover::before { left: 100%; }
        .status-pending { background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%); color: #2d3436; }
        .status-in-progress { background: linear-gradient(135deg, #74b9ff 0%, #0984e3 100%); color: white; }
        .status-completed { background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); color: white; }
        .status-cancelled { background: linear-gradient(135deg, #ff7675 0%, #e84393 100%); color: white; }
        .status-paid { background: linear-gradient(135deg, #00b894 0%, #00cec9 100%); color: white; }
        .status-unpaid { background: linear-gradient(135deg, #ff7675 0%, #e84393 100%); color: white; }
        .status-overdue { background: linear-gradient(135deg, #fd79a8 0%, #e84393 100%); color: white; }
        .modal-content { border: none; border-radius: 20px; box-shadow: 0 25px 80px rgba(0,0,0,0.3); overflow: hidden; }
        .modal-header { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; border-radius: 20px 20px 0 0; border-bottom: none; padding: 1.5rem 2rem; }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 12px; padding: 0.8rem 1.2rem; transition: all 0.3s ease; background: rgba(248, 249, 250, 0.8); }
        .form-control:focus, .form-select:focus { border-color: var(--primary-color); box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25); background: white; transform: translateY(-2px); }
        .alert { border: none; border-radius: 15px; padding: 1.2rem 1.8rem; position: relative; overflow: hidden; }
        .alert::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: currentColor; opacity: 0.3; }
        .nav-tabs .nav-link { border: none; border-radius: 12px 12px 0 0; margin-right: 0.5rem; color: #6c757d; font-weight: 600; transition: all 0.3s ease; padding: 1rem 1.5rem; }
        .nav-tabs .nav-link.active { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); color: white; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); }
        .dropdown-menu { border: none; border-radius: 15px; box-shadow: 0 15px 40px rgba(0,0,0,0.15); padding: 0.5rem 0; }
        .dropdown-item { padding: 0.8rem 1.5rem; transition: all 0.3s ease; }
        .dropdown-item:hover { background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%); color: var(--primary-color); transform: translateX(5px); }
        .progress { height: 12px; border-radius: 10px; background-color: #e9ecef; overflow: hidden; }
        .progress-bar { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-radius: 10px; transition: all 0.3s ease; }
        .floating-cars { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: -1; }
        .floating-car { position: absolute; width: 30px; height: 30px; opacity: 0.05; animation: float 20s linear infinite; }
        .floating-car:nth-child(1) { top: 10%; left: 5%; animation-delay: 0s; }
        .floating-car:nth-child(2) { top: 30%; right: 10%; animation-delay: 5s; }
        .floating-car:nth-child(3) { bottom: 20%; left: 15%; animation-delay: 10s; }
        .floating-car:nth-child(4) { bottom: 40%; right: 5%; animation-delay: 15s; }
        @keyframes float { 0% { transform: translateY(0px) rotate(0deg); } 25% { transform: translateY(-20px) rotate(90deg); } 50% { transform: translateY(0px) rotate(180deg); } 75% { transform: translateY(-20px) rotate(270deg); } 100% { transform: translateY(0px) rotate(360deg); } }
        ::-webkit-scrollbar { width: 10px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .card, .alert { animation: fadeInUp 0.6s ease-out; }
        @media (max-width: 768px) { .sidebar { border-radius: 0; } .main-content { padding: 1rem; } .card { border-radius: 15px; } .stat-card .h5 { font-size: 1.1rem; } .navbar-brand { font-size: 1.2rem; } .navbar-brand img { width: 30px; height: 30px; } }
        .vehicle-card { background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%); border-left: 4px solid var(--primary-color); transition: all 0.3s ease; }
        .vehicle-card:hover { border-left: 4px solid var(--secondary-color); box-shadow: 0 15px 40px rgba(102, 126, 234, 0.2); }
        .vehicle-icon { width: 60px; height: 60px; background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.5rem; }
    </style>
</head>
<body>
    <div class="floating-cars">
        <img src="images/car-float-1.png" class="floating-car" alt="" onerror="this.style.display='none'">
        <img src="images/car-float-2.png" class="floating-car" alt="" onerror="this.style.display='none'">
        <img src="images/car-float-3.png" class="floating-car" alt="" onerror="this.style.display='none'">
        <img src="images/car-float-4.png" class="floating-car" alt="" onerror="this.style.display='none'">
    </div>

    <nav class="navbar navbar-expand-lg navbar-dark navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="images/garage-logo.png" alt="Logo" onerror="this.style.display='none'">
                <i class="fas fa-tools me-2"></i>
                <?= SITE_NAME ?>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white d-flex align-items-center" href="#" role="button" data-bs-toggle="dropdown">
                        <div class="me-2">
                            <i class="fas fa-user-circle fa-lg me-1"></i>
                            <?= htmlspecialchars($current_user['full_name'] ?? $current_user['username']) ?>
                        </div>
                        <span class="badge bg-light text-dark">
                            <?php 
                                switch($current_user['role']) {
                                    case 'admin': echo 'Administrateur'; break;
                                    case 'mechanic': echo 'Mécanicien'; break;
                                    case 'user': echo 'Client'; break;
                                }
                            ?>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Déconnexion
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- MODIFIED: Role-based sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
                                <i class="fas fa-tachometer-alt"></i>
                                Tableau de bord
                            </a>
                        </li>
                        
                        <?php if ($current_user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($current_page, ['clients.php', 'client_profile.php']) ? 'active' : '' ?>" href="clients.php">
                                <i class="fas fa-users"></i>
                                Clients
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $current_page == 'inventory.php' ? 'active' : '' ?>" href="inventory.php">
                                <i class="fas fa-boxes"></i>
                                Inventaire
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($current_page, ['invoices.php', 'print_invoice.php']) ? 'active' : '' ?>" href="invoices.php">
                                <i class="fas fa-file-invoice-dollar"></i>
                                Factures
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($current_page, ['repairs.php', 'repair.php', 'new_repair.php']) ? 'active' : '' ?>" href="repairs.php">
                                <i class="fas fa-wrench"></i>
                                Réparations
                            </a>
                        </li>
                        <?php elseif ($current_user['role'] == 'user'): ?>
                       
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($current_page, ['repairs.php', 'repair.php']) ? 'active' : '' ?>" href="repairs.php">
                                <i class="fas fa-wrench"></i>
                                Mes Réparations
                            </a>
                        </li>
                        <?php else:  ?>
                        <li class="nav-item">
                            <a class="nav-link <?= in_array($current_page, ['repairs.php', 'repair.php']) ? 'active' : '' ?>" href="repairs.php">
                                <i class="fas fa-wrench"></i>
                                Réparations
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 main-content">