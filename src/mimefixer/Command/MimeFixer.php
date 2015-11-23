<?php

namespace mimefixer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use Defr\MimeType;
use Aws\S3\S3Client;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class MimeFixer extends Command
{

    /**
     * @var S3Client
     */
    private $client;

    protected function configure()
    {
        $this
            ->setName('fix')
            ->setDescription('Searches php files in a given path (current path by default)');

        $this->addOption('path', 'p', InputOption::VALUE_OPTIONAL, '');
        $this->addOption('access_key', 'a', InputOption::VALUE_REQUIRED, '');
        $this->addOption('secret', 's', InputOption::VALUE_REQUIRED, '');
        $this->addOption('bucket', 'b', InputOption::VALUE_REQUIRED, '');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $key = $input->getOption('path');
        $this->bucket = $input->getOption('bucket');
        $prefix = $input->getOption('path');

        $this->client = S3Client::factory([
            'region' => 'us-east-1',
            'key' => $input->getOption('access_key'),
            'secret' => $input->getOption('secret'),
            'version' => '2006-03-01'
        ]);

        $objects = $this->client->getIterator('ListObjects', ['Bucket' => $this->bucket, 'Prefix' => $prefix]);
        $output->writeln("Files retrieved!");

        foreach ($objects as $object) {
            $output->writeln($this->updateContentTypeOfKey($object['Key']));
        }
    }

    private function updateContentTypeOfKey($key)
    {
        $mimeType = $this->getMimeTypeForKey($key);

        $this->client->copyObject([
            'Bucket' => $this->bucket,
            'CopySource' => "{$this->bucket}/$key",
            'Key' => $key,
            'ContentType' => $mimeType,
            'MetadataDirective' => 'REPLACE'
        ]);

        return "<fg=green>Update $key</> with $mimeType";
    }

    protected function getMimeTypeForKey($key)
    {
        $path = explode('.', $key);
        $extension = end($path);

        return isset(MimeType::$mimeTypes[$extension]) ? 
                MimeType::$mimeTypes[$extension] : 
                'application/octet-stream';
    }
}
