<?php

namespace Maxicare;

use CURLFile;

class Hubspot {

    public function __construct() {
    }

    public function upload(String $filePath, String $folderId) {
        $curl = curl_init();

        $hubspotFileUploadOption = [
            "access" => "PRIVATE",
            "overwrite" => false,
            "duplicateValidationStrategy" => "NONE"
        ];

        $payload = array('file'=> new CURLFile($filePath), 'folderId' => $folderId, 'options' => json_encode($hubspotFileUploadOption));
        $headers = array(
            'Authorization: Bearer ' . getenv('HUBSPOT_API_KEY') ?: null,
            'Cookie: __cf_bm=SZYNy5EN8JjbAJYX3r4N.ZsG46D11bpKbOgxudAUwH0-1748503040-1.0.1.1-z_dDyBdoio76KEe_rED9uv7WeMZ9id6lx6UWJQHxqdhpHMlDKOcm09pZtItwI1jFTIt8hPDAsIxZ43Wfs6xabhCIpS8C5KUFB3C3G9GkL7s'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => getenv('HUBSPOT_API_FILEUPLOAD') ?: 'https://api.hubapi.com/files/v3/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);
        curl_close($curl);

        return $response;
    }
}