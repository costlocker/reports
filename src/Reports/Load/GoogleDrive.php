<?php

namespace Costlocker\Reports\Load;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

class GoogleDrive implements Loader
{
    private $clientConfigDir;

    public function __construct($clientConfigDir)
    {
        $this->clientConfigDir = $clientConfigDir;
    }

    public function __invoke($filePath, $title, $driveConfig)
    {
        $service = $this->getClient();
        $database = $driveConfig['files'];
        $fileId = $database[$driveConfig['uniqueReportId']] ?? null;

        $metadata = new Google_Service_Drive_DriveFile([
            'name' => $title,
            'mimeType' => 'application/vnd.google-apps.spreadsheet',
            'parents' => [$driveConfig['folderId']],
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
            $database[$driveConfig['uniqueReportId']] = $result->id;
        }
        return $database;
    }

    private function getClient()
    {
        $client = new Google_Client();
        $client->setApplicationName('costlocker/reports');
        $client->setScopes(Google_Service_Drive::DRIVE);
        $client->setAccessToken(file_get_contents("{$this->clientConfigDir}/token.json"));

        $oauth = $this->getJson("{$this->clientConfigDir}/client.json")['web'];
        $client->setClientId($oauth['client_id']);
        $client->setClientSecret($oauth['client_secret']);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $this->saveJson("{$this->clientConfigDir}/token.json", $client->getAccessToken());
        }

        return new Google_Service_Drive($client);
    }

    private function getJson($path)
    {
        return json_decode(file_get_contents($path), true);
    }

    private function saveJson($path, array $json)
    {
        file_put_contents($path, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
