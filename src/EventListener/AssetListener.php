<?php


namespace PimcoreS3Bundle\EventListener;


use Aws\Exception\AwsException;
use Pimcore\Cache;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\FrontendEvents;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use PimcoreS3Bundle\Client\CloudFrontClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class AssetListener implements EventSubscriberInterface
{

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $s3TmpUrlPrefix;

    /**
     * @var string
     */
    private $s3AssetUrlPrefix;
    /**
     * @var bool
     */
    private $cloudfrontEnabled;

    /**
     * @var CloudFrontClient
     */
    private $cloudFrontClient;
    /**
     * @var string
     */
    private $cloudfrontDistributionId;
    /**
     * @var bool
     */
    private $cdnEnabled;

    /**
     * @var string|null
     */
    private $cdnDomain;

    public function __construct(
        CloudFrontClient $cloudFrontClient,
        string $baseUrl,
        string $tmpUrl,
        string $assetUrl,
        bool $cloudfrontEnabled = false,
        string $cloudfrontDistributionId = null,
        bool $cdnEnabled = false,
        string $cdnDomain = null
    )
    {
        $this->baseUrl = $baseUrl;
        $this->s3TmpUrlPrefix = $this->baseUrl . str_replace("s3:/", "", $tmpUrl);
        $this->s3AssetUrlPrefix = $this->baseUrl . str_replace("s3:/", "", $assetUrl);
        $this->cloudfrontEnabled = $cloudfrontEnabled;
        $this->cloudFrontClient = $cloudFrontClient;
        $this->cloudfrontDistributionId = $cloudfrontDistributionId;
        $this->cdnEnabled = $cdnEnabled;
        $this->cdnDomain = $cdnDomain;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            FrontendEvents::ASSET_IMAGE_THUMBNAIL => 'onFrontendPathThumbnail',
            FrontendEvents::ASSET_DOCUMENT_IMAGE_THUMBNAIL => 'onFrontendPathThumbnail',
            FrontendEvents::ASSET_VIDEO_IMAGE_THUMBNAIL => 'onFrontendPathThumbnail',
            FrontendEvents::ASSET_VIDEO_THUMBNAIL => 'onFrontendPathThumbnail',
            FrontendEvents::ASSET_PATH => 'onFrontEndPathAsset',
            AssetEvents::IMAGE_THUMBNAIL => 'onAssetThumbnailCreated',
            AssetEvents::VIDEO_IMAGE_THUMBNAIL => 'onAssetThumbnailCreated',
            AssetEvents::DOCUMENT_IMAGE_THUMBNAIL => 'onAssetThumbnailCreated',
            AssetEvents::POST_UPDATE => 'onAssetPostUpdate',
            AssetEvents::PRE_DELETE => 'onAssetPreDelete',
        ];
    }

    /**
     * @param GenericEvent $event
     */
    public function onFrontendPathThumbnail(GenericEvent $event)
    {
        // rewrite the path for the frontend
        $fileSystemPath = $event->getSubject()->getFileSystemPath();

        $cacheKey = "thumb_s3_" . md5($fileSystemPath);
        $path = Cache::load($cacheKey);

        if (!$path) {
            if (!file_exists($fileSystemPath)) {
                // the thumbnail doesn't exist yet, so we need to create it on request -> Thumbnail controller plugin
                $path = str_replace(PIMCORE_TEMPORARY_DIRECTORY . "/image-thumbnails", "", $fileSystemPath);
            } else {
                if ($this->cdnEnabled) {
                    $path = str_replace(PIMCORE_TEMPORARY_DIRECTORY . "/", $this->cdnDomain . "/", $fileSystemPath);
                } else {
                    $path = str_replace(PIMCORE_TEMPORARY_DIRECTORY . "/", $this->s3TmpUrlPrefix . "/", $fileSystemPath);
                }

                Cache::setForceImmediateWrite(true);
                Cache::save($path, $cacheKey);
            }
        }

        $event->setArgument('frontendPath', $path);
    }

    /**
     * @param GenericEvent $event
     */
    public function onAssetThumbnailCreated(GenericEvent $event)
    {
        $thumbnail = $event->getSubject();

        $fsPath = $thumbnail->getFileSystemPath();

        if ($fsPath && $event->getArgument("generated")) {
            $cacheKey = "thumb_s3_" . md5($fsPath);

            Cache::remove($cacheKey);
        }
    }

    /**
     * @param GenericEvent $event
     */
    public function onFrontEndPathAsset(GenericEvent $event)
    {
        $asset = $event->getSubject();

        if ($this->cdnEnabled) {
            $path = str_replace(PIMCORE_ASSET_DIRECTORY . "/", $this->cdnDomain . "/", $asset->getFileSystemPath());
        } else {
            $path = str_replace(PIMCORE_ASSET_DIRECTORY . "/", $this->s3AssetUrlPrefix . "/", $asset->getFileSystemPath());
        }

        $event->setArgument('frontendPath', $path);
    }

    /**
     * @param AssetEvent $event
     */
    public function onAssetPostUpdate(AssetEvent $event)
    {
        $asset = $event->getAsset();

        if ($this->cloudfrontEnabled) {
            $this->invalidateCloudfrontCache($asset);
        }
    }

    /**
     * @param AssetEvent $event
     */
    public function onAssetPreDelete(AssetEvent $event)
    {
        $asset = $event->getAsset();

        if ($this->cloudfrontEnabled) {
            $this->invalidateCloudfrontCache($asset);
        }
    }

    /**
     * @param Asset $asset
     */
    private function invalidateCloudfrontCache(Asset $asset)
    {
        $request = [
            'DistributionId' => $this->cloudfrontDistributionId,
            'InvalidationBatch' => [
                'CallerReference' => uniqid(),
                'Paths' => [
                    'Items' => [
                        $asset->getRealFullPath(),
                    ],
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