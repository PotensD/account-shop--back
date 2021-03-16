<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Role;
use App\Http\Requests\StoreAccountTypeRequest;
use App\Http\Requests\UpdateAccountTypeRequest;
use App\Http\Resources\AccountTypeResource;
use App\Models\Game;
use Str;
use Auth;
use DB;

class AccountTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return AccountTypeResource::collection(AccountType::all());
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreAccountTypeRequest $request)
    {
        // Get game
        $game = Game::find($request->gameId);
        if (is_null($game)) {
            return response()->json([
                'message' => 'ID game không tồn tại.',
            ], 404);
        }

        // Validate status codes
        foreach ($request->rolesCanUsedAccountType ?? [] as $role) {
            if (!in_array($role['statusCode'], config('account.status_codes'))) {
                return response()->json([
                    'message' => 'Status code of role[id: '
                        . $role['id'] . ', statusCode: '
                        . $role['statusCode'] . '] invalid.'
                ], 422);
            }
        }

        // Initialize data
        $accountTypeData = [];
        foreach ([
            'name', 'description'
        ] as $key) {
            if ($request->filled($key)) {
                $accountTypeData[$key] = $request->$key;
            }
        }
        $accountTypeData['slug'] = Str::slug($accountTypeData['name']);
        $accountTypeData['game_id'] = $game->id;
        $accountTypeData['last_updated_editor_id'] = Auth::user()->id;
        $accountTypeData['creator_id'] = Auth::user()->id;

        // DB transaction
        try {
            DB::beginTransaction();
            $accountType = AccountType::create($accountTypeData); // Save rule to database

            $appRoles = Role::all();
            // Relationship many-many with Models\Role 1
            $syncRoles = [];
            foreach ($request->rolesCanUsedAccountType ?? [] as $role) {
                if ($appRoles->contains($role['id'])) {
                    $syncRoles[$role['id']] = [
                        'status_code' => $role['statusCode'],
                    ];
                }
            }
            $accountType->rolesCanUsedAccountType()->sync($syncRoles);

            // When success
            DB::commit();
        } catch (\Throwable $th) {
            return $th;
            DB::rollback();
            return response()->json([
                'message' => 'Thêm mới kiểu tài khoản thất bại, vui lòng thừ lại sau.',
            ], 500);
        }

        return new AccountTypeResource($accountType->refresh());
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\AccountType  $accountType
     * @return \Illuminate\Http\Response
     */
    public function show(AccountType $accountType)
    {
        return new AccountTypeResource($accountType);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AccountType  $accountType
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAccountTypeRequest $request, AccountType $accountType)
    {
        // Initialize data
        $accountTypeData = [];
        foreach ([
            'name', 'description'
        ] as $key) {
            if ($request->filled($key)) {
                $accountTypeData[$key] = $request->$key;
            }
        }
        if (array_key_exists('name', $accountTypeData)) {
            $accountTypeData['slug'] = Str::slug($accountTypeData['name']);
        }
        $accountTypeData['last_updated_editor_id'] = Auth::user()->id;

        // DB transaction
        try {
            DB::beginTransaction();
            $accountType->update($accountTypeData); // Save rule to database

            $roles = Role::all();
            // Relationship many-many with Models\Role 1
            $syncRoles = [];
            foreach ($request->rolesCanUsedAccountType ?? [] as $role) {
                if ($roles->contains($role['id'])) {
                    $syncRoles[$role['id']] = ['status_code' => $role['statusCode']];
                }
            }
            $accountType->rolesCanUsedAccountType()->sync($syncRoles);

            // When success
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => 'Cập nhật kiểu tài khoản thất bại, vui lòng thừ lại sau.',
            ], 500);
        }

        return new AccountTypeResource($accountType);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\AccountType  $accountType
     * @return \Illuminate\Http\Response
     */
    public function destroy(AccountType $accountType)
    {
        // DB transaction
        try {
            DB::beginTransaction();
            $accountType->rolesCanUsedAccountType()->sync([]); // Delete relationship with Models\Role
            $accountType->delete();
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json([
                'message' => 'Xoá kiểu tài khoản thất bại, vui lòng thừ lại sau.',
            ], 500);
        }

        return response()->json([
            'message' => 'Xoá kiểu tài khoản thành công.',
        ], 200);
    }

    // -----------------------------------------------------------
    // -----------------------------------------------------------
    // -----------------------------------------------------------
}
