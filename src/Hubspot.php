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
    private $searchFiles = [];

    public function __construct() {
        if (!defined('HUBSPOT_API_FILEUPLOAD')) define('HUBSPOT_API_FILEUPLOAD', 'https://api.hubapi.com/files/v3/files');
        if (!defined('HUBSPOT_API_SEARCH')) define('HUBSPOT_API_SEARCH', 'https://api.hubapi.com/files/v3/files/search');
        if (!defined('HUBSPOT_API_DELETE')) define('HUBSPOT_API_DELETE', 'https://api.hubapi.com/files/v3/files');

        if (!defined('HUBSPOT_API_KEY')) define('HUBSPOT_API_KEY', getenv('HUBSPOT_API_KEY') ?: null);
        if (!defined('HUBSPOT_HEADER')) define('HUBSPOT_HEADER', array(
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
     * 
     * @param String $filePath path of the file
     * @param String $folderId Folder id from the hubspot files
     * @param String $filename Desired Filename
     * 
     * @version 1.1.7
     */
    public function upload(String $filePath, String $folderId, ?String $filename = null) {
        $hubspotFileUploadOption = [
            "access" => "PRIVATE",
            "overwrite" => true,
            "duplicateValidationStrategy" => "NONE"
        ];

        $payload = array(
            'file'=> new CURLFile($filePath), 
            'folderId' => $folderId, 
            'options' => json_encode($hubspotFileUploadOption)
        );

        if ($filename != null) $payload = [...$payload, "fileName" => $filename];
        $result = executeCurlRequest(HUBSPOT_API_FILEUPLOAD, "POST", $payload, HUBSPOT_HEADER, __CLASS__);

        return $result;
    }


    /**
     * Delete file in hubspot
     * @param String $fileId Id from the hubspot api.
     */
    public function delete(String $fileId) {
        $result = executeCurlRequest(HUBSPOT_API_DELETE . "/$fileId", "DELETE", null, HUBSPOT_HEADER, __CLASS__);
        return $result;
    }

    /**
     * Files
     * @param string $folderId Search by filtering to folderId
     * @param string $search Search contains filename
     * @param string $id Search by id
     * @param int $limit Limit of the results (max 100)
     */
    public function files(String $folderId, String|null $search = null, String|null $id = null, Int $limit = 10) {
        $params = ["parentFolderIds" => $folderId];

        if ($id != null) $params = [...$params, "id" => $id];
        if ($search != null) $params = [...$params, "search" => $search];
        if ($limit != null) $params = [...$params, "limit" => $limit];

        $this->searchFiles = executeCurlRequest(HUBSPOT_API_SEARCH . "?" . http_build_query($params), "GET", null, HUBSPOT_HEADER, __CLASS__);
        return $this;
    }

    public function results() {
        return $this->searchFiles['response'];
    }

    public function next() {
        if (!property_exists($this->searchFiles['response'], "paging")) return false;

        $nextUri = $this->searchFiles['response']->paging->next->link ?: null;
        $this->searchFiles = executeCurlRequest($nextUri, "GET", null, HUBSPOT_HEADER, __CLASS__);

        return $this;
    }

    public function prev() {
        if (property_exists($this->searchFiles['response'], "paging")) return false;

        $nextUri = $this->searchFiles['response']->paging->prev->link ?: null;
        $this->searchFiles = executeCurlRequest($nextUri, "GET", null, HUBSPOT_HEADER, __CLASS__);

        return $this;
    }

    /**
     * Delete files under folderId
     * @param String $folderId FolderID of the folder you want to cleanup.
     */
    public function deleteAllByFolderId(String $folderId) {
        set_time_limit(300); // 5mins
        $res = $this->files($folderId, null, null, 100);
        $ids = array_column($res->results()->results, "id");
        do {
            // Delete
            foreach ($ids as $id) {
                $this->delete($id);
            }
        } while ($res->next());

        return $ids;
    }
}