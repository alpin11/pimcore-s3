<?php

namespace PimcoreS3Bundle\Service;

use Aws\Exception\AwsException;
use Pimcore\Logger;
use PimcoreS3Bundle\Client\CloudFrontClient;

class CloudFrontService
{
    private CloudFrontClient $cloudFrontClient;

    private string $cloudfrontDistributionId;

    /**
     * @param \PimcoreS3Bundle\Client\CloudFrontClient $cloudFrontClient
     * @param string|null $cloudfrontDistributionId
     */
    public function __construct(
        CloudFrontClient $cloudFrontClient,
        string $cloudfrontDistributionId
    ) {
        $this->cloudFrontClient = $cloudFrontClient;
        $this->cloudfrontDistributionId = $cloudfrontDistributionId;
    }

    /**
     * @param string[] $paths
     *
     * @return void
     */
    public function invalidateCloudfrontCache(array $paths): void
    {
        $request = [
            'DistributionId' => $this->cloudfrontDistributionId,
            'InvalidationBatch' => [
                'CallerReference' => uniqid(),
                'Paths' => [
                    'Items' => $paths,
                    'Quantity' => 1
                ]
            ]
        ];

        Logger::info('invalidation request', [
            'request' => $request
        ]);

        try {
            $res = $this->cloudFrontClient->createInvalidation($request);

            $message = '';

            if (isset($res['Location'])) {
                $message = 'The invalidation location is: ' . $res['Location'];
            }

            $message .= ' and the effective URI is ' . $res['@metadata']['effectiveUri'] . '.';

            Logger::info($message);
        } catch (AwsException $e) {
            Logger::err('could not create invalidation for updated asset. Reason: ' . $e->getAwsErrorMessage());
        }
    }
}
