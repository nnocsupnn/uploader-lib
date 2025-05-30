<?php

namespace Maxicare;

use CURLFile;
use InvalidArgumentException;
use Maxicare\Interface\FileUploadInterface;

/**
 * Hubspot Impl
 * @author Nino Casupanan
 * 
 */
class Hubspot implements FileUploadInterface {

    public function __construct() {
        define('HUBSPOT_API_FILEUPLOAD', 'https://api.hubapi.com/files/v3/files');
        define('HUBSPOT_API_SEARCH', 'https://api.hubapi.com/files/v3/files/search');

        define('HUBSPOT_API_KEY', getenv('HUBSPOT_API_KEY') ?: null);
        define('HUBSPOT_HEADER', array(
            'Authorization: Bearer ' . HUBSPOT_API_KEY
        ));

        $this->validateVars();
    }

    private function validateVars() {
        if (HUBSPOT_API_KEY === null) {
            throw new InvalidArgumentException("Environment variable HUBSPOT_API_KEY is not set");
        }
    }

    /**
     * Upload to hubspot
     * 
     * @reference https://developers.hubspot.com/docs/reference/api/library/files/v3#post-%2Ffiles%2Fv3%2Ffiles
     */
    public function upload(String $filePath, String $folderId) {
        $hubspotFileUploadOption = [
            "access" => "PRIVATE",
            "overwrite" => true,
            "duplicateValidationStrategy" => "NONE"
        ];

        $payload = array('file'=> new CURLFile($filePath), 'folderId' => $folderId, 'options' => json_encode($hubspotFileUploadOption));
        $result = executeCurlRequest(HUBSPOT_API_FILEUPLOAD, "POST", $payload, HUBSPOT_HEADER, __CLASS__);

        return $result;
    }


    public function delete(String $fileId) {
        // TODO
    }

    /**
     * Files
     * @param string $folderId Search by filtering to folderId
     * @param string $search Search contains filename
     */
    public function files(String $folderId, String $search) {
        $result = executeCurlRequest(HUBSPOT_API_SEARCH . "?" . http_build_query(["parentFolderIds" => $folderId]), "GET", null, HUBSPOT_HEADER, __CLASS__);

        $filtered = json_decode($result['response'], true);

        return array_filter($filtered['results'], function($obj) use ($search) {
            return str_contains($obj['name'], $search);
        });
    }
}