<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CmsMedia extends Model {
 protected $table='cms_media';
 protected $fillable=['disk','path','original_name','mime_type','size','alt_text','uploaded_by'];
}
