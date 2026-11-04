<?php
declare(strict_types=1);
final class Database {
    public PDO $pdo;
    public bool $mysql;
    public function __construct(?string $dsn = null) {
        $dsn ??= getenv('DB_DSN') ?: 'sqlite:' . CRM_ROOT . '/storage/commerce.sqlite';
        $this->mysql = str_starts_with($dsn,'mysql:');
        if (!$this->mysql && !str_starts_with($dsn,'sqlite:')) throw new RuntimeException('Only MySQL and SQLite are supported.');
        $this->pdo = new PDO($dsn, getenv('DB_USER') ?: null, getenv('DB_PASSWORD') ?: null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        if ($this->mysql) $this->pdo->exec('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        if (!$this->mysql) { $this->pdo->exec('PRAGMA foreign_keys=ON'); $this->pdo->exec('PRAGMA busy_timeout=5000'); }
    }
    public function run(string $sql, array $params=[]): PDOStatement { $q=$this->pdo->prepare($sql); $q->execute($params); return $q; }
    public function one(string $sql,array $params=[]): ?array { return $this->run($sql,$params)->fetch() ?: null; }
    public function all(string $sql,array $params=[]): array { return $this->run($sql,$params)->fetchAll(); }
    public function insert(string $table,array $data): int { $this->run('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')',array_values($data)); return (int)$this->pdo->lastInsertId(); }
    public function transaction(callable $fn): mixed {
        $this->pdo->beginTransaction();
        try { $result=$fn(); $this->pdo->commit(); return $result; } catch(Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
    }
    public function migrate(): void {
        $sql=file_get_contents(CRM_ROOT.'/database/schema.sql');
        if ($this->mysql) $sql=str_replace('INTEGER PRIMARY KEY AUTOINCREMENT','INTEGER PRIMARY KEY AUTO_INCREMENT',$sql);
        foreach(explode(';',$sql) as $statement) {
            $statement=trim($statement); if($statement==='')continue;
            if($this->mysql && str_starts_with($statement,'CREATE INDEX IF NOT EXISTS')) {
                $statement=str_replace('CREATE INDEX IF NOT EXISTS','CREATE INDEX',$statement);
                try {$this->pdo->exec($statement);}catch(PDOException $e){if(($e->errorInfo[1]??0)!==1061)throw $e;}
            } else $this->pdo->exec($statement);
        }
        foreach(['customer','pending_dealer','dealer','support','admin','super_admin'] as $role) if(!$this->one('SELECT name FROM roles WHERE name=?',[$role]))$this->run('INSERT INTO roles(name) VALUES(?)',[$role]);
    }
}
