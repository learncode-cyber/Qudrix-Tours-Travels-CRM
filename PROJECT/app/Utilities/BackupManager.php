<?php
namespace App\Utilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BackupManager
{
    public function createBackup(string $backupName = null): string
    {
        $backupName = $backupName ?? 'backup_' . date('Y_m_d_H_i_s');
        $backupPath = "backups/{$backupName}.sql";
        
        $database = env('DB_DATABASE');
        $user = env('DB_USERNAME');
        $password = env('DB_PASSWORD');
        $host = env('DB_HOST');
        
        $command = "mysqldump -h {$host} -u {$user} -p{$password} {$database} > /tmp/{$backupName}.sql";
        exec($command);
        
        $content = file_get_contents("/tmp/{$backupName}.sql");
        Storage::put($backupPath, $content);
        unlink("/tmp/{$backupName}.sql");
        
        return $backupPath;
    }
    
    public function restoreBackup(string $backupPath): bool
    {
        try {
            $content = Storage::get($backupPath);
            $tempFile = "/tmp/restore_" . time() . ".sql";
            file_put_contents($tempFile, $content);
            
            $database = env('DB_DATABASE');
            $user = env('DB_USERNAME');
            $password = env('DB_PASSWORD');
            $host = env('DB_HOST');
            
            $command = "mysql -h {$host} -u {$user} -p{$password} {$database} < {$tempFile}";
            exec($command);
            
            unlink($tempFile);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function listBackups(): array
    {
        return Storage::files('backups');
    }
    
    public function deleteOldBackups(int $daysOld = 30): int
    {
        $cutoffTime = time() - ($daysOld * 24 * 60 * 60);
        $deleted = 0;
        
        foreach (Storage::files('backups') as $file) {
            if (Storage::lastModified("backups/{$file}") < $cutoffTime) {
                Storage::delete("backups/{$file}");
                $deleted++;
            }
        }
        
        return $deleted;
    }
}
