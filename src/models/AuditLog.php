<?php

namespace App\Models;

use App\Utils\Logger;

class AuditLog extends BaseModel
{
    protected $table = 'audit_logs';
    
    protected $fillable = [
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'details',
        'ip_address',
        'user_agent',
        'created_at'
    ];
    
    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime'
    ];
    
    /**
     * Log an action
     */
    public static function log($action, $data = [])
    {
        $log = self::create([
            'user_id' => $data['user_id'] ?? null,
            'action' => $action,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'details' => $data['details'] ?? [],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        Logger::info("Audit log created", [
            'action' => $action,
            'user_id' => $data['user_id'] ?? 'system'
        ]);
        
        return $log;
    }
    
    /**
     * Log user action
     */
    public static function logUserAction($userId, $action, $entityType = null, $entityId = null, $details = [])
    {
        return self::log($action, [
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => array_merge($details, [
                'timestamp' => time(),
                'user_ip' => $_SERVER['REMOTE_ADDR'] ?? null
            ])
        ]);
    }
    
    /**
     * Log system action
     */
    public static function logSystemAction($action, $details = [])
    {
        return self::log($action, [
            'details' => array_merge($details, [
                'system_action' => true,
                'timestamp' => time()
            ])
        ]);
    }
    
    /**
     * Get logs with filters
     */
    public static function getLogs($filters = [])
    {
        $query = self::query();
        
        if (isset($filters['user_id']) && $filters['user_id']) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['action']) && $filters['action']) {
            $query->where('action', $filters['action']);
        }
        
        if (isset($filters['entity_type']) && $filters['entity_type']) {
            $query->where('entity_type', $filters['entity_type']);
        }
        
        if (isset($filters['entity_id']) && $filters['entity_id']) {
            $query->where('entity_id', $filters['entity_id']);
        }
        
        if (isset($filters['start_date']) && $filters['start_date']) {
            $query->where('created_at', '>=', $filters['start_date']);
        }
        
        if (isset($filters['end_date']) && $filters['end_date']) {
            $query->where('created_at', '<=', $filters['end_date']);
        }
        
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('action', 'ILIKE', "%{$search}%")
                  ->orWhere('entity_type', 'ILIKE', "%{$search}%")
                  ->orWhereRaw("details::text ILIKE ?", ["%{$search}%"]);
            });
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $page = $filters['page'] ?? 1;
        $perPage = $filters['per_page'] ?? 50;
        
        return $query->paginate($perPage, ['*'], 'page', $page);
    }
    
    /**
     * Clean old logs
     */
    public static function cleanOldLogs($days = 90)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $deleted = self::where('created_at', '<', $cutoffDate)->delete();
        
        Logger::info("Old audit logs cleaned", [
            'days' => $days,
            'deleted_count' => $deleted,
            'cutoff_date' => $cutoffDate
        ]);
        
        return $deleted;
    }
    
    /**
     * Get action statistics
     */
    public static function getActionStatistics($startDate = null, $endDate = null)
    {
        $query = self::query();
        
        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }
        
        $stats = $query->selectRaw('
            action,
            COUNT(*) as count,
            COUNT(DISTINCT user_id) as unique_users,
            MIN(created_at) as first_occurrence,
            MAX(created_at) as last_occurrence
        ')
        ->groupBy('action')
        ->orderBy('count', 'desc')
        ->get();
        
        return $stats;
    }
}