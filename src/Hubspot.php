<?php

namespace Maxicare;

use CURLFile;
use Exception;
use Maxicare\interface\FileUploadInterface;

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
            'Authorization: Bearer ' . getenv('HUBSPOT_API_KEY') ?: null,
            'Cookie: __cf_bm=SZYNy5EN8JjbAJYX3r4N.ZsG46D11bpKbOgxudAUwH0-1748503040-1.0.1.1-z_dDyBdoio76KEe_rED9uv7WeMZ9id6lx6UWJQHxqdhpHMlDKOcm09pZtItwI1jFTIt8hPDAsIxZ43Wfs6xabhCIpS8C5KUFB3C3G9GkL7s'
        );

        $fullUrl = getenv('HUBSPOT_API_FILEUPLOAD') ?: 'https://api.hubapi.com/files/v3/files';

        $result = executeCurlRequest($fullUrl, "POST", $payload, $headers);

        return $result;
    }


    public function delete(String $fileId) {
        // TODO
    }
}