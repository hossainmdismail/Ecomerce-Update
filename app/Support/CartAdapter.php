<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Wearepixel\Cart\Cart as BaseCart;
use Wearepixel\Cart\CartCondition;
use Wearepixel\Cart\ItemCollection;

class CartAdapter
{
    protected BaseCart $cart;
    protected string $instanceName;

    public function __construct(string $instanceName = 'default')
    {
        $this->instanceName = $instanceName;
        $this->cart = $this->makeCart($instanceName);
    }

    protected function makeCart(string $instanceName): BaseCart
    {
        $config = config('cart');
        $sessionKey = session()->getId() . '_' . $instanceName;
        $session = app('session');
        $events = app('events');

        return new BaseCart($session, $events, $instanceName, $sessionKey, $config);
    }

    protected function normalizeItem(array $item): array
    {
        if (isset($item['options'])) {
            $item['attributes'] = $item['options'];
            unset($item['options']);
        }

        if (isset($item['qty']) && ! isset($item['quantity'])) {
            $item['quantity'] = $item['qty'];
            unset($item['qty']);
        }

        return $item;
    }

    protected function normalizeUpdateData(array $data): array
    {
        if (isset($data['options'])) {
            $data['attributes'] = $data['options'];
            unset($data['options']);
        }

        if (isset($data['qty']) && ! isset($data['quantity'])) {
            $data['quantity'] = $data['qty'];
            unset($data['qty']);
        }

        return $data;
    }

    protected function transformItem(mixed $item)
    {
        if (! $item instanceof ItemCollection) {
            return $item;
        }

        if (! isset($item['rowId'])) {
            $item['rowId'] = $item['id'];
        }

        if ($item->has('attributes') && ! $item->has('options')) {
            $item['options'] = $item['attributes'];
        }

        // Add qty alias for backward compatibility
        if ($item->has('quantity') && ! $item->has('qty')) {
            $item['qty'] = $item['quantity'];
        }

        return $item;
    }

    protected function transformContent(Collection $content): Collection
    {
        return $content->map(function ($item, $key) {
            if ($item instanceof ItemCollection) {
                if (! isset($item['rowId'])) {
                    $item['rowId'] = $key;
                }

                if ($item->has('attributes') && ! $item->has('options')) {
                    $item['options'] = $item['attributes'];
                }

                // Add qty alias for backward compatibility
                if ($item->has('quantity') && ! $item->has('qty')) {
                    $item['qty'] = $item['quantity'];
                }
            }

            return $item;
        });
    }

    public function add(string|int|array $id, ?string $name = null, ?float $price = null, mixed $quantity = null, array $attributes = [], array|CartCondition $conditions = [], ?string $associatedModel = null)
    {
        if (is_array($id)) {
            $id = $this->normalizeItem($id);
            $this->cart->add($id);

            return $this->get($id['id']);
        }

        if (isset($attributes['options'])) {
            $attributes = $attributes['options'];
        }

        if (isset($attributes['qty']) && ! isset($attributes['quantity'])) {
            $attributes['quantity'] = $attributes['qty'];
            unset($attributes['qty']);
        }

        $this->cart->add($id, $name, $price, $quantity, $attributes, $conditions, $associatedModel);

        return $this->get($id);
    }

    public function update(string|int $id, mixed $data)
    {
        if (is_numeric($data)) {
            if ((float) $data === 0.0) {
                return $this->remove($id);
            }

            return $this->cart->update($id, ['quantity' => $data]);
        }

        if (is_array($data)) {
            $data = $this->normalizeUpdateData($data);
        }

        return $this->cart->update($id, $data);
    }

    public function remove(string|int $id): bool
    {
        return $this->cart->remove($id);
    }

    public function destroy(): bool
    {
        return $this->cart->clear();
    }

    public function clear(): bool
    {
        return $this->cart->clear();
    }

    public function count(): int
    {
        return $this->cart->getTotalQuantity();
    }

    public function subtotal(): int|float|string
    {
        return $this->cart->getSubTotal();
    }

    public function content(): Collection
    {
        return $this->transformContent($this->cart->getContent());
    }

    public function get(string|int $id)
    {
        return $this->transformItem($this->cart->get($id));
    }

    public function __call(string $method, array $arguments)
    {
        $result = $this->cart->$method(...$arguments);

        if ($method === 'getContent') {
            return $this->transformContent($result);
        }

        if ($method === 'get') {
            return $this->transformItem($result);
        }

        return $result;
    }
}
