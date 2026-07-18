<?php
declare(strict_types=1);

final class AdminAuth
{
    private const SESSION_LIFETIME = 1800;
    private const ATTEMPT_WINDOW = 900;
    private const MAX_ATTEMPTS = 5;

    private string $runtimeDirectory;
    private string $adminPath;
    private string $authLockPath;
    private string $attemptsPath;

    public function __construct(string $basePath)
    {
        $this->runtimeDirectory = $basePath . '/runtime';
        $this->adminPath = $this->runtimeDirectory . '/admin.json';
        $this->authLockPath = $this->runtimeDirectory . '/auth.lock';
        $this->attemptsPath = $this->runtimeDirectory . '/login-attempts.json';

        if (!is_dir($this->runtimeDirectory) && !mkdir($this->runtimeDirectory, 0750, true) && !is_dir($this->runtimeDirectory)) {
            throw new RuntimeException('Unable to initialize administrator storage.');
        }

        @chmod($this->runtimeDirectory, 0750);
        start_secure_session();
    }

    public function hasAdmin(): bool
    {
        return is_file($this->adminPath);
    }

    public function createAdmin(string $username, string $password): void
    {
        $username = $this->validateUsername($username);
        $this->validatePassword($password);
        $handle = $this->lock(LOCK_EX);

        try {
            if (is_file($this->adminPath)) {
                throw new RuntimeException('Administrator setup has already been completed.');
            }

            $algorithm = $this->passwordAlgorithm();
            $hash = password_hash($password, $algorithm);

            if ($hash === false) {
                throw new RuntimeException('Unable to secure the administrator password.');
            }

            $record = [
                'version' => 1,
                'username' => $username,
                'password_hash' => $hash,
                'created_at' => gmdate('c'),
                'updated_at' => gmdate('c'),
            ];

            $this->writeJson($this->adminPath, $record);
        } finally {
            $this->unlock($handle);
        }

        $this->establishSession($username);
    }

    public function login(string $username, string $password, string $ip): bool
    {
        $username = trim($username);

        if ($this->isBlocked($ip)) {
            throw new RuntimeException('Too many login attempts. Please wait 15 minutes before trying again.');
        }

        $record = $this->readAdmin();
        $usernameMatches = hash_equals(strtolower((string) $record['username']), strtolower($username));
        $dummyHash = '$2y$10$R8f7GfxvF0UkhkVIoUenIu7UOquL0sA4Z1NA26KvO2Wf5QVx0Sv9C';
        $hash = $usernameMatches ? (string) $record['password_hash'] : $dummyHash;
        $passwordMatches = password_verify($password, $hash);

        if (!$usernameMatches || !$passwordMatches) {
            $this->recordFailure($ip);
            return false;
        }

        $this->clearFailures($ip);
        $this->establishSession((string) $record['username']);

        $algorithm = $this->passwordAlgorithm();
        if (password_needs_rehash((string) $record['password_hash'], $algorithm)) {
            $newHash = password_hash($password, $algorithm);
            if ($newHash !== false) {
                $record['password_hash'] = $newHash;
                $record['updated_at'] = gmdate('c');
                $this->writeAdminRecord($record);
            }
        }

        return true;
    }

    public function isAuthenticated(): bool
    {
        start_secure_session();

        if (empty($_SESSION['admin_username']) || empty($_SESSION['last_activity']) || empty($_SESSION['fingerprint'])) {
            return false;
        }

        if (!hash_equals((string) $_SESSION['fingerprint'], $this->sessionFingerprint())) {
            $this->logout();
            return false;
        }

        if ((time() - (int) $_SESSION['last_activity']) > self::SESSION_LIFETIME) {
            $this->logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public function requireAuthenticated(): void
    {
        if (!$this->isAuthenticated()) {
            flash('error', 'Please sign in to continue.');
            header('Location: ' . app_url('admin/'));
            exit;
        }
    }

    public function username(): string
    {
        return $this->isAuthenticated() ? (string) $_SESSION['admin_username'] : '';
    }

    public function logout(): void
    {
        start_secure_session();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Strict',
            ]);
        }

        session_destroy();
    }

    private function establishSession(string $username): void
    {
        start_secure_session();
        session_regenerate_id(true);
        $_SESSION['admin_username'] = $username;
        $_SESSION['last_activity'] = time();
        $_SESSION['fingerprint'] = $this->sessionFingerprint();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    private function passwordAlgorithm(): string|int
    {
        return defined('PASSWORD_ARGON2ID') ? constant('PASSWORD_ARGON2ID') : PASSWORD_DEFAULT;
    }

    private function sessionFingerprint(): string
    {
        return hash('sha256', substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 0, 500));
    }

    private function validateUsername(string $username): string
    {
        $username = trim($username);

        if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
            throw new RuntimeException('Username must be 3–64 characters and use only letters, numbers, dots, underscores, or hyphens.');
        }

        return $username;
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 12 || strlen($password) > 200) {
            throw new RuntimeException('Password must be between 12 and 200 characters.');
        }

        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            throw new RuntimeException('Password must contain at least one letter and one number.');
        }
    }

    private function readAdmin(): array
    {
        if (!$this->hasAdmin()) {
            throw new RuntimeException('Administrator setup has not been completed.');
        }

        $contents = file_get_contents($this->adminPath);
        $record = $contents === false ? null : json_decode($contents, true);

        if (!is_array($record) || empty($record['username']) || empty($record['password_hash'])) {
            throw new RuntimeException('Administrator credentials are invalid.');
        }

        return $record;
    }

    private function writeAdminRecord(array $record): void
    {
        $handle = $this->lock(LOCK_EX);

        try {
            $this->writeJson($this->adminPath, $record);
        } finally {
            $this->unlock($handle);
        }
    }

    private function isBlocked(string $ip): bool
    {
        $attempts = $this->readAttempts();
        $key = hash('sha256', $ip);
        $recent = array_values(array_filter($attempts[$key] ?? [], static fn ($timestamp): bool => (int) $timestamp > time() - self::ATTEMPT_WINDOW));

        return count($recent) >= self::MAX_ATTEMPTS;
    }

    private function recordFailure(string $ip): void
    {
        $this->mutateAttempts(static function (array $attempts) use ($ip): array {
            $cutoff = time() - self::ATTEMPT_WINDOW;

            foreach ($attempts as $key => $timestamps) {
                $attempts[$key] = array_values(array_filter((array) $timestamps, static fn ($timestamp): bool => (int) $timestamp > $cutoff));
                if ($attempts[$key] === []) {
                    unset($attempts[$key]);
                }
            }

            $key = hash('sha256', $ip);
            $attempts[$key][] = time();
            return $attempts;
        });
    }

    private function clearFailures(string $ip): void
    {
        $this->mutateAttempts(static function (array $attempts) use ($ip): array {
            unset($attempts[hash('sha256', $ip)]);
            return $attempts;
        });
    }

    private function readAttempts(): array
    {
        if (!is_file($this->attemptsPath)) {
            return [];
        }

        $contents = file_get_contents($this->attemptsPath);
        $attempts = $contents === false ? null : json_decode($contents, true);
        return is_array($attempts) ? $attempts : [];
    }

    private function mutateAttempts(callable $callback): void
    {
        $handle = $this->lock(LOCK_EX);

        try {
            $attempts = $this->readAttempts();
            $this->writeJson($this->attemptsPath, $callback($attempts));
        } finally {
            $this->unlock($handle);
        }
    }

    private function writeJson(string $path, array $data): void
    {
        $temporary = tempnam($this->runtimeDirectory, 'auth-');
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;

        if ($temporary === false || file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to save administrator data.');
        }

        @chmod($temporary, 0640);

        if (!rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('Unable to publish administrator data.');
        }
    }

    private function lock(int $operation)
    {
        $handle = fopen($this->authLockPath, 'c+');

        if ($handle === false || !flock($handle, $operation)) {
            throw new RuntimeException('Administrator storage is temporarily unavailable.');
        }

        @chmod($this->authLockPath, 0640);
        return $handle;
    }

    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
