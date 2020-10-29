# Pimcore AWS S3 & Cloudfront Connector

## Installation 

Add to  `composer.json`

```json
"repositories": [
    {
      "type": "vcs",
      "url": "https://bitbucket.org/alpin11gmbh/pimcores3bundle.git"
    }
],
```

After that install package

```shell script
composer require alpin11/pimcore-s3:dev-master
```

## Pimcore Setup 

Add following content to `/app/startup.php`

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

Add the following content to `/app/constants.php`

```php
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
```

## Environment Variables

Those environment variables must be set

```dotenv
AWS_ACCESS_KEY_ID=<access-key-id>
AWS_SECRET_ACCESS_KEY=<secret-access-key>

AWS_S3_REGION=<default-region>
AWS_S3_BUCKET_NAME=<bucket-name>
```

### Configuration Reference

```yaml
pimcore_s3:
    bucket_name:          ~
    region:               ~
    credentials:
        access_key_id:        ~
        secret_access_key:    ~
    cloudfront:
        enabled:              false
        base_url:             ~
        origin_path:          null
        id:                   null
        client:
            version:              latest
```