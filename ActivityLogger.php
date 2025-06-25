<?php
/**
 * ActivityLogger.php - File-based activity logging system
 */

class ActivityLogger {
    // Log directory - make sure this exists and is writable
    private $logDir = 'logs';
    
    // Maximum log file size before rotation (5MB)
    private $maxLogSize = 5242880;
    
    /**
     * Constructor - creates log directory if it doesn't exist
     */
    public function __construct() {
        if (!file_exists($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }
    }
    
    /**
     * Log an activity
     * 
     * @param string $action The action being performed
     * @param string $details Additional details about the action
     * @param string $userEmail Email of the user performing the action
     * @param string $recordId ID of the record being modified (optional)
     * @return bool Success or failure
     */
    public function log($action, $details, $userEmail, $recordId = null) {
        // Get current date for logging and file organization
        $date = new DateTime();
        $timestamp = $date->format('Y-m-d H:i:s');
        $dateStr = $date->format('Y-m-d');
        
        // Prepare log file path - one file per day
        $logFile = $this->logDir . '/activity_' . $dateStr . '.log';
        
        // Rotate log if it exceeds max size
        $this->rotateLogIfNeeded($logFile);
        
        // Format the log entry as JSON
        $logEntry = json_encode([
            'timestamp' => $timestamp,
            'user' => $userEmail,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'action' => $action,
            'record_id' => $recordId,
            'details' => $details
        ]);
        
        // Write to the log file
        $result = file_put_contents(
            $logFile, 
            $logEntry . PHP_EOL, 
            FILE_APPEND | LOCK_EX
        );
        
        return ($result !== false);
    }
    
    /**
     * Rotate log file if it becomes too large
     * 
     * @param string $logFile Path to the log file
     */
    private function rotateLogIfNeeded($logFile) {
        if (file_exists($logFile) && filesize($logFile) > $this->maxLogSize) {
            $timestamp = date('Y-m-d_H-i-s');
            rename($logFile, $logFile . '.' . $timestamp . '.bak');
        }
    }
    
    /**
     * Get log entries with optional filtering
     * 
     * @param string $date Date to retrieve logs for (YYYY-MM-DD)
     * @param int $limit Maximum number of entries to return
     * @param string $userFilter Filter by specific user
     * @param string $actionFilter Filter by specific action
     * @param string $recordIdFilter Filter by specific record ID
     * @param int $offset Offset for pagination
     * @return array Array of log entries
     */
    public function getLogs($date = null, $limit = 100, $userFilter = null, $actionFilter = null, $recordIdFilter = null, $offset = 0) {
        // If no date provided, use today
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $logFile = $this->logDir . '/activity_' . $date . '.log';
        if (!file_exists($logFile)) {
            return [];
        }
        
        // Read log file
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return [];
        }
        
        // Parse and filter log entries
        $entries = [];
        $count = 0;
        $skipped = 0;
        
        // Process in reverse order (newest first)
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $entry = json_decode($lines[$i], true);
            if (!$entry) continue;
            
            // Apply filters
            if ($userFilter && $entry['user'] !== $userFilter) continue;
            if ($actionFilter && $entry['action'] !== $actionFilter) continue;
            if ($recordIdFilter && $entry['record_id'] != $recordIdFilter) continue;
            
            // Skip entries based on offset
            if ($skipped < $offset) {
                $skipped++;
                continue;
            }
            
            $entries[] = $entry;
            $count++;
            
            // Stop if we've reached the limit
            if ($count >= $limit) break;
        }
        
        return $entries;
    }
    
    /**
     * Get count of log entries matching filters
     */
    public function getLogsCount($date = null, $userFilter = null, $actionFilter = null, $recordIdFilter = null) {
        // If no date provided, use today
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $logFile = $this->logDir . '/activity_' . $date . '.log';
        if (!file_exists($logFile)) {
            return 0;
        }
        
        // Read log file
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return 0;
        }
        
        // Count matching entries
        $count = 0;
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry) continue;
            
            // Apply filters
            if ($userFilter && $entry['user'] !== $userFilter) continue;
            if ($actionFilter && $entry['action'] !== $actionFilter) continue;
            if ($recordIdFilter && $entry['record_id'] != $recordIdFilter) continue;
            
            $count++;
        }
        
        return $count;
    }
    
    /**
     * Get available log dates
     * 
     * @return array Array of dates that have log files
     */
    public function getAvailableDates() {
        $dates = [];
        $pattern = $this->logDir . '/activity_*.log';
        $files = glob($pattern);
        
        foreach ($files as $file) {
            if (preg_match('/activity_(\d{4}-\d{2}-\d{2})\.log$/', $file, $matches)) {
                $dates[] = $matches[1];
            }
        }
        
        // Sort in descending order (newest first)
        rsort($dates);
        return $dates;
    }
    
    /**
     * Get unique users from log files
     */
    public function getUniqueUsers() {
        $users = [];
        $files = glob($this->logDir . '/activity_*.log');
        
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry || !isset($entry['user'])) continue;
                
                if (!in_array($entry['user'], $users)) {
                    $users[] = $entry['user'];
                }
            }
        }
        
        sort($users);
        return $users;
    }
    
    /**
     * Get unique actions from log files
     */
    public function getUniqueActions() {
        $actions = [];
        $files = glob($this->logDir . '/activity_*.log');
        
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if (!$entry || !isset($entry['action'])) continue;
                
                if (!in_array($entry['action'], $actions)) {
                    $actions[] = $entry['action'];
                }
            }
        }
        
        sort($actions);
        return $actions;
    }
    
    /**
     * Get count of specific actions for a date
     */
    public function getActionCount($action, $date = null) {
        // If no date provided, use today
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $logFile = $this->logDir . '/activity_' . $date . '.log';
        if (!file_exists($logFile)) {
            return 0;
        }
        
        // Read log file
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            return 0;
        }
        
        // Count matching actions
        $count = 0;
        
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            if (!$entry || !isset($entry['action'])) continue;
            
            if ($entry['action'] === $action) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Clear logs for a specific date or all dates
     */
    public function clearLogs($date = null) {
        if ($date === 'all') {
            // Clear all log files
            $files = glob($this->logDir . '/activity_*.log');
            foreach ($files as $file) {
                if (!unlink($file)) {
                    return false;
                }
            }
            return true;
        } else {
            // Clear logs for a specific date
            $logFile = $this->logDir . '/activity_' . $date . '.log';
            if (file_exists($logFile)) {
                return unlink($logFile);
            }
            return true; // File doesn't exist, so it's already "cleared"
        }
    }
}
?>