<?php
/**
 * User: shenzhe
 * Date: 13-6-17
 */
namespace ZPHP\Queue\Adapter;

class Beanstalk
{
    private $beanstalk;

    public function __construct($config)
    {
        if (empty($this->beanstalk)) {
            $this->beanstalk = new \Beanstalk();
            foreach ($config['servers'] as $server) {
                $this->beanstalk->addServer($server['host'], $server['port']);
            }
        }
    }

    public function add($key, $data)
    {
        return $this->beanstalk->put($key, $data);
    }

    public function get($key)
    {
        $job = $this->beanstalk->reserve($key);
        $this->beanstalk->delete($job['id'], $key);
        return $job;
    }
}