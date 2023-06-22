<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Permissiongroup;
use App\Models\Role;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class AdminController extends Controller
{

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('throttle:6,1')->only('verifyEmail');
    }

    public function index(): View
    {
        $users = User::where('type', UserType::ADMIN)->get()->except(User::role('Super Admin')->first()->id);
        return view('admin.index', compact('users'));
    }

    public function show(User $user): View
    {
        $basePermissions = array();
        $permissions = array();

        foreach ($user->roles as $key => $role) {
            $permissions = Permission::withWhereHas('group', fn ($query) => $query->where('type', $role->type))->get();
            foreach ($permissions as $permission) {
                $basePermissions[$permission->group->name][$permission->id] = $permission->text;
            }

            $rolePermissions = $role->permissions->pluck('id')->toArray();
        }

        return view('admin.detail', compact('user', 'basePermissions', 'rolePermissions'));
    }

    public function createAdmin(): View
    {
        $roles = Role::where([['type', UserType::ADMIN], ['name', '!=', 'Super Admin']])->get();
        return view('admin.create', compact('roles'));
    }

    public function permissionAdmin(User $user): View
    {
        $userRoles = array();
        $basePermissions = array();
        $permissions = array();
        $rolePermissions = array();

        $permissions = Permission::withWhereHas('group', fn ($query) => $query->where('type', UserType::ADMIN))->get();

        foreach ($permissions as $permission) {
            $basePermissions[$permission->group->name][$permission->id] = $permission->text;
        }

        foreach ($user->roles as $role) {
            $rolePermissions = $role->permissions->pluck('id')->toArray();
        }

        return view('admin.permissions', compact('user', 'basePermissions', 'rolePermissions'));
    }

    public function edit(User $user): View
    {
        return view('admin.edit', compact('user'));
    }
}