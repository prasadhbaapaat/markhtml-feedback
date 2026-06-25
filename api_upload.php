<?php
require_once 'includes/app.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to upload files.']);
    exit;
}

$config = app_config();

if (empty($config['comments']['enabled'])) {
    echo json_encode(['success' => false, 'message' => 'Uploads are currently disabled.']);
    exit;
}

if (!isset($_FILES['attachment'])) {
    echo json_encode(['success' => false, 'message' => 'No files were uploaded.']);
    exit;
}

$uploadDir = __DIR__ . '/storage/uploads/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory.']);
        exit;
    }
}

// Defense-in-depth: stop uploaded files from ever being executed as scripts or MIME-sniffed.
$htaccessPath = $uploadDir . '.htaccess';
if (!is_file($htaccessPath)) {
    $htaccess = <<<'HTACCESS'
# Auto-generated hardening for the user uploads directory.
<IfModule mod_php.c>
    php_admin_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_admin_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_admin_flag engine off
</IfModule>
<FilesMatch "\.(php|phtml|php3|php4|php5|php7|phps|pl|py|cgi|asp|sh)$">
    Require all denied
</FilesMatch>
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
</IfModule>
HTACCESS;
    @file_put_contents($htaccessPath, $htaccess);
}

$files = $_FILES['attachment'];
$isMulti = is_array($files['name']);

$fileCount = $isMulti ? count($files['name']) : 1;
$maxSize = 20 * 1024 * 1024; // 20MB
$allowedExtensions = ['docx', 'doc', 'xlsx', 'xls', 'csv', 'jpg', 'jpeg', 'png', 'webp', 'pdf', 'txt'];

$allowedMimes = [
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
    'text/csv',
    'text/plain',
    'image/jpeg',
    'image/png',
    'image/webp',
    'application/pdf',
    'application/zip',           // .docx / .xlsx are OOXML zip containers (common finfo result)
    'application/csv',           // some systems report .csv as application/csv
    'application/vnd.ms-office', // legacy .doc / .xls
    'application/x-ole-storage', // legacy OLE2 .doc / .xls
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
if ($finfo === false) {
    echo json_encode(['success' => false, 'message' => 'Server configuration error (finfo failed).']);
    exit;
}

$uploadedFiles = [];
$errors = [];

for ($i = 0; $i < $fileCount; $i++) {
    $error = $isMulti ? $files['error'][$i] : $files['error'];
    if ($error === UPLOAD_ERR_NO_FILE) continue;
    
    $name = $isMulti ? $files['name'][$i] : $files['name'];
    
    if ($error !== UPLOAD_ERR_OK) {
        $errors[] = "$name: Upload error code $error.";
        continue;
    }

    $size = $isMulti ? $files['size'][$i] : $files['size'];
    if ($size > $maxSize) {
        $errors[] = "$name exceeds the 20MB size limit.";
        continue;
    }

    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        $errors[] = "$name has an invalid extension.";
        continue;
    }

    $tmpName = $isMulti ? $files['tmp_name'][$i] : $files['tmp_name'];
    $mime = finfo_file($finfo, $tmpName);

    if (!in_array($mime, $allowedMimes, true)) {
        $errors[] = "$name has an invalid MIME type ($mime).";
        continue;
    }

    $newFileName = md5(uniqid('', true) . time() . $i) . '.' . $extension;
    $destination = $uploadDir . $newFileName;

    if (move_uploaded_file($tmpName, $destination)) {
        $uploadedFiles[] = [
            'path' => 'storage/uploads/' . $newFileName,
            'original_name' => $name
        ];
    } else {
        $errors[] = "Failed to save $name.";
    }
}

finfo_close($finfo);

if (empty($uploadedFiles) && !empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Upload processed.',
        'files' => $uploadedFiles,
        'errors' => $errors
    ]);
}
