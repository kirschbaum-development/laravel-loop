<?php

namespace Kirschbaum\Loop\Services;

use Illuminate\Support\Manager;
use Kirschbaum\Loop\Contracts\SseDriverInterface;
use Kirschbaum\Loop\SseDrivers\FileDriver;
use Kirschbaum\Loop\SseDrivers\RedisDriver;

class SseDriverManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('loop.sse.driver', 'file');

        return is_string($driver) ? $driver : 'file';
    }

    /**
     * Create an instance of the file driver.
     */
    protected function createFileDriver(): SseDriverInterface
    {
        $config = $this->config->get('loop.sse.drivers.file', []);
        $config = is_array($config) ? $config : [];

        return new FileDriver($config);
    }

    /**
     * Create an instance of the redis driver.
     */
    protected function createRedisDriver(): SseDriverInterface
    {
        $config = $this->config->get('loop.sse.drivers.redis', []);
        $config = is_array($config) ? $config : [];

        return new RedisDriver($config);
    }

    /**
     * Get the driver instance.
     *
     * @param  string|null  $driver
     */
    public function driver($driver = null): SseDriverInterface
    {
        /** @var SseDriverInterface */
        return parent::driver($driver);
    }
}
