# Pimcore AWS S3 & Cloudfront Connector

## Installation 

Add to  `composer.json`

```json
"repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/alpin11/pimcore-s3.git"
    }
],
```

##Installation Pimcore X

```
composer require alpin11/pimcore-s3:dev-master
```

## Pimcore Setup 

Add following content to `/config/pimcore/startup.php`

```php
<?php

use Aws\S3\S3Client;

$s3Client = new S3Client([
    'version' => $_ENV['AWS_S3_VERSION'],
    'region' => $_ENV['AWS_S3_REGION'],
    'credentials' => [
        'key' => $_ENV['AWS_ACCESS_KEY_ID'],
        'secret' => $_ENV['AWS_SECRET_ACCESS_KEY'],
    ],
]);

$default_opts = [
    's3' => [
        'ACL' => 'private',
        'seekable' => true
    ]
];

stream_context_set_default($default_opts);

$s3Client->registerStreamWrapper();

\Pimcore\File::setContext(stream_context_create([
    's3' => ['seekable' => true]
]));

```

Add the following content to `/config/pimcore/constants.php`

```php
<?php

use Symfony\Component\Dotenv\Dotenv;


// load .env file if available
$dotEnvFile = PIMCORE_PROJECT_ROOT . '/.env';
if (file_exists($dotEnvFile)) {
    (new Dotenv())->load($dotEnvFile);
}

if (isset($_ENV['AWS_S3_BUCKET_NAME'])){
    $bucketName = $_ENV['AWS_S3_BUCKET_NAME'];
    $fileWrapperPrefix = 's3://' . $bucketName ;
    $s3BaseUrl = sprintf("https://s3.%s.amazonaws.com", $_ENV['AWS_S3_REGION']);

    define('PIMCORE_ASSET_DIRECTORY', $fileWrapperPrefix . '/assets');
    define('PIMCORE_TEMPORARY_DIRECTORY', $fileWrapperPrefix . '/tmp');
    define("PIMCORE_TRANSFORMED_ASSET_URL", $s3BaseUrl . "/" . $bucketName . "/assets");
    define("PIMCORE_VERSION_DIRECTORY", $fileWrapperPrefix . "/versions");
    define("PIMCORE_RECYCLEBIN_DIRECTORY", $fileWrapperPrefix . "/recyclebin");
    define("PIMCORE_LOG_MAIL_PERMANENT", $fileWrapperPrefix . "/email");
    define("PIMCORE_LOG_FILEOBJECT_DIRECTORY", $fileWrapperPrefix . "/fileobjects");
}
```

## Environment Variables

Those environment variables must be set

```dotenv
AWS_S3_BUCKET_NAME=project-name-bucket
AWS_S3_REGION=eu-central-1
AWS_S3_VERSION=latest

AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=

AWS_CLOUDFRONT_URL=https://cdn.PROJECTNAME.com
AWS_CLOUDFRONT_DISTRIBUTION_ID=
```

### Configuration Reference

Add the following content to `/config/config.yml`

```yaml
pimcore_s3:
    bucket_name: '%env(AWS_S3_BUCKET_NAME)%'
    region: '%env(AWS_S3_REGION)%'
    credentials:
        access_key_id: '%env(AWS_ACCESS_KEY_ID)%'
        secret_access_key: '%env(AWS_SECRET_ACCESS_KEY)%'
    cdn:
        enabled: true
        domain: '%env(AWS_CLOUDFRONT_URL)%'
    cloudfront:
        enabled: true
        id: '%env(AWS_CLOUDFRONT_DISTRIBUTION_ID)%'
```
