<?php

use System\Engine\Model;

class ShopCategoryModel extends Model
{
    protected $table = '#__shop_categories';
    protected $primaryKey = 'id';

    public function getAllCategories(): array
    {
        return $this->db->query("SELECT * FROM #__shop_categories ORDER BY sort_order, name")->rows;
    }
}