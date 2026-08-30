<?php

use System\Engine\Model;

class ShopProductModel extends Model
{
    protected $table = '#__shop_products';
    protected $primaryKey = 'id';

    public function getAllProducts(): array
    {
        return $this->db->query("SELECT * FROM #__shop_products ORDER BY name")->rows;
    }
}