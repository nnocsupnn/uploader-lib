<?php


/**
 * Log operation results
 */
function logResult(string $method, string $url, array $result, ...$args): void
{
    $status = $result['success'] ? '✅' : '❌';
    $message = "{$status} {$method} {$url} - Status: {$result['status_code']}";
    
    if (!$result['success'] && $result['response']) {
        $message .= " - Error: " . substr($result['response'], 0, 200);
    }

    // Use Laravel's logger if available, otherwise echo
    if (function_exists('logger')) {
        logger(join(" ", $args) . " - " . $message);
    } else {
        echo $message . "\n";
    }
}