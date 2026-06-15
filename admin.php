<?php

declare(strict_types=1);

require_once 'includes/app.php';
require_once 'UserManager.php';

session_start();

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    http_response_code(403);
    die("Forbidden. You do not have permission to access this page.");
}

$config = app_config();
$userManager = new UserManager($config['storage']['database_path']);

$message = '';
$messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear_cache') {
        $cacheDir = $config['storage']['cache_dir'];
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            $deletedCount = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                    $deletedCount++;
                }
            }
            $message = "Successfully cleared $deletedCount cached file(s).";
        } else {
            $message = "Cache directory not found or already empty.";
            $messageType = 'info';
        }
    } elseif ($action === 'reset_comments') {
        // Only delete comments from database.sqlite, preserve users.
        // Or actually, deleting database.sqlite deletes users too now!
        // We need to drop the comments table instead of deleting the file.
        $dbPath = $config['storage']['database_path'];
        try {
            $pdo = new PDO("sqlite:" . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("DROP TABLE IF EXISTS comments");
            $message = "Successfully wiped all comments. The table will be recreated automatically.";
        } catch (Exception $e) {
            $message = "Error wiping comments: " . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($action === 'sync_comments') {
        require_once 'includes/MarkdownInjector.php';
        $cm = new CommentManager($config['storage']['database_path']);
        $injector = new MarkdownInjector($cm);

        $totalSynced = 0;
        try {
            foreach ($config['content']['documents'] as $docId => $docConfig) {
                $totalSynced += $injector->syncComments($docId, $docConfig['path'], (int) ($config['content']['split_level'] ?? 2), $config);
            }
            $message = "Successfully synced $totalSynced comments back to the markdown source files.";
        } catch (Exception $e) {
            $message = "Error syncing comments: " . $e->getMessage();
            $messageType = 'danger';
        }
    } elseif ($action === 'create_user') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $isAdmin = isset($_POST['is_admin']) && $_POST['is_admin'] === '1';

        if (empty($name) || empty($email) || empty($password)) {
            $message = "Please fill all required fields to create a user.";
            $messageType = 'danger';
        } else {
            if ($userManager->createUser($name, $email, $password, $isAdmin)) {
                $message = "User '$name' successfully created.";
            } else {
                $message = "Failed to create user. That email might already be in use.";
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'bulk_upload') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['csv_file']['tmp_name'];
            $successCount = 0;
            $failCount = 0;

            if (($handle = fopen($tmpName, 'r')) !== false) {
                $row = 0;
                while (($data = fgetcsv($handle, 1000, ",", "\"", "\\")) !== false) {
                    $row++;
                    // Basic 3 column check: Name, Email, Password
                    if (count($data) >= 3) {
                        $name = trim($data[0]);
                        $email = trim($data[1]);
                        $password = trim($data[2]);

                        // Skip header row heuristic
                        if ($row === 1 && strtolower($name) === 'name' && strtolower($email) === 'email') {
                            continue;
                        }

                        if (!empty($name) && filter_var($email, FILTER_VALIDATE_EMAIL) && !empty($password)) {
                            if ($userManager->createUser($name, $email, $password, false)) {
                                $successCount++;
                            } else {
                                $failCount++;
                            }
                        } else {
                            $failCount++; // Invalid format
                        }
                    } else {
                        $failCount++; // Not enough columns
                    }
                }
                fclose($handle);

                $message = "Bulk Upload Complete! Successfully created $successCount users.";
                if ($failCount > 0) {
                    $message .= " Skipped $failCount rows (invalid format or email already exists).";
                    if ($successCount === 0) {
                        $messageType = 'danger';
                    } else {
                        $messageType = 'warning';
                    }
                }
            } else {
                $message = "Failed to read the uploaded CSV file.";
                $messageType = 'danger';
            }
        } else {
            $message = "Please select a valid CSV file to upload.";
            $messageType = 'danger';
        }
    } elseif ($action === 'delete_user') {
        $id = (int) ($_POST['id'] ?? 0);

        // Prevent deleting yourself
        $currentUser = $userManager->authenticate($_SESSION['user_email'], 'placeholder'); // Just check existence maybe, or simpler:
        // Actually we don't have user ID in session, we have user_email.
        // Let's get the user being deleted to check.
        $stmt = (new PDO("sqlite:" . $config['storage']['database_path']))->prepare("SELECT email FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($targetUser && $targetUser['email'] === $_SESSION['user_email']) {
            $message = "You cannot delete your own account.";
            $messageType = 'danger';
        } else {
            if ($userManager->deleteUser($id)) {
                $message = "User successfully deleted.";
            } else {
                $message = "Failed to delete user.";
                $messageType = 'danger';
            }
        }
    }
}

$users = $userManager->getUsers();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= h($config['title_suffix']) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        <?= app_theme_style($config['theme']) ?>
    </style>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
</head>

<body class="d-flex flex-column min-vh-100 bg-light">

    <nav class="navbar navbar-expand-lg navbar-dark shadow-sm sticky-top app-navbar">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold d-flex align-items-center" href="index.php">
                &larr; Back to Site
            </a>
            <div class="d-flex align-items-center">
                <span class="text-white me-3 d-none d-md-inline">Welcome,
                    <?= h($_SESSION['user_name'] ?? 'Admin') ?></span>
                <a href="help/index.html" target="_blank" class="btn btn-warning btn-sm me-2 fw-bold">Help &
                    Docs</a>
                <a href="logout.php" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="max-width: 1200px;">
        <h2 class="mb-4">System Administration</h2>

        <?php if ($message): ?>
            <div class="alert alert-<?= h($messageType) ?> alert-dismissible fade show" role="alert">
                <?= h($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- System Actions -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body p-4 text-center">
                        <h5 class="card-title fw-bold">Clear System Cache</h5>
                        <p class="card-text text-muted mb-4 small">
                            Removes all the rendered HTML cache files. You can use this if you have updated the markdown
                            documents.
                        </p>
                        <form method="POST" action="admin.php">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="btn btn-outline-primary w-100">Clear Cache</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-success h-100">
                    <div class="card-body p-4 text-center">
                        <h5 class="card-title fw-bold text-success">Sync Comments to Documents</h5>
                        <p class="card-text text-muted mb-4 small">
                            Injects database comments into the original markdown documents for offline reading and
                            version control.
                        </p>
                        <form method="POST" action="admin.php">
                            <input type="hidden" name="action" value="sync_comments">
                            <button type="submit" class="btn btn-outline-success w-100">Sync to Markdown</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 border-danger h-100">
                    <div class="card-body p-4 text-center">
                        <h5 class="card-title fw-bold text-danger">Reset All Comments</h5>
                        <p class="card-text text-muted mb-4 small">
                            Permanently erases all comments from the database. User accounts are NOT deleted. <strong
                                class="text-danger">Cannot be
                                undone!</strong>.
                        </p>
                        <form method="POST" action="admin.php"
                            onsubmit="return confirm('Are you absolutely sure you want to permanently delete all comments? This cannot be undone.');">
                            <input type="hidden" name="action" value="reset_comments">
                            <button type="submit" class="btn btn-outline-danger w-100">Wipe Comments</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- User Management Section -->
        <div class="card shadow-sm border-0 mb-5">
            <div class="card-header bg-white border-bottom pt-4 pb-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">User Management</h4>
                    <div>
                        <button type="button" class="btn btn-outline-secondary btn-sm me-2" data-bs-toggle="modal"
                            data-bs-target="#bulkUploadModal">
                            Bulk Upload User's via CSV
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                            data-bs-target="#addUserModal">
                            + Add New User
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="ps-4 fw-medium"><?= h($u['name']) ?></td>
                                    <td><?= h($u['email']) ?></td>
                                    <td>
                                        <?php if ($u['is_admin']): ?>
                                            <span class="badge bg-primary">Admin</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Client</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ($u['email'] !== $_SESSION['user_email']): ?>
                                            <form method="POST" action="admin.php" class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to revoke access for <?= h($u['name']) ?>?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">No users found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="admin.php">
                    <input type="hidden" name="action" value="create_user">
                    <div class="modal-header border-bottom-0">
                        <h5 class="modal-title fw-bold" id="addUserModalLabel">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                placeholder="e.g. John Doe">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required
                                placeholder="name@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="text" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-check form-switch mt-4">
                            <input class="form-check-input" type="checkbox" role="switch" id="is_admin" name="is_admin"
                                value="1">
                            <label class="form-check-label" for="is_admin">Grant Admin Access</label>
                            <div class="form-text">Admins can manage other users and clear the system.</div>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel"
        aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="admin.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="bulk_upload">
                    <div class="modal-header border-bottom-0">
                        <h5 class="modal-title fw-bold" id="bulkUploadModalLabel">Bulk Upload Users</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-4">
                            Upload a CSV file to instantly create multiple user accounts. The file must contain exactly
                            3 columns in the following order:
                        </p>
                        <div class="bg-light p-3 rounded mb-4 font-monospace small">
                            <strong>Name, Email, Password</strong><br>
                            John Doe, john@example.com, secret123<br>
                            Jane Smith, jane@example.com, secure456
                        </div>
                        <div class="mb-3">
                            <label for="csv_file" class="form-label fw-medium">Select CSV File</label>
                            <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv"
                                required>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-secondary px-4">Upload Users</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="text-muted text-center py-4 mt-auto">
        <small>MarkHTML Feedback Admin Panel</small>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>