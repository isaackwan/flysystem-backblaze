<?php

namespace Mhetreramesh\Flysystem;

use ChrisWhite\B2\Client;
use League\Flysystem\Adapter\AbstractAdapter;
use League\Flysystem\Adapter\Polyfill\NotSupportingVisibilityTrait;
use League\Flysystem\Config;
use GuzzleHttp\Stream;

class BackblazeAdapter extends AbstractAdapter {

    use NotSupportingVisibilityTrait;

    protected $client;

    protected $bucketName;

    public function __construct(Client $client, $bucketName)
    {
        $this->client = $client;
        $this->bucketName = $bucketName;
    }

    /**
     * {@inheritdoc}
     */
    public function has($path)
    {
        return $this->getClient()->fileExists(['FileName' => $path, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function write($path, $contents, Config $config)
    {
        $file = $this->getClient()->upload([
            'BucketName' => $this->bucketName,
            'FileName' => $path,
            'Body' => $contents
        ]);
        return $this->normalizeFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function writeStream($path, $resource, Config $config)
    {
        $file = $this->getClient()->upload([
            'BucketName' => $this->bucketName,
            'FileName' => $path,
            'Body' => $resource
        ]);
        return $this->normalizeFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function update($path, $contents, Config $config)
    {
        $file = $this->getClient()->upload([
            'BucketName' => $this->bucketName,
            'FileName' => $path,
            'Body' => $contents
        ]);
        return $this->normalizeFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function updateStream($path, $resource, Config $config)
    {
        $file = $this->getClient()->upload([
            'BucketName' => $this->bucketName,
            'FileName' => $path,
            'Body' => $resource
        ]);
        return $this->normalizeFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function read($path)
    {
        $fileContent = $this->getClient()->download([
            'BucketName' => $this->bucketName,
            'FileName' => $path
        ]);
        return ['contents' => $fileContent];
    }

    /**
     * {@inheritdoc}
     */
    public function readStream($path)
    {
        $stream = Stream\Stream::factory();
        $download = $this->getClient()->download([
            'BucketName' => $this->bucketName,
            'FileName' => $path,
            'SaveAs' => $stream,
        ]);
        $stream->seek(0);
        try {
            $resource = Stream\GuzzleStreamWrapper::getResource($stream);
        } catch (InvalidArgumentException $e) {
            return false;
        }
        return $download === true ? ['stream' => $resource] : false;
    }

    /**
     * {@inheritdoc}
     */
    public function rename($path, $newpath)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function copy($path, $newPath)
    {
        return $this->getClient()->upload([
            'BucketName' => $this->bucketName,
            'FileName' => $newPath,
            'Body' => @file_get_contents($path)
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete($path)
    {
        return $this->getClient()->deleteFile(['FileName' => $path, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function deleteDir($path)
    {
        return $this->getClient()->deleteFile(['FileName' => $path, 'BucketName' => $this->bucketName]);
    }

    /**
     * {@inheritdoc}
     */
    public function createDir($path, Config $config)
    {
        return $this->getClient()->upload([
            'BucketName' => $this->bucketName,
            'FileName' => $path . '/.bzEmpty',
            'Body' => ''
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getMetadata($path)
    {
        $file = $this->getClient()->getFile(['FileName' => $path, 'BucketName' => $this->bucketName]);
        return $this->normalizeFileInfo($file);
    }

    /**
     * {@inheritdoc}
     */
    public function getMimetype($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getSize($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getTimestamp($path)
    {
        return $this->getMetadata($path);
    }

    /**
     * {@inheritdoc}
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * {@inheritdoc}
     */
    public function listContents($directory = '', $recursive = false)
    {
        $fileObjects = $this->getClient()->listFiles([
            'BucketName' => $this->bucketName,
        ]);
        if ($recursive === true && $directory === '') {
            $regex = '';
        } else if ($recursive === true && $directory !== '') {
            $regex = '/^' . preg_quote($directory) . '\/.+$/';
        } else if ($recursive === false && $directory === '') {
            $regex = '/^(?!.+\\/).+$/';
        } else if ($recursive === false && $directory !== '') {
            $regex = '/^' . preg_quote($directory) . '\/(?!.+\\/).+$/';
        } else {
            throw new \InvalidArgumentException();
        }
        if (!empty($regex)) {
            $fileObjects = array_filter($fileObjects, function ($fileObject) use ($directory, $regex) {
                return 1 === preg_match($regex, $fileObject->getName());
            });
        }
        $normalized = array_map(function ($fileObject) {
            return $this->normalizeFileInfo($fileObject);
        }, $fileObjects);
        return array_values($normalized);
    }

    /**
     * Get file info
     *
     * @param $file
     *
     * @return array
     */

    protected function normalizeFileInfo($file)
    {
        $normalized = [
            'type' => 'file',
            'path' => $file->getName(),
            'timestamp' => substr($file->getUploadTimestamp(), 0, -3),
            'size' => $file->getSize(),
            'mimetype' => $file->getType(),
        ];

        return $normalized;
    }
}
