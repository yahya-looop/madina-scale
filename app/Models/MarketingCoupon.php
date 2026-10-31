<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MarketingCoupon extends Model {
 protected $fillable=['code','name','discount_type','discount_value','minimum_order','maximum_discount','usage_limit','per_customer_limit','used_count','is_active','starts_at','ends_at','conditions','created_by'];
 protected function casts(): array { return ['discount_value'=>'decimal:2','minimum_order'=>'decimal:2','maximum_discount'=>'decimal:2','is_active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime','conditions'=>'array']; }
}
