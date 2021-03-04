<?php


namespace PimcoreS3Bundle\Maintenance\Tasks;


use Pimcore\Image\ImageOptimizerInterface;
use Pimcore\Maintenance\TaskInterface;
use Pimcore\Model\Tool\TmpStore;
use PimcoreS3Bundle\Client\S3Client;
use Psr\Log\LoggerInterface;

class ImageOptimizeTask implements TaskInterface
{
    /**
     * @var ImageOptimizerInterface
     */
    private $optimizer;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var S3Client
     */
    private S3Client $s3Client;
    private string $bucketName;

    /**
     * @param ImageOptimizerInterface $optimizer
     * @param LoggerInterface $logger
     */
    public function __construct(ImageOptimizerInterface $optimizer, LoggerInterface $logger, S3Client $s3Client, string $bucketName)
    {
        $this->optimizer = $optimizer;
        $this->logger = $logger;
        $this->s3Client = $s3Client;
        $this->bucketName = $bucketName;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $ids = TmpStore::getIdsByTag('image-optimize-queue');

        // id = path of image relative to PIMCORE_TEMPORARY_DIRECTORY
        foreach ($ids as $id) {
            $tmpStore = TmpStore::get($id);

            if ($tmpStore && $tmpStore->getData()) {
                $file = PIMCORE_TEMPORARY_DIRECTORY . '/' . $tmpStore->getData();
                if ($this->s3Client->doesObjectExist($this->bucketName, $file)) {
                    $originalFilesize = filesize($file);
                    $this->optimizer->optimizeImage($file);

                    $this->logger->debug('Optimized image: ' . $file . ' saved ' . formatBytes($originalFilesize - filesize($file)));
                } else {
                    $this->logger->debug('Skip optimizing of ' . $file . " because it doesn't exist anymore");
                }
            }

            TmpStore::delete($id);
        }
    }
}