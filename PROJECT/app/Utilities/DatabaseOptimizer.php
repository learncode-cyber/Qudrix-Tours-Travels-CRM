<?php
namespace App\Utilities;
use Illuminate\Support\Facades\DB;

class DatabaseOptimizer
{
    public function optimizeAllTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $results = [];
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            try {
                DB::statement("OPTIMIZE TABLE {$tableName}");
                $results[$tableName] = 'optimized';
            } catch (\Exception $e) {
                $results[$tableName] = 'failed';
            }
        }
        
        return $results;
    }
    
    public function analyzeAllTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        $results = [];
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            DB::statement("ANALYZE TABLE {$tableName}");
            $results[$tableName] = 'analyzed';
        }
        
        return $results;
    }
    
    public function getIndexStats(): array
    {
        return DB::select("
            SELECT TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX, COLUMN_NAME, CARDINALITY
            FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY TABLE_NAME, INDEX_NAME
        ");
    }
    
    public function getSlowQueries(): array
    {
        return DB::select("
            SELECT * FROM mysql.slow_log
            ORDER BY start_time DESC
            LIMIT 100
        ");
    }
    
    public function rebuildIndexes(): array
    {
        $tables = DB::select('SHOW TABLES');
        $results = [];
        
        foreach ($tables as $table) {
            $tableName = array_values((array)$table)[0];
            try {
                DB::statement("REPAIR TABLE {$tableName}");
                $results[$tableName] = 'repaired';
            } catch (\Exception $e) {
                $results[$tableName] = 'failed';
            }
        }
        
        return $results;
    }
}
