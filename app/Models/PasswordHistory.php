<?php

namespace App\Models;

use App\Models\Traits\HasUuidColumn;
use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordHistory extends Model
{
    use HasUuidColumn, BelongsToCompany, HasFactory;

    public $timestamps = false;

    protected $fillable = ['company_id', 'user_id', 'password', 'created_at'];

    protected $hidden = ['password'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
