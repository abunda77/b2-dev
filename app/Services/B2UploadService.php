<?php

namespace App\Services;

use Aws\S3\S3Client;

class B2UploadService
{
    private S3Client $client;

    private string $bucket;

    private string $disk;

    public function __construct(string $disk = 'b2')
    {
        $config = config("filesystems.disks.{$disk}");

        $this->client = new S3Client([
            'version' => 'latest',
            'region' => $config['region'],
            'endpoint' => $config['endpoint'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
            'use_path_style_endpoint' => true,
        ]);

        $this->bucket = $config['bucket'];
        $this->disk = $disk;
    }

    /**
     * Generate a presigned PUT URL for single-part direct upload.
     */
    public function presignedPutUrl(string $key, string $contentType): string
    {
        $cmd = $this->client->getCommand('PutObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $request = $this->client->createPresignedRequest($cmd, '+1 hour');

        return (string) $request->getUri();
    }

    /**
     * Initiate a multipart upload and return presigned URLs for each part.
     *
     * @return array{uploadId: string, key: string, presignedUrls: array<int, string>}
     */
    public function initiateMultipartUpload(string $key, string $contentType, int $fileSize): array
    {
        $result = $this->client->createMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'ContentType' => $contentType,
        ]);

        $uploadId = $result['UploadId'];

        $chunkSize = 5 * 1024 * 1024; // 5 MB minimum part size
        $totalParts = (int) ceil($fileSize / $chunkSize);
        $presignedUrls = [];

        for ($partNumber = 1; $partNumber <= $totalParts; $partNumber++) {
            $presignedUrls[] = $this->presignedUploadPartUrl($key, $uploadId, $partNumber);
        }

        return [
            'uploadId' => $uploadId,
            'key' => $key,
            'presignedUrls' => $presignedUrls,
        ];
    }

    /**
     * Generate a presigned URL for a single multipart upload part.
     */
    public function presignedUploadPartUrl(string $key, string $uploadId, int $partNumber): string
    {
        $cmd = $this->client->getCommand('UploadPart', [
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'PartNumber' => $partNumber,
        ]);

        $request = $this->client->createPresignedRequest($cmd, '+1 hour');

        return (string) $request->getUri();
    }

    /**
     * Complete a multipart upload.
     *
     * @param  array<int, array{PartNumber: int, ETag: string}>  $parts
     */
    public function completeMultipartUpload(string $key, string $uploadId, array $parts): void
    {
        $this->client->completeMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $parts],
        ]);
    }

    /**
     * Abort a multipart upload.
     */
    public function abortMultipartUpload(string $key, string $uploadId): void
    {
        $this->client->abortMultipartUpload([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
        ]);
    }

    /**
     * Delete an object from storage.
     */
    public function deleteObject(string $key): void
    {
        $this->client->deleteObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);
    }

    /**
     * Generate a presigned GET URL for downloading.
     */
    public function presignedGetUrl(string $key): string
    {
        $cmd = $this->client->getCommand('GetObject', [
            'Bucket' => $this->bucket,
            'Key' => $key,
        ]);

        $request = $this->client->createPresignedRequest($cmd, '+1 hour');

        return (string) $request->getUri();
    }

    /**
     * Generate a unique storage key for a file.
     */
    public function generateKey(string $originalName): string
    {
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $unique = uniqid('', true).'_'.time();

        return 'file-host/'.$unique.($ext ? '.'.$ext : '');
    }

    /**
     * Configure CORS on the bucket to allow direct browser uploads.
     *
     * @param  array<int, string>  $allowedOrigins
     */
    public function configureCors(array $allowedOrigins = ['*']): void
    {
        $this->client->putBucketCors([
            'Bucket' => $this->bucket,
            'CORSConfiguration' => [
                'CORSRules' => [
                    [
                        'AllowedOrigins' => $allowedOrigins,
                        'AllowedMethods' => ['PUT', 'GET', 'HEAD'],
                        'AllowedHeaders' => ['*'],
                        'ExposeHeaders' => ['ETag', 'x-amz-request-id', 'x-amz-id-2'],
                        'MaxAgeSeconds' => 3600,
                    ],
                ],
            ],
        ]);
    }

    /**
     * Read current CORS configuration from the bucket.
     */
    public function getCorsConfig(): ?array
    {
        try {
            $result = $this->client->getBucketCors([
                'Bucket' => $this->bucket,
            ]);

            return $result['CORSRules'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the disk name.
     */
    public function disk(): string
    {
        return $this->disk;
    }
}
