<?php

namespace App;

use Ramsey\Uuid\Type\Integer;

class Cart
{
    public $items = [];

    public function add(
        string $brand, string $article, string $name,
        string $deliveryTime, string $price, int $qty, string $stockFrom
    )
    {
        return $this->items[] = [
            'article' => $article, 'name' => $name, 'price' => $price,
            'qty' => $qty, 'brand' => $brand, 'deliveryTime' => $deliveryTime, 'stockFrom' => $stockFrom
        ];
    }

    public function count()
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item['qty'];
        }
        return $count;
    }

    public function update(string $article, array $params)
    {    
        foreach ($this->items as $key => $item) {
            if ($article == $item['article']) {
                foreach ($params as $paramsKey => $paramsValue) {
                    if (array_key_exists($paramsKey, $item) && $paramsKey) {
                        $this->items[$key][$paramsKey] = $paramsValue;
                    }
                }
            }
        }

        return $this->items;
    }

    public function content()
    {
        return $this->items;
    }

    public function search(string $article)
    {
        if (array_key_exists($article, $this->items)) {
            return $this;
        }
        return null;
    }

    public function total()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += (int)$item['price'];
        }
        return $total;
    }

    /*public function update(string $id, string $newQty)
    {
        if (array_key_exists(self::PREFIX . $id, $this->items)) {
            $this->items[self::PREFIX . $id]['qty'] = $newQty;
        }
        
    }*/

    public function remove(string $article)
    {
        if (array_key_exists($article, $this->items)) {
            unset($this->items['article']);
        }
        return null;
    }

    public function clear()
    {
        return $this->items = [];
    }
}