<?php

declare(strict_types=1);

require_once 'includes/app.php';
require_once 'UserManager.php';

session_start();

$config = app_config();

// If already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $userManager = new UserManager(
            $config['storage']['database_path'],
            $config['users']['default_users'] ?? []
        );
        $user = $userManager->authenticate($email, $password);

        if ($user) {
            // Login successful
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = $user['email']; // Using email as unique ID now
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['is_admin'] = $user['is_admin'];

            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= h($config['title_suffix']) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style><?= app_theme_style($config['theme']) ?></style>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="d-flex align-items-center py-4 bg-light min-vh-100">

<main class="form-signin w-100 m-auto" style="max-width: 400px; padding: 15px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <?php if (!empty($config['logo_path'])): ?>
                    <img class="mb-3" src="<?= h(app_asset($config['logo_path'])) ?>" alt="<?= h($config['logo_alt']) ?>" height="50">
                <?php endif; ?>
                <h1 class="h3 mb-3 fw-normal">Sign In</h1>
                <p class="text-muted">Enter your credentials to access the feedback system.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-floating mb-3">
                    <input type="email" class="form-control" id="floatingInput" name="email" placeholder="name@example.com" required autofocus value="<?= h($_POST['email'] ?? '') ?>">
                    <label for="floatingInput">Email address</label>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" required>
                    <label for="floatingPassword">Password</label>
                </div>

                <button class="w-100 btn btn-lg btn-primary mt-3" type="submit">Sign in</button>
            </form>
        </div>
    </div>
</main>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
