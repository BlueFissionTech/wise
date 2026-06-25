<?php

namespace BlueFission\Wise\Sys\Drivers;

class CompositeDisplayDriver implements IDisplayDriver
{
    /** @var array<int, IDisplayDriver> */
    private array $_drivers;

    public function __construct(array $drivers)
    {
        $this->_drivers = array_values(array_filter($drivers, function ($driver) {
            return $driver instanceof IDisplayDriver;
        }));
    }

    public function handle($data, $type = null, $style = null): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->handle($data, $type, $style);
        }
    }

    public function send($data): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->send($data);
        }
    }

    public function getContent(): array
    {
        $content = [];
        foreach ($this->_drivers as $driver) {
            $content = array_merge($content, $driver->getContent());
        }

        return $content;
    }

    public function flush(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->flush();
        }
    }

    public function getTerminalSize(): array
    {
        $driver = $this->_drivers[0] ?? null;
        return $driver ? $driver->getTerminalSize() : [80, 24];
    }

    public function init(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->init();
        }
    }

    public function update(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->update();
        }
    }

    public function draw(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->draw();
        }
    }

    public function print(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->print();
        }
    }

    public function clear(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->clear();
        }
    }

    public function clearScreen(): void
    {
        foreach ($this->_drivers as $driver) {
            $driver->clearScreen();
        }
    }
}
