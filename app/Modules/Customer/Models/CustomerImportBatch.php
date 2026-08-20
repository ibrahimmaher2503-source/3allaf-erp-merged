<?php
namespace App\Modules\Customer\Models;
use App\Models\User; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class CustomerImportBatch extends Model { protected $fillable=['created_by','original_filename','mode','status','headers','total_rows','valid_rows','invalid_rows','approved_at']; protected $casts=['headers'=>'array','approved_at'=>'datetime']; public function rows():HasMany{return $this->hasMany(CustomerImportRow::class);} public function creator(){return $this->belongsTo(User::class,'created_by');} }
