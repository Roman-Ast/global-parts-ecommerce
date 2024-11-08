<?php

namespace App;

use Ramsey\Uuid\Type\Integer;
use Illuminate\Http\Request;

class Cart
{
    public $items = [];

    public function add(
        string $article, string $brand, string $name, string $originNumber,
        string $deliveryTime, string $price, int $qty, string $stockFrom
    )
    {
        return $this->items[] = [
            'article' => $article, 'name' => $name, 'price' => $price, 'originNumber' => $originNumber,
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

    public function update(string $article, string $qty)
    {    
        foreach ($this->items as $key => $item) {
            if ($article == $item['article']) {
                $this->items[$key]['qty'] = $qty;
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
        foreach ($this->items as $key => $item) {
            if ($article == $item['article']) {
                return 'bingo';
            }
        }
        
        return null;
    }

    public function total()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += ((int)$item['price'] * (int)$item['qty']);
        }
        return $total;
    }

    public function remove(string $article)
    {
        foreach ($this->items as $key => $cartItem) {
            if($cartItem['article'] == $article) {
                unset($this->items[$key]);
            }
        }
        return $this->items;
    }

    public function clear()
    {
        return $this->items = [];
    }
}