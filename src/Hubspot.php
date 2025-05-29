<?php

namespace Maxicare;

use CURLFile;
use Exception;
use Maxicare\Interface\FileUploadInterface;

/**
 * Hubspot Impl
 * @author Nino Casupanan
 * 
 */
class Hubspot implements FileUploadInterface {

    public function __construct() {
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
        $headers = array(
            'Authorization: Bearer ' . getenv('HUBSPOT_API_KEY') ?: null
        );

        $fullUrl = getenv('HUBSPOT_API_FILEUPLOAD') ?: 'https://api.hubapi.com/files/v3/files';

        $result = executeCurlRequest($fullUrl, "POST", $payload, $headers, __CLASS__);

        return $result;
    }


    public function delete(String $fileId) {
        // TODO
    }
}