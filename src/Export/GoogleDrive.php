<?php

namespace Costlocker\Reports\Export;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Costlocker\Reports\ReportSettings;

class GoogleDrive
{
    private $configDir;
    private $reportType;
    private $filename;

    public function __construct($oauthConfigDir, $reportType, $filename)
    {
        $this->configDir = $oauthConfigDir;
        $this->reportType = $reportType;
        $this->filename = $filename;
    }

    public function downloadCsvFile($filename = null)
    {
        if ($this->isDisabled()) {
            return null;
        }
        list($service, $database) = $this->getClient();
        $fileId = $this->findFileId($filename ?: $this->filename, $database);
        if (!$fileId) {
            return null;
        }
        return $service->files->export($fileId, 'text/csv', ['alt' => 'media'])->getBody()->getContents();
    }

    private function findFileId($filename, array $database)
    {
        foreach ($database as $report => $id) {
            if (is_int(strpos($report, $filename))) {
                return $id;
            }
        }
    }

    public function upload($filePath, ReportSettings $settings)
    {
        if ($this->isDisabled()) {
            return false;
        }

        list($service, $database) = $this->getClient();
        $fileId = $database[$filePath] ?? null;

        $reportConfig = $this->getReportConfig();
        $metadata = new Google_Service_Drive_DriveFile([
            'name' => $reportConfig['title']($settings),
            'mimeType' => 'application/vnd.google-apps.spreadsheet',
            'parents' => $reportConfig['folders']
        ]);
        $file = [
            'data' => file_get_contents($filePath),
            'mimeType' => mime_content_type($filePath),
            'uploadType' => 'multipart',
            'fields' => 'id',
        ];

        if ($fileId) {
            $metadata->setParents(null);
            $result = $service->files->update($fileId, $metadata, $file);
        } else {
            $result = $service->files->create($metadata, $file);
            $database[$filePath] = $result->id;
            $this->saveJson('files.json', $database);
        }
        return true;
    }

    private function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('costlocker/reports');
        $client->setScopes(Google_Service_Drive::DRIVE);
        $client->setAccessToken(file_get_contents($this->getConfigFile('token.json')));

        $oauth = $this->getConfigJson('client.json')['web'];
        $client->setClientId($oauth['client_id']);
        $client->setClientSecret($oauth['client_secret']);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $this->saveJson('token.json', $client->getAccessToken());
        }

        $database = $this->getConfigJson('files.json');

        return [new Google_Service_Drive($client), $database];
    }

    private function isDisabled()
    {
        return !$this->configDir;
    }

    private function getReportConfig()
    {
        $configs = require $this->getConfigFile('config.php');
        return $configs[$this->reportType];
    }

    private function getConfigJson($file)
    {
        return json_decode(file_get_contents($this->getConfigFile($file)), true);
    }

    private function saveJson($file, array $json)
    {
        file_put_contents($this->getConfigFile($file), json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function getConfigFile($file)
    {
        return "{$this->configDir}/{$file}";
    }
}
