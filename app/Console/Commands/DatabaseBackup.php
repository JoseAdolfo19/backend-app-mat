<?php

namespace App\Console\Commands;

use App\Models\InstitutionConfig;
use Illuminate\Console\Command;

class DatabaseBackup extends Command
{
    protected $signature = 'mathflow:backup
        {--prune : Elimina respaldos viejos (conserva el más reciente de cada día)}';

    protected $description = 'Genera un respaldo de la base de datos MySQL';

    public function handle(): int
    {
        $dbConfig = config('database.connections.mysql');

        if (!$dbConfig) {
            $this->error('Configuración MySQL no encontrada.');
            return self::FAILURE;
        }

        $filename = 'backup_' . now()->format('Y-m-d_His') . '.sql';
        $backupPath = storage_path('app/backups');

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0750, true);
        }

        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'];
        $password = $dbConfig['password'];

        $cmd = sprintf(
            'mysqldump --host=%s --port=%s --user=%s %s > %s 2>&1',
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($backupPath . '/' . $filename)
        );

        $env = array_merge($_ENV ?? [], [
            'MYSQL_PWD' => $password,
        ]);

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode, $env);

        if ($returnCode !== 0) {
            report('Backup failed: ' . implode("\n", $output));
            $this->error('Error al generar el respaldo.');
            return self::FAILURE;
        }

        $config = InstitutionConfig::first();
        if ($config) {
            $config->update(['last_backup' => now()]);
        }

        $this->info("Respaldo creado: {$filename} (" . filesize($backupPath . '/' . $filename) . ' bytes)');

        if ($this->option('prune')) {
            $this->pruneOldBackups($backupPath);
        }

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $backupPath): void
    {
        $files = glob($backupPath . '/backup_*.sql');

        if (empty($files)) {
            return;
        }

        $byDay = [];

        foreach ($files as $file) {
            $day = date('Y-m-d', filemtime($file));
            if (!isset($byDay[$day]) || filemtime($file) > filemtime($byDay[$day])) {
                $byDay[$day] = $file;
            }
        }

        $keep = array_values($byDay);

        foreach ($files as $file) {
            if (!in_array($file, $keep, true)) {
                unlink($file);
                $this->info("Respaldo viejo eliminado: " . basename($file));
            }
        }
    }
}