<?php


namespace PimcoreS3Bundle\Maintenance\Tasks;


use Pimcore\Maintenance\TaskInterface;
use Pimcore\Model\Version;
use PimcoreS3Bundle\Client\S3Client;
use Psr\Log\LoggerInterface;

class VersionsCompressTask implements TaskInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var S3Client
     */
    private $s3Client;

    /**
     * @var string
     */
    private $bucketName;

    /**
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger, S3Client $s3Client, string $bucketName)
    {
        $this->logger = $logger;
        $this->s3Client = $s3Client;
        $this->bucketName = $bucketName;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $perIteration = 100;
        $alreadyCompressedCounter = 0;

        $list = new Version\Listing();
        $list->setCondition('date < ' . (time() - 86400 * 30));
        $list->setOrderKey('date');
        $list->setOrder('DESC');
        $list->setLimit($perIteration);

        $total = $list->getTotalCount();
        $iterations = ceil($total / $perIteration);

        for ($i = 0; $i < $iterations; $i++) {
            $this->logger->debug('iteration ' . ($i + 1) . ' of ' . $iterations);

            $list->setOffset($i * $perIteration);

            $versions = $list->load();

            foreach ($versions as $version) {
                if ($this->s3Client->doesObjectExist($this->bucketName, $version->getFilePath())) {

                    gzcompressfile($version->getFilePath(), 9);

                    $this->s3Client->deleteObject([
                        'Bucket' => $this->bucketName,
                        'Key' => $version->getFilePath()
                    ]);

                    $alreadyCompressedCounter = 0;

                    $this->logger->debug('version compressed:' . $version->getFilePath());
                    $this->logger->debug('Waiting 1 sec to not kill the server...');
                    sleep(1);
                } else {
                    $alreadyCompressedCounter++;
                }
            }

            \Pimcore::collectGarbage();

            // check here how many already compressed versions we've found so far, if over 100 skip here
            // this is necessary to keep the load on the system low
            // is would be very unusual that older versions are not already compressed, so we assume that only new
            // versions need to be compressed, that's not perfect but a compromise we can (hopefully) live with.
            if ($alreadyCompressedCounter > 100) {
                $this->logger->debug('Over ' . $alreadyCompressedCounter . " versions were already compressed before, it doesn't seem that there are still uncompressed versions in the past, skip...");

                return;
            }
        }
    }
}