<?php
/**
 * Error Logging Endpoint for Production Monitoring
 * Logs JavaScript errors and exceptions to a file
 */

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Get JSON input
$input = file_get_contents('php://input');
$errorData = json_decode($input, true);

if (!$errorData) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Prepare log entry
$logEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'context' => $errorData['context'] ?? 'Unknown',
    'message' => $errorData['message'] ?? '',
    'stack' => $errorData['stack'] ?? '',
    'url' => $errorData['url'] ?? '',
    'userAgent' => $errorData['userAgent'] ?? '',
    'serverTime' => date('c')
];

// Log directory
$logDir = __DIR__ . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

// Log file with date
$logFile = $logDir . '/js-errors-' . date('Y-m-d') . '.log';

// Format log entry
$logLine = sprintf(
    "[%s] %s: %s\nURL: %s\nUser Agent: %s\nStack: %s\n%s\n\n",
    $logEntry['timestamp'],
    $logEntry['context'],
    $logEntry['message'],
    $logEntry['url'],
    $logEntry['userAgent'],
    $logEntry['stack'],
    str_repeat('-', 80)
);

// Write to log file
file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// Return success
http_response_code(200);
echo json_encode(['success' => true]);
