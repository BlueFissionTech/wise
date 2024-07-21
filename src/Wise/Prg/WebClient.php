<?php

// SimpleTextWebBrowsingService.php
namespace App\Business\Services;

use BlueFission\Services\Service;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

class SimpleTextWebBrowsingService extends Service
{
    private $html;
    private $dom;
    private $xpath;
    private $history;
    private $currentIndex;
    private $client;
    private $cookieJar;
    private $_links;

    public function __construct()
    {
        // $this->xpath = store('_browser_xpath') ?? null;
        $this->dom = new DOMDocument();
        $this->history = store('_system.browser.history') ?? [];
        $this->currentIndex = store('_system.browser.current_index') ?? -1;
        $this->_links = store('_system.browser.links') ?? [];

        $this->client = new Client();
        $this->cookieJar = new CookieJar();

        parent::__construct();
    }

    public function getCurrentUrl()
    {
        return $this->history[$this->currentIndex];
    }

    public function browse(string $url = ""): bool
    {
        if (isset($this->history[$this->currentIndex]) && $url == "" && $this->xpath == null) {
            $url = $this->history[$this->currentIndex];
        } elseif ($url == "" && $this->xpath != null) {
            return true;
        }
        if ($url != "") {
            $exists = false;
            $file_headers = @get_headers($url);
            if(!$file_headers || $file_headers[0] == 'HTTP/1.1 404 Not Found') {
                $exists = false;
            } else {
                $exists = true;
                $this->_links = [];
                try {
                    $this->html = file_get_contents($url);
                    if ($this->html === false) {
                        throw new \Exception('Error loading HTML.');
                    }
                    @$this->dom->loadHTML($this->html);
                    $this->xpath = new DOMXPath($this->dom);

                    $this->history[] = $url;
                    $this->currentIndex++;

                    // Debug message
                    // echo "HTML loaded successfully.\n";
                } catch (\Exception $e) {
                    $this->html = '';
                    $this->dom = new DOMDocument();
                    $this->xpath = new DOMXPath($this->dom);
                    $exists = false;

                    // Debug message
                    // echo "Error loading HTML: " . $e->getMessage() . "\n";
                }
            }
            return $exists;
        }
        return false;
    }

    public function viewPageContent(): string
    {
        $content = '';

        $body = $this->dom->getElementsByTagName('body')->item(0);
        if ($body) {
            // Debug message
            // echo "Body content:\n" . $body->nodeValue . "\n\n";

            $content = $this->getTextContent($body);
        }

        return $content;
    }

    private function getTextContent($element): string
    {
        $content = '';

        foreach ($element->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text = trim($child->textContent);
                if (!empty($text)) {
                    $content .= $text . ' ';
                }
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->tagName);
                if ($tagName !== 'script' && $tagName !== 'style' && $tagName !== 'noscript') {
                    $content .= $this->getTextContent($child);
                }
            }
        }

        return $content;
    }

    public function getLinks(): array
    {
         if ( count($this->_links) ) {
            $links = $this->_links;
         } else {
            $links = [];
            $aElements = $this->dom->getElementsByTagName('a');

            foreach ($aElements as $aElement) {
                $href = $aElement->getAttribute('href');
                $text = $aElement->textContent;
                $selector = $this->generateUniqueSelector($aElement);

                $links[] = [
                    'url' => $href,
                    'text' => $text,
                    'selector' => $selector,
                ];
            }

            $this->_links = $links;
        }
        return $links;
    }

    private function generateUniqueSelector($element): string
    {
        $path = [];

        while ($element && $element->nodeType === XML_ELEMENT_NODE) {
            $index = 0;
            $sibling = $element->previousSibling;

            while ($sibling) {
                if ($sibling->nodeType === XML_ELEMENT_NODE && $sibling->tagName === $element->tagName) {
                    $index++;
                }
                $sibling = $sibling->previousSibling;
            }

            $part = $element->tagName . '[' . $index . ']';
            array_unshift($path, $part);

            $element = $element->parentNode;
        }

        return implode('/', $path);
    }

    public function goBack(): void
    {
        if ($this->currentIndex > 0) {
            $this->currentIndex--;
            $this->browse($this->history[$this->currentIndex]);
        }
    }

    public function goForward(): void
    {
        if ($this->currentIndex < count($this->history) - 1) {
            $this->currentIndex++;
            $this->browse($this->history[$this->currentIndex]);
        }
    }

    public function downloadMedia(string $url, string $destination): void
    {
        $content = file_get_contents($url);
        file_put_contents($destination, $content);
    }

    public function getMediaLinks(): array
    {
        $mediaLinks = [];
        $imgElements = $this->dom->getElementsByTagName('img');
        $videoElements = $this->dom->getElementsByTagName('video');
        $audioElements = $this->dom->getElementsByTagName('audio');

        foreach ($imgElements as $imgElement) {
            $mediaLinks[] = $imgElement->getAttribute('src');
        }

        foreach ($videoElements as $videoElement) {
            $mediaLinks[] = $videoElement->getAttribute('src');
        }

        foreach ($audioElements as $audioElement) {
            $mediaLinks[] = $audioElement->getAttribute('src');
        }

        return $mediaLinks;
    }

    public function downloadPage(string $destination): void
    {
        file_put_contents($destination, $this->html);
    }

    public function fillForm(array $fields): void
    {
        foreach ($fields as $fieldName => $fieldValue) {
            $fieldElement = $this->xpath->query("//*[@name='$fieldName']")->item(0);
            if ($fieldElement) {
                $fieldElement->setAttribute('value', $fieldValue);
            }
        }
    }

    public function clickElement(string $selector): void
    {
        // For this example, we assume the selector is for an 'a' element.
        $element = $this->xpath->query($selector)->item(0);
        if ($element && $element->tagName === 'a') {
            $url = $element->getAttribute('href');
            $this->browse($url);
        }
    }

    public function submitForm(string $selector): void
    {
        $formElement = $this->xpath->query($selector)->item(0);
        if ($formElement && $formElement->tagName === 'form') {
            $method = strtoupper($formElement->getAttribute('method')) ?: 'GET';
            $action = $formElement->getAttribute('action') ?: $this->history[$this->currentIndex];
            $fields = [];

            $inputElements = $this->xpath->query($selector . '//input');
            foreach ($inputElements as $inputElement) {
                $name = $inputElement->getAttribute('name');
                $value = $inputElement->getAttribute('value');
                if ($name) {
                    $fields[$name] = $value;
                }
            }

            $options = [
                'form_params' => $fields,
                'cookies' => $this->cookieJar,
            ];

            $response = $this->client->request($method, $action, $options);
            @$this->dom->loadHTML($response->getBody());
            $this->xpath = new DOMXPath($this->dom);

            $this->history[] = $action;
            $this->currentIndex++;
        }
    }

    public function __destruct()
    {
        store('_system.browser.history', $this->history);
        store('_system.browser.links', $this->_links);
        // store('_browser_xpath', $this->xpath);
        store('_system.browser.current_index', $this->currentIndex);
    }
}

