<?php

namespace BlueFission\Wise\Res;

use BlueFission\Data\Storage\Disk;
use BlueFission\Services\Service;
use DateTime;

class Schedule
{
    public $title;
    public $date;
    public $time;
    public $description;

    public function __construct($title, $date, $time, $description = "")
    {
        $this->title = $title;
        $this->date = date('Y-m-d', strtotime($date ?? date('Y-m-d')));
        $this->time = date('H:i:s', strtotime($time ?? '00:00:00'));
        $this->description = $description;
    }
}

class ScheduleResource extends Service
{
    protected $_storage;

    public function __construct()
    {
        parent::__construct();
        $storagePath = OPUS_ROOT . '/storage/system';
        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        $this->_storage = new Disk([
            'location' => $storagePath,
            'name' => 'schedule_data.json',
        ]);
        $this->_storage->activate();
        $this->cleanup();
    }

    public function saveData($data)
    {
        $this->_storage->contents(json_encode($data));
        $this->_storage->assign($data);
        $this->_storage->write();
    }

    public function loadData()
    {
        return $this->_storage->read() ?? [];
    }

    public function process($behavior, $args)
    {
        $originalArgs = $args;

        if (!empty($args) && count($args) === 1) {
            $args = explode(' ', $args[0]);
        } elseif ( !empty($args) && $args[0] === 'help' ) {
            $this->_response = $this->help();
            return;
        }

        $response = "No command executed.";
        switch ($behavior->name()) {
            case 'add':
                $response = $this->addEvent($args);
                break;
            case 'list':
                $response = $this->listEvents($args);
                break;
            case 'next':
                $response = $this->nextEvent();
                break;
            case 'previous':
                $response = $this->lastEvent();
                break;
            case 'delete':
                $response = $this->deleteEvent($originalArgs[0]);
                break;
            case 'edit':
                $response = $this->editEvent($args);
            default:
                $response = "Invalid behavior.";
            case 'help':
                $response = $this->help();
        }

        $this->_response = $response;
    }

    protected function addEvent($args)
    {
        $data = $this->loadData();
        $event = new Schedule($args[0], $args[1] ?? null, $args[2] ?? null);
        $data[$args[0].' - '.($args[1] ?? null)] = (array) $event;
        $this->saveData($data);

        return "Event added successfully.";
    }

    protected function listEvents($args)
    {
        $page = count($args) >= 1 ? (int) $args[0] : $this->_page;
        if (count($args) >= 2) {
            $this->_perPage = (int) $args[0] ?? 1;
            $page = (int) $args[1];
        }

        if ( $this->_perPage < 1 ) {
            $this->_perPage = 25;
        }

        $this->_page = $page;
        $events = $this->loadData();

        if (!count($events)) {
            return "No events have been created.";
        }

        $total = count($events);
        $totalPages = ceil($total / $this->_perPage);

        if ($this->_perPage < 1) {
            $this->_perPage = 25;
        }

        if ($page < 1) {
            $page = 1;
        } elseif ($page > $totalPages) {
            $page = $totalPages;
        }
        $this->_page = $page;

        $pageStart = ($page - 1) * $this->_perPage;
        $pageEnd = $pageStart + $this->_perPage;

        $i = 0;
        $count = 0;
        $response = "";

        $response = "Available events:\n";
        foreach ($events as $action=>$details) {
            if ($i >= $pageStart && $i < $pageEnd) {
                $response .= "  - {$action}: " . $details['description'] . PHP_EOL;
                $count++;
            }
            $i++;
        }

        $response .= "Showing {$count} of {$total} events. Page {$page} of {$totalPages}." . PHP_EOL;
        $response .= "Use commands `previous schedules` or `next schedules` to move through pages." . PHP_EOL;
        $response .= "Command `show schedule <event>`." . PHP_EOL;

        return $response;
    }

    protected function nextEvent()
    {
        $data = $this->loadData();
        $nextEvent = null;
        $now = new DateTime();

        if ( !empty($data) && is_array($data) ) {
            foreach ($data as $event) {
                $eventDateTime = new DateTime($event['date'] . ' ' . $event['time']);
                if ($eventDateTime > $now && (!$nextEvent || $eventDateTime < $nextEvent)) {
                    $nextEvent = $eventDateTime;
                }
            }
        }
        return $nextEvent ? $nextEvent->format('Y-m-d H:i:s') : "No upcoming events found.";
    }

    protected function lastEvent()
    {
        $data = $this->loadData();
        $lastEvent = null;
        $now = new DateTime();

        if ( !empty($data) && is_array($data) ) {
            foreach ($data as $event) {
                $eventDateTime = new DateTime($event['date'] . ' ' . $event['time']);
                if ($eventDateTime < $now && (!$lastEvent || $eventDateTime > $lastEvent)) {
                    $lastEvent = $eventDateTime;
                }
            }
        }
        return $lastEvent ? $lastEvent->format('Y-m-d H:i:s') : "No past events found.";
    }

        protected function deleteEvent($title)
    {
        $data = $this->loadData();
        $data = array_filter($data, function ($event) use ($title) {
            return $event['title'] !== $title;
        });
        $this->saveData($data);

        return "Event deleted successfully.";
    }

    protected function editEvent($args)
    {
        $title = $args[0];
        $newDate = $args[1];
        $newTime = $args[2];

        $data = $this->loadData();
        $found = false;

        foreach ($data as &$event) {
            if ($event['title'] === $title) {
                $event['date'] = $newDate;
                $event['time'] = $newTime;
                $found = true;
                break;
            }
        }

        if ($found) {
            $this->saveData($data);
            return "Event updated successfully.";
        } else {
            return "Event not found.";
        }
    }

    protected function cleanup()
    {
        $data = $this->loadData();
        $now = new DateTime();
        $monthAgo = $now->modify('-1 month');

        if (is_array($data)) {
            $data = array_filter($data, function ($event) use ($monthAgo) {
                $time = strtotime($event['date'] . ' ' . $event['time']);

                $eventDateTime = new DateTime($time);
                return $eventDateTime > $monthAgo;
            });

            $this->saveData($data);
        }
    }

    public function help()
    {
        $help = "Schedule Manager Help:\n\n";
        $help .= "Available commands:\n";
        $help .= "1. Add a new event:\n";
        $help .= "   'add schedule \"<title>\" <date> <time>'\n";
        $help .= "   Example: add schedule \"Meeting\" 2023-05-01 14:00\n";
        $help .= "   Note: <date> should be in the format 'YYYY-MM-DD', and <time> should be in the format 'HH:mm'\n\n";
        $help .= "2. List all events:\n";
        $help .= "   'list schedules'\n\n";
        $help .= "3. Show next event:\n";
        $help .= "   'next schedule'\n\n";
        $help .= "4. Show previous event:\n";
        $help .= "   'previous schedule'\n\n";
        $help .= "5. Delete an event by title:\n";
        $help .= "   'delete schedule \"<title>\"'\n";
        $help .= "   Example: delete schedule \"Meeting\"\n\n";
        $help .= "6. Edit an event:\n";
        $help .= "   'edit schedule \"<title>\" <new_date> <new_time>'\n";
        $help .= "   Example: edit schedule \"Meeting\" 2023-05-02 15:00\n";
        $help .= "   Note: <new_date> should be in the format 'YYYY-MM-DD', and <new_time> should be in the format 'HH:mm'\n\n";
        $help .= "Please note the importance of the order of the arguments when using the class methods.";

        return $help;
    }

}
