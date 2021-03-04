<?php


namespace PimcoreS3Bundle\Maintenance\Tasks;


use Pimcore\Maintenance\TaskInterface;
use Pimcore\Model\Asset;
use Pimcore\Model\Tool\TmpStore;
use Pimcore\Model\Version;
use PimcoreS3Bundle\Client\S3Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Factory as LockFactory;
use Symfony\Component\Lock\LockInterface;

class LowQualityImagePreviewTask implements TaskInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var LockInterface
     */
    private $lock;
    /**
     * @var S3Client
     */
    private S3Client $s3Client;
    private string $bucketName;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger, LockFactory $lockFactory, S3Client $s3Client, string $bucketName)
    {
        $this->logger = $logger;
        $this->lock = $lockFactory->createLock(self::class, 86400 * 2);
        $this->s3Client = $s3Client;
        $this->bucketName = $bucketName;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        if (date('H') <= 4 && $this->lock->acquire()) {
            // execution should be only sometime between 0:00 and 4:59 -> less load expected
            $this->logger->debug('Execute low quality image preview generation');

            $listing = new Asset\Listing();
            $listing->setCondition("type = 'image'");
            $listing->setOrderKey('id');
            $listing->setOrder('DESC');

            $total = $listing->getTotalCount();
            $perLoop = 10;

            for ($i = 0; $i < (ceil($total / $perLoop)); $i++) {
                $listing->setLimit($perLoop);
                $listing->setOffset($i * $perLoop);

                /** @var Asset\Image[] $images */
                $images = $listing->load();
                foreach ($images as $image) {
                    if (!$this->s3Client->doesObjectExist($this->bucketName, $image->getLowQualityPreviewFileSystemPath())) {
                        try {
                            $this->logger->debug(sprintf('Generate LQIP for asset %s', $image->getId()));
                            $image->generateLowQualityPreview();
                        } catch (\Exception $e) {
                            $this->logger->error($e);
                        }
                    }
                }
                \Pimcore::collectGarbage();
            }
        } else {
            $this->logger->debug('Skip low quality image preview execution, was done within the last 24 hours');
        }
    }
}