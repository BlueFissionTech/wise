<?php

// WebBrowser.php
namespace BlueFission\Wise\Prg;

use BlueFission\Connections\Curl;
use BlueFission\Data\FileSystem;
use BlueFission\Net\HTTP;
use BlueFission\Services\Service;
use BlueFission\Str;
use BlueFission\Wise\Sys\StorageRoot;
use Symfony\Component\Panther\Client;

class WebBrowser extends Service
{
    private $client;
    private StorageRoot $storageRoot;

    public function __construct(?StorageRoot $storageRoot = null)
    {
        parent::__construct();
        $this->storageRoot = $storageRoot ?? new StorageRoot();
        $this->client = Client::createChromeClient();
    }

    public function browse(string $url): bool
    {
        $this->client->request('GET', $url);
        return true;
    }

    public function click(string $selector): void
    {
        $element = $this->client->findElement($selector);
        $element->click();
    }

    public function fillForm(array $formData, string $submitButtonSelector): void
    {
        foreach ($formData as $selector => $value) {
            $element = $this->client->findElement($selector);
            $element->sendKeys($value);
        }

        $submitButton = $this->client->findElement($submitButtonSelector);
        $submitButton->click();
    }

    public function getMedia(): array
    {
        $media = [];
        $imgElements = $this->dom->getElementsByTagName('img');
        $videoElements = $this->dom->getElementsByTagName('video');
        $audioElements = $this->dom->getElementsByTagName('audio');

        foreach ($imgElements as $imgElement) {
            $src = $imgElement->getAttribute('src');
            $media[] = ['url' => $src, 'type' => 'image'];
        }

        foreach ($videoElements as $videoElement) {
            $src = $videoElement->getAttribute('src');
            $media[] = ['url' => $src, 'type' => 'video'];
        }

        foreach ($audioElements as $audioElement) {
            $src = $audioElement->getAttribute('src');
            $media[] = ['url' => $src, 'type' => 'audio'];
        }

        return $media;
    }

    public function downloadMedia(string $url): void
    {
        $path = HTTP::urlPath($url);
        if (!Str::is($path)) {
            return;
        }

        $filename = FileSystem::fileBasename($path);
        if (Str::isEmpty($filename)) {
            return;
        }

        $connection = new Curl(['target' => $url, 'method' => 'get']);
        $connection->open()->query();
        $contents = $connection->result();
        $connection->close();

        if (!Str::is($contents)) {
            return;
        }

        $storage = new FileSystem([
            'root' => $this->storageRoot->prepare('downloads'),
            'mode' => 'w+',
            'filter' => [],
        ]);
        $storage->open($filename);
        $storage->contents($contents);
        $storage->write();
        $storage->close();
    }

    public function getLinks(string $selector): array
    {
        $elements = $this->client->findElements($selector);
        $links = [];

        foreach ($elements as $element) {
            $links[] = $element->getAttribute('href');
        }

        return $links;
    }

    public function getForms(): array
    {
        $forms = [];
        $formElements = $this->dom->getElementsByTagName('form');

        foreach ($formElements as $formElement) {
            $forms[] = [
                'action' => $formElement->getAttribute('action'),
                'method' => $formElement->getAttribute('method'),
                'id' => $formElement->getAttribute('id')
            ];
        }

        return $forms;
    }

    public function getFormFields(string $formId): array
    {
        $formFields = [];
        $formElement = $this->dom->getElementById($formId);

        if ($formElement) {
            $inputElements = $formElement->getElementsByTagName('input');
            foreach ($inputElements as $inputElement) {
                $formFields[] = [
                    'name' => $inputElement->getAttribute('name'),
                    'type' => $inputElement->getAttribute('type')
                ];
            }
        }

        return $formFields;
    }

    public function viewPageAsPlainText(): string
    {
        $content = '';

        $body = $this->dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $content = $this->getTextContent($body);
        }

        return strip_tags($content);
    }


    public function goBack(): void
    {
        $this->client->executeScript('window.history.back();');
    }

    public function goForward(): void
    {
        $this->client->executeScript('window.history.forward();');
    }

    public function close(): void
    {
        $this->client->quit();
    }
}
