<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'member_name',
        'member_date_joined',
        'member_dept',
        'member_sacco_id',
        'member_national_id',
        'member_postal_address',
        'member_phone_no',
        'member_gender',
        'member_email',
        'member_share_contr_monthly',
        'member_fosa_contr_monthly',
        'member_total_share',
        'member_total_fosa',
        'member_total_loan',
        'member_total_share_capital',
        'member_tied_shares',
        'member_tied_shares_self',
        'member_active',
        'member_date_dactivated',
        'member_deleted',
        'member_deleted_by',
        'member_deleted_ip',
        'member_deleted_on',
        'member_position',
        'member_image',
        'member_password',
        'member_password_last_changed',
        'member_password_changed_by',
        'member_ip',
        'member_transdate',
        'member_user_id',
        'member_last_mobile_login',
        'member_kra_pin',
        'member_dob',
        'bank_name',
        'bank_branch',
        'bank_account_number',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'member_password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'member_password_last_changed' => 'datetime',
        'member_password_changed_by' => 'int',
        'member_date_joined' => 'datetime',
        'member_date_dactivated' => 'datetime',
        'member_deleted_on' => 'datetime',
        'member_transdate' => 'datetime',
    ];

    /**
     * Get the member's ID.
     *
     * @return int
     */
    public function getIdAttribute()
    {
        return $this->attributes['member_id'];
    }

    /**
     * Override the method to get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return 'member_id';
    }

    /**
     * Override the method to get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->member_id;
    }
}
