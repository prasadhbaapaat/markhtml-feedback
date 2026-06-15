<?php
class CommentManager {
    private $pdo;

    public function __construct($dbPath) {
        $dbDir = dirname($dbPath);

        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0775, true);
        }

        $this->pdo = new PDO("sqlite:" . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDb();
    }

    private function initDb() {
        $query = "
            CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                parent_id INTEGER DEFAULT NULL,
                document_id TEXT NOT NULL DEFAULT 'default',
                section_id TEXT NOT NULL,
                page_title TEXT DEFAULT '',
                name TEXT NOT NULL,
                email TEXT DEFAULT '',
                feedback_type TEXT DEFAULT '',
                comment TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'approved',
                resolved INTEGER NOT NULL DEFAULT 0,
                ip_address TEXT DEFAULT '',
                user_agent TEXT DEFAULT '',
                question_id TEXT DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ";
        $this->pdo->exec($query);
        $this->migrateColumns();
    }

    private function migrateColumns() {
        $columns = $this->pdo->query("PRAGMA table_info(comments)")->fetchAll(PDO::FETCH_ASSOC);
        $existing = array_column($columns, 'name');
        $required = [
            'document_id' => "ALTER TABLE comments ADD COLUMN document_id TEXT NOT NULL DEFAULT 'default'",
            'parent_id' => "ALTER TABLE comments ADD COLUMN parent_id INTEGER DEFAULT NULL",
            'page_title' => "ALTER TABLE comments ADD COLUMN page_title TEXT DEFAULT ''",
            'email' => "ALTER TABLE comments ADD COLUMN email TEXT DEFAULT ''",
            'feedback_type' => "ALTER TABLE comments ADD COLUMN feedback_type TEXT DEFAULT ''",
            'status' => "ALTER TABLE comments ADD COLUMN status TEXT NOT NULL DEFAULT 'approved'",
            'resolved' => "ALTER TABLE comments ADD COLUMN resolved INTEGER NOT NULL DEFAULT 0",
            'ip_address' => "ALTER TABLE comments ADD COLUMN ip_address TEXT DEFAULT ''",
            'user_agent' => "ALTER TABLE comments ADD COLUMN user_agent TEXT DEFAULT ''",
            'question_id' => "ALTER TABLE comments ADD COLUMN question_id TEXT DEFAULT ''",
        ];

        if (in_array('rating', $existing, true) && !in_array('feedback_type', $existing, true)) {
            $this->pdo->exec("ALTER TABLE comments RENAME COLUMN rating TO feedback_type");
            $existing[] = 'feedback_type';
        }

        foreach ($required as $column => $sql) {
            if (!in_array($column, $existing, true)) {
                $this->pdo->exec($sql);
            }
        }
    }

    public function getComments($documentId, $sectionId, $visibleOnly = true) {
        $sql = "SELECT id, parent_id, document_id, section_id, question_id, page_title, name, email, feedback_type, comment, status, resolved, created_at
                FROM comments
                WHERE document_id = :document_id AND section_id = :section_id";

        if ($visibleOnly) {
            $sql .= " AND status != 'hidden'";
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':document_id' => $documentId,
            ':section_id' => $sectionId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getCommentCounts($documentId) {
        $stmt = $this->pdo->prepare("
            SELECT section_id, COUNT(*) AS total
            FROM comments
            WHERE document_id = :document_id AND status != 'hidden'
            GROUP BY section_id
        ");
        $stmt->execute([':document_id' => $documentId]);
        $counts = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[$row['section_id']] = (int) $row['total'];
        }

        return $counts;
    }

    public function addComment($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO comments (
                parent_id,
                document_id,
                section_id,
                page_title,
                name,
                email,
                feedback_type,
                comment,
                status,
                resolved,
                ip_address,
                user_agent,
                question_id
            ) VALUES (
                :parent_id,
                :document_id,
                :section_id,
                :page_title,
                :name,
                :email,
                :feedback_type,
                :comment,
                :status,
                :resolved,
                :ip_address,
                :user_agent,
                :question_id
            )
        ");

        return $stmt->execute([
            ':parent_id' => !empty($data['parent_id']) ? $data['parent_id'] : null,
            ':document_id' => $data['document_id'] ?? 'default',
            ':section_id' => $data['section_id'],
            ':page_title' => $data['page_title'] ?? '',
            ':name' => $data['name'],
            ':email' => $data['email'] ?? '',
            ':feedback_type' => $data['feedback_type'] ?? '',
            ':comment' => $data['comment'],
            ':status' => $data['status'] ?? 'approved',
            ':resolved' => !empty($data['resolved']) ? 1 : 0,
            ':ip_address' => $data['ip_address'] ?? '',
            ':user_agent' => $data['user_agent'] ?? '',
            ':question_id' => $data['question_id'] ?? '',
        ]);
    }
}
