<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Models\Traits\BelongsToOrganization;
use App\Models\Traits\HasUuidColumn;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmEmployeeMealSelection extends Model
{
    use HasFactory, HasUuidColumn, BelongsToCompany, BelongsToOrganization;

    protected $table = 'hrm_employee_meal_selections';

    protected $fillable = [
        'uuid',
        'company_id',
        'organization_id',
        'hrm_employee_id',
        'hrm_weekly_meal_id',
        'date',
        'selection',
        'selected_item_name',
        'is_manually_updated',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date'                => 'date',
        'is_manually_updated' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'hrm_employee_id');
    }

    public function weeklyMeal(): BelongsTo
    {
        return $this->belongsTo(HrmWeeklyMeal::class, 'hrm_weekly_meal_id');
    }
}
