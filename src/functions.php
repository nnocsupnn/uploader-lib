<?php


function get_base_path(): callable {
    // Check if Laravel's base_path() function exists
    if (function_exists('base_path')) {
        return base_path(...);
    } else {
        throw new Exception("Laravel base_path() function not available");
    }
}

function getLogger(): callable {
    // Check if Laravel's logger() function exists
    if (function_exists('base_path')) {
        return logger(...);
    } else {
        throw new Exception("Laravel logger() function not available");
    }
}

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
        getLogger()(join(" ", $args) . " - " . $message);
    } else {
        echo $message . "\n";
    }
}

/**
 * Determine content type based on file extension
 */
function getContentType(string $filename): string
{
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    $mimeTypes = [
        'txt' => 'text/plain',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'zip' => 'application/zip',
        'csv' => 'text/csv'
    ];

    return $mimeTypes[$extension] ?? 'application/octet-stream';
}


/**
 * 
 * Centralize curl exec
 */
function executeCurlRequest(string $url, string $method, string|array|null $data, array $headers, ...$args): array
{
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false, # to avoid issues
        CURLOPT_TIMEOUT => 300,
        CURLOPT_CONNECTTIMEOUT => 30
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    
    curl_close($ch);

    if ($curlError) {
        throw new Exception("cURL error: {$curlError}");
    }

    $result = [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'status_code' => $httpCode,
        'response' => $response,
        'curl_info' => $curlInfo
    ];

    // Parse JSON response if applicable
    if ($response && str_contains($curlInfo['content_type'] ?? '', 'json')) {
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $result['data'] = $decoded;
        }
    }

    // Log results for debugging
    logResult($method, $url, $result, $args);

    return $result;
}