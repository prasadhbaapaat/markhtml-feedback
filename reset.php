<?php

declare(strict_types=1);

// Ensure this script is only run from the command line to prevent unauthorized web access
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die("Error: This script can only be executed from the command line (CLI).\n");
}

$rootPath = __DIR__;
$dbPath = $rootPath . '/storage/database.sqlite';
$cacheDir = $rootPath . '/storage/cache';

echo "MarKHTML Feedback - Reset Utility\n";
echo "=================================\n\n";

// 1. Delete the SQLite Database
if (file_exists($dbPath)) {
    if (unlink($dbPath)) {
        echo "[OK] Deleted database: storage/database.sqlite\n";
    } else {
        echo "[ERROR] Failed to delete database. Check file permissions.\n";
    }
} else {
    echo "[INFO] Database already empty or does not exist.\n";
}

// 2. Clear the Cache Directory
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*');
    $deletedCount = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            $deletedCount++;
        }
    }
    echo "[OK] Cleared $deletedCount cached file(s) from storage/cache/\n";
} else {
    echo "[INFO] Cache directory is already empty or does not exist.\n";
}

echo "\nReset complete! The system will automatically recreate the database and cache on the next page load.\n";
