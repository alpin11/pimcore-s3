<?php

namespace PimcoreS3Bundle\EventListener;

use Aws\S3\S3Client;
use Pimcore\Cache;
use Pimcore\Event\AssetEvents;
use Pimcore\Event\FrontendEvents;
use Pimcore\Event\Model\AssetEvent;
use Pimcore\Model\Asset;
use PimcoreS3Bundle\Client\CloudFrontClient;
use PimcoreS3Bundle\Service\CloudFrontService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class AssetListener implements EventSubscriberInterface
{
    private CloudFrontService $cloudFrontService;

    protected CloudFrontClient $cloudFrontClient;

    protected S3Client $s3Client;

    protected string $baseUrl;

    protected string $s3TmpUrlPrefix;

    protected string $s3AssetUrlPrefix;

    protected bool $cloudfrontEnabled;

    protected ?string $cloudfrontDistributionId;

    protected bool $cdnEnabled;

    protected ?string $cdnDomain;

    private string $bucketName;

    /**
     * @param \PimcoreS3Bundle\Service\CloudFrontService $cloudFrontService
     * @param \PimcoreS3Bundle\Client\CloudFrontClient $cloudFrontClient
     * @param string $region
     * @param string $accessKeyId
     * @param string $secretAccessKey
     * @param string $bucketName
     * @param string $baseUrl
     * @param string $tmpUrl
     * @param string $assetUrl
     * @param bool $cloudfrontEnabled
     * @param string|null $cloudfrontDistributionId
     * @param bool $cdnEnabled
     * @param string|null $cdnDomain
     */
    public function __construct(
        CloudFrontService $cloudFrontService,
        CloudFrontClient $cloudFrontClient,
        string $region,
        string $accessKeyId,
        string $secretAccessKey,
        string $bucketName,
        string $baseUrl,
        string $tmpUrl,
        string $assetUrl,
        bool $cloudfrontEnabled = false,
        string $cloudfrontDistributionId = null,
        bool $cdnEnabled = false,
        string $cdnDomain = null
    ) {
        $this->cloudFrontService = $cloudFrontService;
        $this->baseUrl = $baseUrl;
        $this->s3TmpUrlPrefix = $this->baseUrl . str_replace("s3:/", "", $tmpUrl);
        $this->s3AssetUrlPrefix = $this->baseUrl . str_replace("s3:/", "", $assetUrl);
        $this->cloudfrontEnabled = $cloudfrontEnabled;
        $this->cloudFrontClient = $cloudFrontClient;
        $this->cloudfrontDistributionId = $cloudfrontDistributionId;
        $this->cdnEnabled = $cdnEnabled;
        $this->cdnDomain = $cdnDomain;
        $this->bucketName = $bucketName;
        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $region,
            'credentials' => [
                'key' => $accessKeyId,
                'secret' => $secretAccessKey,
            ],
        ]);
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
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
    public function onFrontendPathThumbnail(GenericEvent $event): void
    {
        /** @var Asset\Image\Thumbnail $subject */
        $subject = $event->getSubject();
        $fileSystemPath = $event->getSubject()->getFileSystemPath();

        $cacheKey = "thumb_s3_" . md5($fileSystemPath);
        $path = Cache::load($cacheKey);

        if (!$path) {
            $key = str_replace("s3://" . $this->bucketName . "/", '', $fileSystemPath);
            if (!$this->s3Client->doesObjectExist($this->bucketName, $key)) {

                if (PHP_SAPI === 'cli') {
                    // cant pass to controller
                    $image = $subject->getAsset();
                    if ($image instanceof Asset\Image) {
                        $thumbnail = $image->getThumbnail($subject->getConfig(), false);

                        $fsPath = $thumbnail->getFileSystemPath();

                        if ($this->cdnEnabled) {
                            $path = str_replace(PIMCORE_TEMPORARY_DIRECTORY . "/", $this->cdnDomain . "/", $fsPath);
                        } else {
                            $path = str_replace(PIMCORE_TEMPORARY_DIRECTORY . "/", $this->s3TmpUrlPrefix . "/", $fsPath);
                        }


                        Cache::setForceImmediateWrite(true);
                        Cache::save($path, $cacheKey);
                    }
                } else {
                    $path = str_replace(PIMCORE_TEMPORARY_DIRECTORY . "/image-thumbnails", "", $fileSystemPath);
                }
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
    public function onAssetThumbnailCreated(GenericEvent $event): void
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
    public function onFrontEndPathAsset(GenericEvent $event): void
    {
        $asset = $event->getSubject();

        if ($asset instanceof Asset\Folder) {
            return;
        }

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
    public function onAssetPostUpdate(AssetEvent $event): void
    {
        $asset = $event->getAsset();

        if ($asset instanceof Asset\Folder) {
            return;
        }

        if ($this->cloudfrontEnabled) {
            $this->cloudFrontService->invalidateCloudfrontCache($asset);
        }
    }

    /**
     * @param AssetEvent $event
     */
    public function onAssetPreDelete(AssetEvent $event): void
    {
        $asset = $event->getAsset();

        if ($this->cloudfrontEnabled) {
            $this->cloudFrontService->invalidateCloudfrontCache($asset);
        }
    }
}
