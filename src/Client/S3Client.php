<?php


namespace PimcoreS3Bundle\Client;


use Aws\S3\S3Client as BaseS3Client;

class S3Client extends BaseS3Client
{
    public function __construct(
        string $region,
        string $accessKeyId,
        string $secretAccessKey,
        string $version = 'latest'
    )
    {
        parent::__construct([
            'version' => $version,
            'region' => $region,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ],
        ]);
    }
}