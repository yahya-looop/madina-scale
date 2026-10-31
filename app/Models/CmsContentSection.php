<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsContentSection extends Model {
 protected $fillable=['name','key','type','content','is_active','created_by'];
 protected function casts(): array { return ['content'=>'array','is_active'=>'boolean']; }
}
