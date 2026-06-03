<?php
/**
 * Database backup helpers — daily files named backup_YYYY-MM-DD.sql
 */

function getDatabaseBackupDirectory(): string {
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'database';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function getDailyBackupFilename(?string $date = null): string {
    $date = $date ?? date('Y-m-d');
    return 'backup_' . $date . '.sql';
}

function isValidBackupFilename(string $filename): bool {
    return (bool) preg_match('/^backup_\d{4}-\d{2}-\d{2}\.sql$/', $filename);
}

function resolveBackupFilePath(string $filename): ?string {
    if (!isValidBackupFilename($filename)) {
        return null;
    }

    $dir = getDatabaseBackupDirectory();
    $path = $dir . DIRECTORY_SEPARATOR . $filename;

    if (!is_file($path)) {
        return null;
    }

    $realDir = realpath($dir);
    $realFile = realpath($path);
    if (!$realDir || !$realFile || strpos($realFile, $realDir) !== 0) {
        return null;
    }

    return $realFile;
}

/**
 * @return array<int, array{filename: string, date: string, size: int, modified: int, path: string}>
 */
function listDatabaseBackups(): array {
    $dir = getDatabaseBackupDirectory();
    $files = glob($dir . DIRECTORY_SEPARATOR . 'backup_*.sql') ?: [];
    $backups = [];

    foreach ($files as $path) {
        $filename = basename($path);
        if (!isValidBackupFilename($filename)) {
            continue;
        }
        $date = substr($filename, 7, 10);
        $backups[] = [
            'filename' => $filename,
            'date' => $date,
            'size' => (int) filesize($path),
            'modified' => (int) filemtime($path),
            'path' => $path,
        ];
    }

    usort($backups, static function ($a, $b) {
        return strcmp($b['date'], $a['date']);
    });

    return $backups;
}

function formatBackupFileSize(int $bytes): string {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}

function findMysqldumpBinary(): ?string {
    $candidates = [];

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump';
        $out = [];
        @exec('where mysqldump 2>nul', $out, $code);
        if ($code === 0 && !empty($out[0]) && is_file(trim($out[0]))) {
            $candidates[] = trim($out[0]);
        }
    } else {
        $candidates[] = '/usr/bin/mysqldump';
        $candidates[] = '/usr/local/bin/mysqldump';
        $out = [];
        @exec('which mysqldump 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out[0]) && is_file(trim($out[0]))) {
            $candidates[] = trim($out[0]);
        }
        $candidates[] = 'mysqldump';
    }

    foreach ($candidates as $bin) {
        if ($bin === 'mysqldump') {
            continue;
        }
        if (is_file($bin)) {
            return $bin;
        }
    }

    return null;
}

function runMysqldumpBackup(string $host, string $dbname, string $username, string $password, string $outputPath): bool {
    $mysqldump = findMysqldumpBinary();
    if ($mysqldump === null) {
        return false;
    }

    $parts = [
        escapeshellarg($mysqldump),
        '--host=' . escapeshellarg($host),
        '--user=' . escapeshellarg($username),
    ];
    if ($password !== '') {
        $parts[] = '--password=' . escapeshellarg($password);
    }
    $parts[] = '--single-transaction';
    $parts[] = '--routines';
    $parts[] = '--triggers';
    $parts[] = '--add-drop-table';
    $parts[] = '--default-character-set=utf8mb4';
    $parts[] = escapeshellarg($dbname);

    $redirect = ' > ' . escapeshellarg($outputPath) . ' 2>&1';
    $command = implode(' ', $parts) . $redirect;

    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $command = 'cmd /C ' . $command;
    }

    @exec($command, $output, $returnCode);

    return $returnCode === 0 && is_file($outputPath) && filesize($outputPath) > 0;
}

function runPdoDatabaseBackup(PDO $pdo, string $dbname, string $outputPath): bool {
    try {
        $handle = fopen($outputPath, 'wb');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, "-- EVSU Book Borrowing System — Database Backup\n");
        fwrite($handle, '-- Database: ' . $dbname . "\n");
        fwrite($handle, '-- Generated: ' . date('Y-m-d H:i:s T') . "\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $createStmt = $pdo->query('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
            $createRow = $createStmt->fetch(PDO::FETCH_NUM);
            if (!$createRow || empty($createRow[1])) {
                continue;
            }

            fwrite($handle, "DROP TABLE IF EXISTS `" . str_replace('`', '``', $table) . "`;\n");
            fwrite($handle, $createRow[1] . ";\n\n");

            $countStmt = $pdo->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $table) . '`');
            $rowCount = (int) $countStmt->fetchColumn();
            if ($rowCount === 0) {
                fwrite($handle, "\n");
                continue;
            }

            $dataStmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
            $columns = null;

            while ($row = $dataStmt->fetch(PDO::FETCH_ASSOC)) {
                if ($columns === null) {
                    $columns = array_map(static function ($col) {
                        return '`' . str_replace('`', '``', $col) . '`';
                    }, array_keys($row));
                }

                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = $pdo->quote((string) $value);
                    }
                }

                fwrite($handle, 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return is_file($outputPath) && filesize($outputPath) > 0;
    } catch (Throwable $e) {
        error_log('runPdoDatabaseBackup: ' . $e->getMessage());
        if (isset($handle) && is_resource($handle)) {
            fclose($handle);
        }
        if (is_file($outputPath)) {
            @unlink($outputPath);
        }
        return false;
    }
}

/**
 * Create or overwrite today's backup file.
 *
 * @return array{success: bool, filename: string, path: string, method: string, message: string}
 */
function createDatabaseBackup(?PDO $pdo = null, ?string $date = null): array {
    global $host, $dbname, $username, $password;

    if ($pdo === null) {
        require_once __DIR__ . '/db_connect.php';
    }

    $filename = getDailyBackupFilename($date);
    $outputPath = getDatabaseBackupDirectory() . DIRECTORY_SEPARATOR . $filename;
    $method = 'unknown';

    if (runMysqldumpBackup($host, $dbname, $username, $password, $outputPath)) {
        $method = 'mysqldump';
    } elseif (runPdoDatabaseBackup($pdo, $dbname, $outputPath)) {
        $method = 'pdo';
    } else {
        return [
            'success' => false,
            'filename' => $filename,
            'path' => $outputPath,
            'method' => $method,
            'message' => 'Backup failed. Ensure MySQL is running and the backups/database folder is writable.',
        ];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'path' => $outputPath,
        'method' => $method,
        'message' => 'Backup saved as ' . $filename . ' (' . formatBackupFileSize((int) filesize($outputPath)) . ').',
    ];
}

function logDatabaseBackupRun(array $result, string $source = 'manual'): void {
    $logDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $line = sprintf(
        "[%s] source=%s success=%s method=%s file=%s %s\n",
        date('Y-m-d H:i:s'),
        $source,
        $result['success'] ? 'yes' : 'no',
        $result['method'] ?? 'n/a',
        $result['filename'] ?? '',
        $result['message'] ?? ''
    );

    @file_put_contents($logDir . DIRECTORY_SEPARATOR . 'database_backup.log', $line, FILE_APPEND | LOCK_EX);
}
