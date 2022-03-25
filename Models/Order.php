<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $casts = [
        'created_at' => 'datetime:Y-m-d',
        'updated_at' => 'datetime:Y-m-d',
    ];

    public function getOwnerAttribute($value)
    {
        $user = User::find($value);
        if($user)
        {
            return $user->firstname." ".$user->lastname;
        }
        else
        {
            return "Brak";
        }
    }

    public function getOrderCategoryAttribute($value)
    {
        $orderCategory = OrdersCategory::find($value);
        if($orderCategory)
        {
            return $orderCategory->name;
        }
        else
        {
            return "Brak";
        }
    }

    public function getStatusAttribute($value)
    {
        $status = Status::find($value);
        if($status)
        {
            return $status->name;
        }
        else
        {
            return "Brak";
        }
    }
}
