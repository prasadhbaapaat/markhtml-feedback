<?php

class UserManager {
    private $pdo;

    public function __construct($dbPath, array $defaultUsers = []) {
        $dbDir = dirname($dbPath);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0775, true);
        }

        $this->pdo = new PDO("sqlite:" . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDb();

        if (!empty($defaultUsers)) {
            $this->seedUsers($defaultUsers);
        }
    }

    private function initDb() {
        $query = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                is_admin INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($query);
    }

    private function seedUsers(array $users) {
        foreach ($users as $user) {
            $name = trim((string) ($user['name'] ?? ''));
            $email = trim((string) ($user['email'] ?? ''));
            $password = (string) ($user['password'] ?? '');

            if ($name === '' || $email === '' || $password === '') {
                continue; // skip incomplete default_users entries
            }

            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if (!$stmt->fetch()) {
                $this->createUser($name, $email, $password, $user['is_admin'] ?? false);
            }
        }
    }

    /**
     * Authenticate a user by email and password
     */
    public function authenticate($email, $password) {
        $stmt = $this->pdo->prepare("SELECT id, name, email, password_hash, is_admin FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']); // Don't return the hash
            return $user;
        }

        return false;
    }

    /**
     * Create a new user
     */
    public function createUser($name, $email, $password, $isAdmin = false) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO users (name, email, password_hash, is_admin) VALUES (:name, :email, :hash, :is_admin)");
        
        try {
            return $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':hash' => $hash,
                ':is_admin' => $isAdmin ? 1 : 0
            ]);
        } catch (PDOException $e) {
            // Handle unique constraint failure (email already exists)
            if ($e->getCode() == 23000) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Get all users
     */
    public function getUsers() {
        $stmt = $this->pdo->query("SELECT id, name, email, is_admin, created_at FROM users ORDER BY name ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get a user by ID
     */
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Delete a user by ID
     */
    public function deleteUser($id) {
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
