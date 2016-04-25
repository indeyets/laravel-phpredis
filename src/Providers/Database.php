<?php

namespace Barbery\Providers;

use Redis;
use RedisCluster;
use Illuminate\Support\Arr;

class Database extends \Illuminate\Redis\Database
{
    private $_optionsKey = ['prefix' => Redis::OPT_PREFIX, 'failover' => RedisCluster::OPT_SLAVE_FAILOVER];


    /**
     * Create a new Redis connection instance.
     *
     * @param  array  $servers
     * @return void
     */
    public function __construct(array $servers = [])
    {
        $cluster = Arr::pull($servers, 'cluster');
        if (isset($servers['cluster']) && $servers['cluster']) {
            $options = (array) Arr::pull($servers['clusterConfig'], 'options');
            $this->clients = $this->createAggregateClient($servers['clusterConfig'], $options);
        } else {
            $options = (array) Arr::pull($servers, 'options');
            $this->clients = $this->createSingleClients($servers, $options);
        }
    }

    protected function createAggregateClient(array $servers, array $options = [])
    {
        $cluster = array();
        foreach ($servers as $key => $server) {
            if (isset($this->_optionsKey[$key])) {
                continue;
            }

            $host = empty($server['host']) ? '127.0.0.1' : $server['host'];
            $port = empty($server['port']) ? '6379'      : $server['port'];
            $cluster[] = "{$host}:{$port}";
        }

        $readTimeout = Arr::get($options, 'read_timeout', 0);
        $timeout = Arr::get($options, 'timeout', 0);
        $persistent = Arr::get($options, 'persistent', false);
        $RedisCluster = new RedisCluster(null, $cluster, $readTimeout, $timeout, $persistent);
        foreach ($this->_optionsKey as $key => $option) {
            if (!empty($options[$key])) {
                $RedisCluster->setOption($option, $options[$key]);
            }
        }

        return array('default' => $RedisCluster);
    }


    protected function createSingleClients(array $servers, array $options = [])
    {
        $clients = array();
        $ingoreKey = ['clusterConfig' => true];

        foreach ($servers as $key => $server) {
            if (isset($ingoreKey[$key])) {
                continue;
            }

            $redis = new Redis();
            $host    = empty($server['host'])    ? '127.0.0.1' : $server['host'];
            $port    = empty($server['port'])    ? '6379'      : $server['port'];
            $timeout = empty($server['timeout']) ? 0           : $server['timeout'];
            if (isset($server['persistent']) && $server['persistent']) {
                $redis->pconnect($host, $port, $timeout);
            } else {
                $redis->connect($host, $port, $timeout);
            }

            if (!empty($server['prefix'])) {
                $redis->setOption(Redis::OPT_PREFIX, $server['prefix']);
            }

            if (!empty($server['database'])) {
                $redis->select($server['database']);
            }

            $clients[$key] = $redis;
        }

        return $clients;
    }


    /**
     * redis pipeline operation
     * 
     * @param  closure $callback
     * @return []$result
     *
     */
    public function pipeline($callback)
    {
        $Pipe = $this->connection()->multi(Redis::PIPELINE);
        $callback($Pipe);
        return $Pipe->exec();
    }
}