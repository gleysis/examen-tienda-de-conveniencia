<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Every user with the cashier role gets a closed register of their own
     * if they do not already have one assigned.
     */
    public function up(): void
    {
        $cashierRoleIds = DB::table('roles')->where('slug', 'cashier')->pluck('id');
        if ($cashierRoleIds->isEmpty()) {
            return;
        }

        $assignedIds = DB::table('cash_registers')->whereNotNull('user_id')->pluck('user_id');

        $cashiers = DB::table('users')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->whereIn('role_user.role_id', $cashierRoleIds)
            ->whereNotIn('users.id', $assignedIds)
            ->select('users.id', 'users.name')
            ->distinct()
            ->get();

        $next = ((int) DB::table('cash_registers')->max('id')) + 1;

        foreach ($cashiers as $cashier) {
            DB::table('cash_registers')->insert([
                'name' => 'Caja '.$next++,
                'user_id' => $cashier->id,
                'status' => 'closed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
