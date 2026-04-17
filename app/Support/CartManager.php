<?php

namespace App\Support;

class CartManager
{
    protected array $instances = [];

    public function instance(string $instanceName = 'default'): CartAdapter
    {
        return $this->instances[$instanceName] ??= new CartAdapter($instanceName);
    }

    public function __call(string $method, array $arguments)
    {
        return $this->instance('default')->$method(...$arguments);
    }
}
