<?php
require_once 'config.php';

startSession();

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'login') {
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (!empty($username) && !empty($password)) {
                try {
                    $pdo = getDBConnection();
                    $stmt = $pdo->prepare("SELECT user_id, username, password_hash, role, full_name FROM Users WHERE username = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($password, $user['password_hash'])) {
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['full_name'] = $user['full_name'];
                        
                        if ($user['role'] === 'user') {
                            $stmt = $pdo->prepare("SELECT client_id FROM Clients WHERE user_id = ?");
                            $stmt->execute([$user['user_id']]);
                            $client = $stmt->fetch();
                            if ($client) {
                                $_SESSION['client_id'] = $client['client_id'];
                            }
                        }
                        
                        header('Location: index.php');
                        exit();
                    } else {
                        $error = 'Nom d\'utilisateur ou mot de passe incorrect.';
                    }
                } catch (PDOException $e) {
                    $error = 'Erreur de base de données. Veuillez réessayer.';
                }
            } else {
                $error = 'Veuillez remplir tous les champs.';
            }
        } elseif ($_POST['action'] == 'register') {
            $username = sanitizeInput($_POST['reg_username'] ?? '');
            $password = $_POST['reg_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $full_name = sanitizeInput($_POST['full_name'] ?? '');
            $role = sanitizeInput($_POST['role'] ?? '');
            
            if (!empty($username) && !empty($password) && !empty($full_name) && !empty($role)) {
                if ($password === $confirm_password) {
                    if (strlen($password) >= 6) {
                        try {
                            $pdo = getDBConnection();
                            
                            $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE username = ?");
                            $stmt->execute([$username]);
                            
                            if (!$stmt->fetch()) {
                                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $pdo->prepare("INSERT INTO Users (username, password_hash, role, full_name) VALUES (?, ?, ?, ?)");
                                
                                if ($stmt->execute([$username, $password_hash, $role, $full_name])) {
                                    $user_id = $pdo->lastInsertId();
                                    
                                    if ($role == 'mechanic') {
                                        $stmt = $pdo->prepare("INSERT INTO Mechanics (name, user_id, hire_date) VALUES (?, ?, CURDATE())");
                                        $stmt->execute([$full_name, $user_id]);
                                    } elseif ($role == 'user') {
                                        $stmt = $pdo->prepare("INSERT INTO Clients (name, user_id) VALUES (?, ?)");
                                        $stmt->execute([$full_name, $user_id]);
                                    }
                                    
                                    $success = 'Compte créé avec succès! Vous pouvez maintenant vous connecter.';
                                } else {
                                    $error = 'Erreur lors de la création du compte.';
                                }
                            } else {
                                $error = 'Ce nom d\'utilisateur existe déjà.';
                            }
                        } catch (PDOException $e) {
                            $error = 'Erreur de base de données. Veuillez réessayer.';
                        }
                    } else {
                        $error = 'Le mot de passe doit contenir au moins 6 caractères.';
                    }
                } else {
                    $error = 'Les mots de passe ne correspondent pas.';
                }
            } else {
                $error = 'Veuillez remplir tous les champs.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?= SITE_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        #video-bg { position: fixed; right: 0; bottom: 0; min-width: 100%; min-height: 100%; width: auto; height: auto; z-index: -100; object-fit: cover; }
        body { background-color: #333; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; position: relative; overflow-x: hidden; }
        body::before { content: ''; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(10, 20, 30, 0.6); z-index: -99; }
        .login-container { background: rgba(255, 255, 255, 0.1); border-radius: 20px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4); overflow: hidden; width: 100%; max-width: 900px; backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.2); animation: slideUp 0.8s ease-out; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(50px); } to { opacity: 1; transform: translateY(0); } }
        .login-left { background: linear-gradient(135deg, rgba(102, 126, 234, 0.85) 0%, rgba(118, 75, 162, 0.85) 100%); color: white; padding: 3rem; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; }
        .garage-icon { margin-bottom: 2rem; animation: pulse 2.5s infinite ease-in-out; }
        @keyframes pulse { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } }
        .garage-icon img { width: 120px; filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.5)); }
        .login-left h2 { text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.5); }
        .login-left p, .feature-item { text-shadow: 1px 1px 5px rgba(0, 0, 0, 0.5); }
        .login-right { padding: 3rem; background-color: rgba(255, 255, 255, 0.9); }
        .nav-tabs { border: none; margin-bottom: 2rem; }
        .nav-tabs .nav-link { border: none; border-radius: 25px; margin-right: 1rem; padding: 0.75rem 2rem; color: #6c757d; font-weight: 600; transition: all 0.3s ease; background: #f0f2f5; }
        .nav-tabs .nav-link.active { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3); }
        .form-control, .form-select { border: 2px solid #e9ecef; border-radius: 15px; padding: 1rem 1.5rem; font-size: 1rem; transition: all 0.3s ease; background: #f8f9fa; }
        .form-control:focus, .form-select:focus { border-color: #667eea; box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25); background: white; transform: translateY(-2px); }
        .input-group-text { border: 2px solid #e9ecef; border-right: none; border-radius: 15px 0 0 15px; background: #f8f9fa; color: #667eea; font-size: 1.1rem; }
        .input-group .form-control, .input-group .form-select { border-left: none; border-radius: 0 15px 15px 0; }
        .btn-login { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; padding: 1rem 2rem; font-weight: 600; border-radius: 15px; font-size: 1.1rem; transition: all 0.3s ease; position: relative; overflow: hidden; }
        .btn-login::before { content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); transition: left 0.5s; }
        .btn-login:hover::before { left: 100%; }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4); }
        .alert { border: none; border-radius: 15px; padding: 1rem 1.5rem; margin-bottom: 1.5rem; }
        .demo-credentials { background: rgba(102, 126, 234, 0.1); border-radius: 15px; padding: 1.5rem; margin-top: 2rem; border: 2px dashed rgba(102, 126, 234, 0.5); }
        @media (max-width: 768px) { .login-container { margin: 1rem; border-radius: 15px; } .login-left { display: none; } .login-right { border-radius: 15px; } .col-lg-7 { width: 100%; } }
    </style>
</head>
<body>
    
    <video playsinline autoplay muted loop id="video-bg">
        <source src="images/mechanic-bg.mp4" type="video/mp4">
    </video>

    <div class="login-container">
        <div class="row g-0">
            <div class="col-lg-5 login-left d-none d-lg-flex">
                <div class="garage-icon">
                    <img src="images/kkk.png" alt="Garage Logo">
                </div>
                <h2 class="mb-3 display-5 fw-bold"><?= SITE_NAME ?></h2>
                <p class="mb-4 lead">Gestion professionnelle de votre garage automobile</p>
                <div class="features mt-3">
                    <div class="feature-item mb-3 d-flex align-items-center"><i class="fas fa-car fa-fw me-3 fs-5"></i><span>Gestion des véhicules</span></div>
                    <div class="feature-item mb-3 d-flex align-items-center"><i class="fas fa-wrench fa-fw me-3 fs-5"></i><span>Suivi des réparations</span></div>
                    <div class="feature-item mb-3 d-flex align-items-center"><i class="fas fa-users fa-fw me-3 fs-5"></i><span>Gestion des clients</span></div>
                    <div class="feature-item d-flex align-items-center"><i class="fas fa-chart-line fa-fw me-3 fs-5"></i><span>Rapports détaillés</span></div>
                </div>
            </div>
            
            <div class="col-lg-7 login-right">
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert"><i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                
                <ul class="nav nav-tabs" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab"><i class="fas fa-sign-in-alt me-2"></i>Connexion</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab"><i class="fas fa-user-plus me-2"></i>Inscription</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="authTabContent">
                    <div class="tab-pane fade show active" id="login" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="login">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nom d'utilisateur</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($username ?? '') ?>" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="password" class="form-label">Mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-login w-100"><i class="fas fa-sign-in-alt me-2"></i>Se connecter</button>
                        </form>
                        <div class="demo-credentials">
                            <h6 class="text-center mb-3"><i class="fas fa-info-circle me-2"></i>Comptes de démonstration</h6>
                            <div class="row">
                                <div class="col-md-4 text-center mb-2 mb-md-0"><strong>Admin:</strong><br><small>admin / password</small></div>
                                <div class="col-md-4 text-center mb-2 mb-md-0"><strong>Mécanicien:</strong><br><small>mechanic1 / password</small></div>
                                <div class="col-md-4 text-center"><strong>Client:</strong><br><small>client1 / password</small></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-pane fade" id="register" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="register">
                            <div class="mb-3">
                                <label for="reg_username" class="form-label">Nom d'utilisateur</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="reg_username" name="reg_username" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Nom complet</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="role" class="form-label">Je suis un...</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user-cog"></i></span>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="" selected disabled>Choisir un rôle...</option>
                                        <option value="user">Client</option>
                                        <option value="mechanic">Mécanicien</option>
                                        <!-- MODIFIED: Admin role removed from public registration for security -->
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="reg_password" class="form-label">Mot de passe (6+ caractères)</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="reg_password" name="reg_password" required>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirmer le mot de passe</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-login w-100"><i class="fas fa-user-plus me-2"></i>Créer un compte</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>