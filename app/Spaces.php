<?php

declare(strict_types=1);

namespace Media;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use RuntimeException;

final class Spaces
{
    private S3Client $client;
    private string $bucket;
    private string $cdnUrl;

    public function __construct()
    {
        $this->bucket = Config::get('SPACES_BUCKET');
        $this->cdnUrl = rtrim(Config::get('CDN_URL'), '/');

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => Config::get('SPACES_REGION'),
            'endpoint' => Config::get('SPACES_ENDPOINT'),
            'credentials' => [
                'key' => Config::get('SPACES_KEY'),
                'secret' => Config::get('SPACES_SECRET'),
            ],
        ]);
    }

    public function put(
        string $key,
        string $body,
        string $contentType
    ): string {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'Body' => $body,
                'ContentType' => $contentType,
            ]);

            return $this->url($key);
        } catch (AwsException $e) {
            throw new RuntimeException(
                'Unable to upload file to Spaces.',
                0,
                $e
            );
        }
    }

    public function uploadFile(
        string $key,
        string $filePath,
        string $contentType
    ): string {
        try {
            $this->client->putObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
                'SourceFile' => $filePath,
                'ContentType' => $contentType,
            ]);

            return $this->url($key);
        } catch (AwsException $e) {
            throw new RuntimeException(
                'Unable to upload file to Spaces.',
                0,
                $e
            );
        }
    }

    public function delete(string $key): void
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $key,
            ]);
        } catch (AwsException $e) {
            throw new RuntimeException(
                'Unable to delete file from Spaces.',
                0,
                $e
            );
        }
    }

    public function url(string $key): string
    {
        return $this->cdnUrl . '/' . ltrim($key, '/');
    }

    public function testConnection(): bool
    {
        try {
            $this->client->headBucket([
                'Bucket' => $this->bucket,
            ]);

            return true;
        } catch (AwsException $e) {
            return false;
        }
    }
public function createUploadUrl(
    string $key,
    string $contentType,
    int $expiresMinutes = 30
): string {
    try {
        $command = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $request = $this->client->createPresignedRequest(
            $command,
            '+' . $expiresMinutes . ' minutes'
        );

        return (string) $request->getUri();

    } catch (AwsException $e) {
        throw new RuntimeException(
            'Unable to create upload URL.',
            0,
            $e
        );
    }
}

public function objectExists(string $key): bool
{
    try {
        $this->client->headObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        return true;

    } catch (AwsException $e) {
        return false;
    }
}

public function createDownloadUrl(
    string $key,
    int $expiresMinutes = 60
): string {
    try {
        $command = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $this->client->createPresignedRequest(
            $command,
            '+' . $expiresMinutes . ' minutes'
        );

        return (string) $request->getUri();

    } catch (AwsException $e) {
        throw new RuntimeException(
            'Unable to create download URL.',
            0,
            $e
        );
    }
}

public function downloadToFile(
    string $key,
    string $filePath
): void {
    try {
        $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'SaveAs' => $filePath,
        ]);
    } catch (AwsException $e) {
        throw new RuntimeException(
            'Unable to download file from Spaces.',
            0,
            $e
        );
    }
}
public function listObjects(
    string $prefix
): array {
    try {

        $objects = [];

        $params = [
            'Bucket' => $this->bucket,
            'Prefix' => $prefix,
        ];

        do {

            $result = $this->client->listObjectsV2(
                $params
            );

            foreach (
                $result['Contents'] ?? []
                as $object
            ) {
                $objects[] = $object;
            }

            if (
                !empty($result['IsTruncated'])
                && !empty($result['NextContinuationToken'])
            ) {
                $params['ContinuationToken'] =
                    $result['NextContinuationToken'];
            } else {
                break;
            }

        } while (true);

        return $objects;

    } catch (AwsException $e) {

        throw new RuntimeException(
            'Unable to list files in Spaces.',
            0,
            $e
        );
    }
}
}
