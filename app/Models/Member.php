<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class Member extends Authenticatable
{
    protected $table = 'sacco_members';
    protected $primaryKey = 'member_id';

    protected $fillable = [
        'member_name', 'member_date_joined', 'member_dept', 'member_sacco_id', 
        'member_national_id', 'member_postal_address', 'member_phone_no', 
        'member_gender', 'member_email', 'member_share_contr_monthly', 
        'member_fosa_contr_monthly', 'member_total_share', 'member_total_fosa', 
        'member_total_loan', 'member_total_share_capital', 'member_tied_shares', 
        'member_tied_shares_self', 'member_active', 'member_date_dactivated', 
        'member_deleted', 'member_deleted_by', 'member_deleted_ip', 
        'member_deleted_on', 'member_position', 'member_image', 'member_password', 
        'member_password_last_changed', 'member_password_changed_by', 
        'member_ip', 'member_transdate', 'member_user_id', 'member_last_mobile_login', 
        'member_kra_pin', 'member_dob', 'bank_name', 'bank_branch', 
        'bank_account_number'
    ];

    protected $hidden = [
        'member_password',
    ];

    public function getAuthPassword()
    {
        return $this->member_password;
    }
}
