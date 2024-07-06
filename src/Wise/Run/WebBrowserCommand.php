<?php

// WebBrowserCommand.php
namespace BlueFission\BlueCore\Business\Commands;

use BlueFission\Services\Service;
use BlueFission\BlueCore\Business\Services\SimpleTextWebBrowsingService;

class WebBrowserCommand extends Service
{
    private $webBrowsingService;
    private $_page;
    private $_perPage;
    private $_content;
    private $_bookmarks;

    public function __construct(SimpleTextWebBrowsingService $webBrowsingService)
    {
        $this->webBrowsingService = $webBrowsingService;

        $this->_page = (int)store('_system.website.page');
        $this->_perPage = (int)store('_system.website.per_page');
        $this->_linkPage = (int)store('_system.website.link_page');
        $this->_perLinkPage = (int)store('_system.website.per_link_page');
        $this->_content = store('_system.website.content') ?? [];
        $this->_bookmarks = store('_system.website.bookmarks') ?? [];

        $this->_page = $this->_page > 0 ? $this->_page : 1;
        $this->_perPage = $this->_perPage > 0 ? $this->_perPage : 2048;
        $this->_linkPage = $this->_linkPage > 0 ? $this->_linkPage : 1;
        $this->_perLinkPage = $this->_perLinkPage > 0 ? $this->_perLinkPage : 20;

        parent::__construct();
    }

    public function handle($behavior, $args): void
    {
        $action = $behavior->name();
        switch( $action ) {
            case 'next':
                if (count($args) < 1) {
                    $this->goForward($behavior, $args);
                } else {
                    $this->listItems($behavior, $args);
                }
            break;
            case 'previous':
                if (count($args) < 1) {
                    $this->goBack($behavior, $args);
                } else {
                    $this->listItems($behavior, $args);
                }
            break;
            case 'select':
            break;
            default:
                $this->_response = "Invalid command.";
            break;
        }
    }

    public function create($behavior, $args): void
    {
        $this->_response = "I'm a web browser for viewing websites. Not creating them.";
    }

    public function browse($behavior, $args): void
    {
        if (isset($args[0])) {
            $url = $args[0];
            $success = $this->webBrowsingService->browse($url);
            if ($success) {
                $this->_response = "Browsing to: $url\nEnter the command `show website` to view the page content.";
            } else {
                $this->_response = "Location $url not found.";
            }
        } else {
            $this->_response = "Please provide a URL to browse.";
        }
    }

    public function viewPageContent($behavior, $args): void
    {
        $this->webBrowsingService->browse();
        $this->_content = $this->webBrowsingService->viewPageContent();
        $page = $this->_page;
        $totalPages = ceil(strlen($this->_content) / $this->_perPage);

        if ($page < 1) {
            $page = 1;
        } elseif ($page > $totalPages) {
            $page = $totalPages;
        }

        $response = substr($this->_content, (($this->_page) * $this->_perPage - $this->_perPage), $this->_perPage);

        $response .= "\n\n";
        $response .= "Showing screen {$page} of {$totalPages}." . PHP_EOL;
        $response .= "Type `list website [links|media|forms]` to navigate interactive elements." . PHP_EOL;
        $response .= "Type `less of website` or `more of website` to move through screens." . PHP_EOL;

        $this->_response = $response;
    }

    public function getLinks($behavior, $args): void
    {
        $links = $this->webBrowsingService->getLinks();
        if ($links !== null) {
            $total = count($links);
            $totalPages = ceil($total / $this->_perLinkPage);
            
            $page = isset($args[1]) ? $args[1] : $this->_linkPage;

            if ($page < 1) {
                $page = 1;
            } elseif ($page > $totalPages) {
                $page = $totalPages;
            }
            $this->_linkPage = $page;

            $pageStart = ($page - 1) * $this->_perLinkPage;
            $pageEnd = $pageStart + $this->_perLinkPage;
            $i = 0;
            $count = 0;
            $response = "";

            $response = "Links on the page:\n\n";
            foreach ($links as $link) {
                if ($i >= $pageStart && $i < $pageEnd) {
                    $number = ($count+1) + ($this->_perLinkPage * $page) - $this->_perLinkPage;
                    $response .= "#{$number}: ".trim($link['text']). " - {$link['url']}\n";
                    $count++;
                }
                $i++;
            }

            $response .= "Showing {$count} of {$total} links. Page {$page} of {$totalPages}." . PHP_EOL;
            $response .= "Use command `select website link <number>` to follow that link." . PHP_EOL;
            $response .= "Use command `previous website links` or `next website links` to move through pages." . PHP_EOL;

        } else {
            $response = "No links found on this page.";
        }

        $this->_response = rtrim($response);
    }

    public function fillForm($behavior, $args): void
    {
        if (isset($args[0])) {
            $fields = $args[0];
            $this->webBrowsingService->fillForm($fields);
            $this->_response = "Form fields filled.";
        } else {
            $this->_response = "Please provide an array of form fields to fill.";
        }
    }

    public function clickElement($behavior, $args): void
    {
        if (isset($args[0])) {
            $selector = $args[0];
            $this->webBrowsingService->clickElement($selector);
            $this->_response = "Element clicked.";
        } else {
            $this->_response = "Please provide a selector for the element to click.";
        }
    }

    public function submitForm($behavior, $args): void
    {
        if (isset($args[0])) {
            $selector = $args[0];
            $this->webBrowsingService->submitForm($selector);
            $this->_response = "Form submitted.";
        } else {
            $this->_response = "Please provide a selector for the form to submit.";
        }
    }

    public function getForms($behavior, $args): void
    {
        $forms = $this->webBrowsingService->getForms();
        $response = "Forms on the page:\n\n";
        foreach ($forms as $form) {
            $response .= "ID: {$form['id']}, Action: {$form['action']}, Method: {$form['method']}\n";
        }
        $this->_response = rtrim($response);
    }

    public function getFormFields($behavior, $args): void
    {
        if (isset($args[0])) {
            $formId = $args[0];
            $formFields = $this->webBrowsingService->getFormFields($formId);

            $response = "Fields in form with ID '$formId':\n\n";
            foreach ($formFields as $field) {
                $response .= "Name: {$field['name']}, Type: {$field['type']}\n";
            }
            $this->_response = rtrim($response);
        } else {
            $this->_response = "Please provide a form ID to retrieve its fields.";
        }
    }

    public function listItems($behavior, $args): void
    {
        $this->webBrowsingService->browse();

        if ($behavior->name() == 'next') {
            $this->_linkPage++;
        } elseif ($behavior->name() == 'previous') {
            $this->_linkPage--;
        }

        if (isset($args[0])) {
            $itemType = strtolower($args[0]);
            switch ($itemType) {
                case 'links':
                    $this->getLinks($behavior, []);
                    break;
                case 'forms':
                    $this->getForms($behavior, []);
                    break;
                case 'media':
                    $this->listMedia($behavior, []);
                    break;
                default:
                    $this->_response = "Invalid item type. Please specify 'links', 'forms', or 'media'.";
            }
        } else {
            $this->_response = "Please specify whether to list 'links', 'forms', or 'media' on this page.";
        }
    }

    public function selectItem($behavior, $args): void
    {
        if ( count($args) == 1 ) {
            $args = explode ( ' ', $args[0] );
        }
        if (count($args) >= 2) {
            $this->webBrowsingService->browse();

            $number = null;
            $selector = 'body';
            $itemType = strtolower($args[0]);
            if (is_numeric($args[1])) {
                $number = $args[1];
            } else {
                $selector = $args[1];
            }
            switch ($itemType) {
                case 'link':
                    if ($number) {
                        $links = $this->webBrowsingService->getLinks();
                        $link = isset($links[$number]) ? $links[$number] : null;
                        if ($link) {
                            $selector = $link['selector'];
                            // $selector = "[href={$link['url']}]";
                        }
                    }
                    $this->clickElement($behavior, [$selector]);
                    break;
                case 'form':
                    $this->getFormFields($behavior, [$selector]);
                    break;
                case 'media':
                    $this->selectMedia($behavior, [$selector]);
                    break;
                default:
                    $this->_response = "Invalid item type. Please use 'select website {type} {selector}' with type as 'link', 'form', or 'media'.";
            }
        } else {
            $this->_response = "Please use 'select website <type> <selector>' with type as 'link', 'form', or 'media'.";
        }
    }

    public function listMedia($behavior, $args): void
    {
        $media = $this->webBrowsingService->getMedia();

        if (!empty($media)) {
            $response = "Here is a list of media on the page:\n\n";
            foreach ($media as $index => $mediaItem) {
                $response .= ($index + 1) . ". " . $mediaItem['url'] . "\n";
            }
            $this->_response = rtrim($response);
        } else {
            $this->_response = "No media found on this page.";
        }
    }

    public function selectMedia($behavior, $args): void
    {
        if (isset($args[0]) && is_numeric($args[0])) {
            $index = intval($args[0]) - 1;
            $media = $this->webBrowsingService->getMedia();

            if (isset($media[$index])) {
                $this->webBrowsingService->downloadMedia($media[$index]['url']);
                $this->_response = "The selected media has been downloaded.";
            } else {
                $this->_response = "Invalid media selection. Please provide a valid index.";
            }
        } else {
            $this->_response = "Please provide a numeric index to select media.";
        }
    }

    public function bookmark()
    {
        $this->_bookmarks[] = $this->webBrowsingService->getCurrentURL();
        $this->_response = "Page bookmarked.";
    }

    public function viewPageAsPlainText($behavior, $args): void
    {
        $this->_response = $this->webBrowsingService->viewPageAsPlainText();
    }

    public function goBack($behavior, $args): void
    {
        $this->webBrowsingService->goBack();
        $this->_response = "Navigating back.";
    }

    public function goForward($behavior, $args): void
    {
        $this->webBrowsingService->goForward();
        $this->_response = "Navigating forward.";
    }

    public function less($behavior, $args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page -= 1;

        $this->viewPageContent($behavior, $args);
    }

    public function more($behavior, $args)
    {
        $perPage = count($args) >= 1 ? (int)$args[0] : $this->_perPage;

        if ($perPage !== null) {
            $this->_perPage = $perPage;
        }
        $this->_page += 1;

        $this->viewPageContent($behavior, $args);
    }

    public function __destruct()
    {
        store('_system.website.page', $this->_page);
        store('_system.website.per_page', $this->_perPage);
        store('_system.website.link_page', $this->_linkPage);
        store('_system.website.per_link_page', $this->_perLinkPage);
        store('_system.website.content', $this->_content);
        store('_system.website.bookmarks', $this->_bookmarks);
    }
}
