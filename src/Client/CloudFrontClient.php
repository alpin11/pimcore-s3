<?php

namespace PimcoreS3Bundle\Client;

use Aws\CloudFront\CloudFrontClient as BaseCloudFrontClient;

class CloudFrontClient extends BaseCloudFrontClient
{
    /**
     * @param string $version
     * @param string $region
     * @param string $accessKeyId
     * @param string $secretAccessKey
     */
    public function __construct(
        string $version,
        string $region,
        string $accessKeyId,
        string $secretAccessKey
    ) {
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
