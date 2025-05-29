<?php

namespace Maxicare;

use Exception;
use InvalidArgumentException;

/**
 * 
 * OCI Object Storage Uploader
 * @author Nino Casupanan
 * @description C4C Uploader
 */
class Uploader
{
    private array $config;
    private array $requiredEnvVars = [
        "OCI_REGION",
        "OCI_USER", 
        "OCI_FINGERPRINT",
        "OCI_TENANCY",
        "OCI_NAMESPACE", 
        "OCI_KEY_FILE",
        "OCI_BUCKET_NAME"
    ];

    public function __construct() 
    {
        $this->loadConfiguration();
        $this->validateConfiguration();
    }

    /**
     * Load environment configuration
     */
    private function loadConfiguration(): void
    {
        $this->config = [];
        
        foreach ($this->requiredEnvVars as $var) {
            $value = getenv($var);
            if ($value === false) {
                throw new InvalidArgumentException("Environment variable {$var} is not set");
            }
            $this->config[$var] = $value;
        }

        // Optional variables with defaults
        $this->config['OCI_BUCKETS_OCID'] = getenv('OCI_BUCKETS_OCID') ?: null;
    }

    /**
     * Validate configuration and dependencies
     */
    private function validateConfiguration(): void
    {
        // Check if Laravel's base_path() function exists
        if (!function_exists('base_path')) {
            throw new Exception("Laravel base_path() function not available");
        }

        // Validate key file exists and is readable
        $keyPath = $this->getPrivateKeyPath();
        if (!file_exists($keyPath)) {
            throw new Exception("Private key file not found: {$keyPath}");
        }

        if (!is_readable($keyPath)) {
            throw new Exception("Private key file is not readable: {$keyPath}");
        }

        // Test key file format
        $keyContent = file_get_contents($keyPath);
        if (!$keyContent || !str_contains($keyContent, '-----BEGIN')) {
            throw new Exception("Invalid private key file format");
        }

        // Validate key can be loaded
        $privateKey = openssl_pkey_get_private($keyContent);
        if (!$privateKey) {
            throw new Exception("Cannot load private key: " . openssl_error_string());
        }
        openssl_free_key($privateKey);
    }

    /**
     * Get full path to private key file
     */
    private function getPrivateKeyPath(): string
    {
        $keyFile = $this->config['OCI_KEY_FILE'];
        
        // If absolute path, use as-is
        if (str_starts_with($keyFile, '/') || str_contains($keyFile, ':\\')) {
            return $keyFile;
        }
        
        // Otherwise, relative to Laravel base path
        return base_path() . DIRECTORY_SEPARATOR . $keyFile;
    }

    /**
     * Generate OCI API signature
     */
    private function generateSignature(
        string $method, 
        string $host, 
        string $date, 
        string $requestTarget, 
        string $keyId, 
        string $privateKeyPem,
        array $additionalHeaders = []
    ): string {
        // Build signing string
        $signingString = "(request-target): " . strtolower($method) . " " . $requestTarget;
        $signingString .= "\nhost: " . $host;
        $signingString .= "\ndate: " . $date;
        
        $headersList = "(request-target) host date";
        
        // Add optional headers
        foreach ($additionalHeaders as $headerName => $headerValue) {
            $headerName = strtolower($headerName);
            $signingString .= "\n{$headerName}: {$headerValue}";
            $headersList .= " " . $headerName;
        }

        // Sign the string
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if (!$privateKey) {
            throw new Exception("Failed to load private key: " . openssl_error_string());
        }

        $signature = '';
        $result = openssl_sign($signingString, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($privateKey);

        if (!$result) {
            throw new Exception("Failed to sign request: " . openssl_error_string());
        }

        $signature = base64_encode($signature);

        return "Signature version=\"1\",keyId=\"{$keyId}\",algorithm=\"rsa-sha256\",headers=\"{$headersList}\",signature=\"{$signature}\"";
    }

    /**
     * Build key ID for OCI authentication
     */
    private function getKeyId(): string
    {
        return $this->config['OCI_TENANCY'] . '/' . 
               $this->config['OCI_USER'] . '/' . 
               $this->config['OCI_FINGERPRINT'];
    }

    /**
     * Upload content to OCI Object Storage
     */
    public function upload(string $content, string $filename): array
    {
        if (empty($content)) {
            throw new InvalidArgumentException("Content cannot be empty");
        }

        if (empty($filename)) {
            throw new InvalidArgumentException("Filename cannot be empty");
        }

        $namespace = $this->config['OCI_NAMESPACE'];
        $bucket = $this->config['OCI_BUCKET_NAME'];
        $region = $this->config['OCI_REGION'];
        $host = "objectstorage.{$region}.oraclecloud.com";
        $urlPath = "/n/{$namespace}/b/{$bucket}/o/" . rawurlencode($filename);
        $method = "PUT";
        $fullUrl = "https://{$host}{$urlPath}";

        $date = gmdate('D, d M Y H:i:s T');
        $keyId = $this->getKeyId();
        $privateKeyPem = file_get_contents($this->getPrivateKeyPath());

        // Additional headers for content
        $contentLength = strlen($content);
        $contentSha256 = base64_encode(hash('sha256', $content, true));
        
        $additionalHeaders = [
            'content-length' => $contentLength,
            'content-type' => $this->getContentType($filename),
            'x-content-sha256' => $contentSha256
        ];

        $authHeader = $this->generateSignature(
            $method, 
            $host, 
            $date, 
            $urlPath, 
            $keyId, 
            $privateKeyPem,
            $additionalHeaders
        );

        $headers = [
            "Host: {$host}",
            "Date: {$date}",
            "Authorization: {$authHeader}",
            "Content-Type: " . $additionalHeaders['content-type'],
            "Content-Length: {$contentLength}",
            "x-content-sha256: {$contentSha256}"
        ];

        return $this->executeCurlRequest($fullUrl, $method, $content, $headers);
    }

    /**
     * Upload file to OCI Object Storage
     */
    public function uploadFile(string $filePath, string $objectName = null): array
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new InvalidArgumentException("File is not readable: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new Exception("Failed to read file: {$filePath}");
        }

        $filename = $objectName ?: basename($filePath);
        return $this->upload($content, $filename);
    }

    /**
     * Download object from OCI Object Storage
     */
    public function download(string $filename): array
    {
        if (empty($filename)) {
            throw new InvalidArgumentException("Filename cannot be empty");
        }

        $namespace = $this->config['OCI_NAMESPACE'];
        $bucket = $this->config['OCI_BUCKET_NAME'];
        $region = $this->config['OCI_REGION'];
        $host = "objectstorage.{$region}.oraclecloud.com";
        $urlPath = "/n/{$namespace}/b/{$bucket}/o/" . rawurlencode($filename);
        $method = "GET";
        $fullUrl = "https://{$host}{$urlPath}";

        $date = gmdate('D, d M Y H:i:s T');
        $keyId = $this->getKeyId();
        $privateKeyPem = file_get_contents($this->getPrivateKeyPath());

        $authHeader = $this->generateSignature($method, $host, $date, $urlPath, $keyId, $privateKeyPem);

        $headers = [
            "Host: {$host}",
            "Date: {$date}",
            "Authorization: {$authHeader}"
        ];

        return $this->executeCurlRequest($fullUrl, $method, null, $headers);
    }

    /**
     * Delete object from OCI Object Storage
     */
    public function delete(string $filename): array
    {
        if (empty($filename)) {
            throw new InvalidArgumentException("Filename cannot be empty");
        }

        $namespace = $this->config['OCI_NAMESPACE'];
        $bucket = $this->config['OCI_BUCKET_NAME'];
        $region = $this->config['OCI_REGION'];
        $host = "objectstorage.{$region}.oraclecloud.com";
        $urlPath = "/n/{$namespace}/b/{$bucket}/o/" . rawurlencode($filename);
        $method = "DELETE";
        $fullUrl = "https://{$host}{$urlPath}";

        $date = gmdate('D, d M Y H:i:s T');
        $keyId = $this->getKeyId();
        $privateKeyPem = file_get_contents($this->getPrivateKeyPath());

        $authHeader = $this->generateSignature($method, $host, $date, $urlPath, $keyId, $privateKeyPem);

        $headers = [
            "Host: {$host}",
            "Date: {$date}",
            "Authorization: {$authHeader}"
        ];

        return $this->executeCurlRequest($fullUrl, $method, null, $headers);
    }

    /**
     * List objects in bucket
     */
    public function listObjects(string $prefix = '', int $limit = 1000): array
    {
        $namespace = $this->config['OCI_NAMESPACE'];
        $bucket = $this->config['OCI_BUCKET_NAME'];
        $region = $this->config['OCI_REGION'];
        $host = "objectstorage.{$region}.oraclecloud.com";
        
        $queryParams = array_filter([
            'prefix' => $prefix,
            'limit' => $limit
        ]);
        $queryString = $queryParams ? '?' . http_build_query($queryParams) : '';
        
        $urlPath = "/n/{$namespace}/b/{$bucket}/o" . $queryString;
        $method = "GET";
        $fullUrl = "https://{$host}{$urlPath}";

        $date = gmdate('D, d M Y H:i:s T');
        $keyId = $this->getKeyId();
        $privateKeyPem = file_get_contents($this->getPrivateKeyPath());

        $authHeader = $this->generateSignature($method, $host, $date, $urlPath, $keyId, $privateKeyPem);

        $headers = [
            "Host: {$host}",
            "Date: {$date}",
            "Authorization: {$authHeader}"
        ];

        return $this->executeCurlRequest($fullUrl, $method, null, $headers);
    }

    /**
     * Execute cURL request
     */
    private function executeCurlRequest(string $url, string $method, ?string $data, array $headers): array
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
        logResult($method, $url, $result);

        return $result;
    }

    /**
     * Determine content type based on file extension
     */
    private function getContentType(string $filename): string
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
     * Get current configuration
     */
    public function getConfig(): array
    {
        // Return config without sensitive data
        $safeConfig = $this->config;
        unset($safeConfig['OCI_KEY_FILE']);
        return $safeConfig;
    }

    /**
     * Test connection to OCI
     */
    public function testConnection(): array
    {
        try {
            $result = $this->listObjects('', 1);
            return [
                'success' => $result['success'],
                'message' => $result['success'] ? 'Connection successful' : 'Connection failed',
                'details' => $result
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
                'details' => null
            ];
        }
    }
}