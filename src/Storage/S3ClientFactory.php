<?php

declare(strict_types=1);

namespace App\Storage;

use Aws\S3\S3Client;

/**
 * The S3 client of the storages on S3 (config/packages/flysystem.yaml): AWS, or a compatible server
 * (RustFS in development, Scaleway, OVH...) when S3_ENDPOINT is set.
 */
final class S3ClientFactory
{
    public static function create(string $region, string $endpoint, string $accessKey, string $secretKey, bool $pathStyle): S3Client
    {
        $options = [
            'version' => 'latest',
            'region' => $region,
            // Most of the compatible servers need the bucket in the path, not in the host name.
            'use_path_style_endpoint' => $pathStyle,
        ];

        if ($endpoint !== '') {
            $options['endpoint'] = $endpoint;
        }

        // Without keys, the SDK looks for them on its own (environment, IAM role of the server).
        if ($accessKey !== '') {
            $options['credentials'] = [
                'key' => $accessKey,
                'secret' => $secretKey,
            ];
        }

        return new S3Client($options);
    }
}
