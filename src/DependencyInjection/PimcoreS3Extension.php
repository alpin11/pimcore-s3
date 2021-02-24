<?php


namespace PimcoreS3Bundle\DependencyInjection;


use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;


class PimcoreS3Extension extends Extension
{

    /**
     * @inheritDoc
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);

        $bucketName = $config['bucket_name'];
        $region = $config['region'];
        $fileWrapperPrefix = 's3://' . $config['bucket_name'];
        $s3BaseUrl = sprintf("https://s3.%s.amazonaws.com", $region);
        $assetDirectory = $fileWrapperPrefix . '/assets';
        $tmpDirectory = $fileWrapperPrefix . '/tmp';

        $container->setParameter('pimcore_s3.bucket_name', $bucketName);
        $container->setParameter('pimcore_s3.region', $region);
        $container->setParameter('pimcore_s3.file_wrapper_prefix', $fileWrapperPrefix);
        $container->setParameter('pimcore_s3.base_url', $s3BaseUrl);
        $container->setParameter('pimcore_s3.credentials.access_key_id', $config['credentials']['access_key_id']);
        $container->setParameter('pimcore_s3.credentials.secret_access_key', $config['credentials']['secret_access_key']);
        $container->setParameter('pimcore_s3.pimcore_directory.tmp', $tmpDirectory);
        $container->setParameter('pimcore_s3.pimcore_directory.asset', $assetDirectory);
        $container->setParameter('pimcore_s3.cdn.enabled', $config['cdn']['enabled']);
        $container->setParameter('pimcore_s3.cdn.domain', $config['cdn']['domain']);
        $container->setParameter('pimcore_s3.cloudfront.enabled', $config['cloudfront']['enabled']);
        $container->setParameter('pimcore_s3.cloudfront.id', $config['cloudfront']['id']);
        $container->setParameter('pimcore_s3.cloudfront.client.version', $config['cloudfront']['client']['version']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }
}
