<?php

namespace App\Http\Controllers\User;

use App\Enums\Status;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Usertag;
use App\Utils\PaginateCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Kullanıcıları listeleme sayfası
     * Durumu silinmiş olmayanlar dışında kalanlar
     * ve türü User olanlar
     */
    public function index(): View
    {
        $roles = Role::where('type', UserType::USER)->get();
        $users = User::whereNotIn('status', [UserStatus::DELETED])->where('type', [UserType::USER])->get();
        $users = PaginateCollection::paginate($users, 25);
        $tags = Usertag::where('status', [Status::ACTIVE])->has('users')->get();

        return view('users.index', [
            'users' => $users,
            'roles' => $roles,
            'tags' => $tags
        ]);
    }

    /**
     * Kullanıcı Detay Sayfası
     */
    public function show(User $user): View
    {
        $basePermissions = array();
        $permissions = array();
        $tags = Usertag::where('status', Status::ACTIVE)->get();
        $selectedTag = $user->usertags->pluck('id')->toArray();
        $userCustomPermissions = $user->getAllPermissions()->pluck('id')->toArray();

        foreach ($user->roles as $key => $role) {
            $permissions = Permission::withWhereHas('group', fn ($query) => $query->where('type', $role->type))->get();
            foreach ($permissions as $permission) {
                $basePermissions[$permission->group->name][$permission->id] = $permission->text;
            }

            $rolePermissions = $role->permissions->pluck('id')->toArray();
        }

        $rolePermissions = !empty($userCustomPermissions) ? array_merge($rolePermissions, $userCustomPermissions) : $rolePermissions;

        return view('users.detail', [
            'user' => $user,
            'tags' => $tags,
            'selectedTag' => $selectedTag,
            'basePermissions' => $basePermissions,
            'rolePermissions' => $rolePermissions
        ]);
    }

    public function permissions(User $user): View
    {
        $basePermissions = array();
        $permissions = array();
        $rolePermissions = array();

        $permissions = Permission::withWhereHas('group', fn ($query) => $query->where('type', UserType::USER))->get();

        foreach ($permissions as $permission) {
            $basePermissions[$permission->group->name][$permission->id] = [
                'title' => $permission->text,
                'name' => $permission->name
            ];
        }

        $userCustomPermissions = $user->getAllPermissions()->pluck('id')->toArray();

        foreach ($user->roles as $role) {
            $rolePermissions = $role->permissions->pluck('id')->toArray();
        }

        $rolePermissions = !empty($userCustomPermissions) ? array_merge($rolePermissions, $userCustomPermissions) : $rolePermissions;

        return view('users.permissions', [
            'user' => $user,
            'basePermissions' => $basePermissions,
            'rolePermissions' => $rolePermissions
        ]);
    }

    /**
     * Kullanıcıları filtreleme
     *
     * Kullanıcılar sayfasındaki ajax filtreleme
     * sonucunun döndüğü kısım
     */
    public function filter(Request $request)
    {
        $users = User::query()->where('type', [UserType::USER]);

        if ($request->has('searchText') && $request->searchText != null) {
            $users->where('name', 'LIKE', "%{$request->searchText}%");
        }

        if ($request->has('statusIds') && $request->statusIds != null) {
            $users->whereIn('status', $request->statusIds);
        }

        if ($request->has('tagIds') && $request->tagIds != null) {
            $users->whereHas('usertags', function ($query) use ($request) {
                $query->whereIn('usertag_id', $request->tagIds);
            });
        }

        if (!$request->has('statusIds') && !$request->has('tagIds') && !$request->has('searchText') && $request->has('page')) {
            $users->paginate('25', ['*'], 'page', $request->page);
            $users = $users->getCollection();
        } else {
            $users = $users->get();
        }

        $users->each(function ($user) {
            $user->roleName = $user->roles->pluck('name')->first();
        });

        return \response()->json($users);
    }

    /**
     * Kullanıcı durumunu güncelleme
     */
    public function status(Request $request)
    {
        if ($request->ajax() && $request->has('ids')) {
            $user = User::findOrFail($request->user_id);
            foreach (UserStatus::cases() as $userStatus) {
                if ($userStatus->value == $request->ids) {
                    $status = $userStatus->value;
                }
            }

            $user->status = $status;
            $user->save();

            return response()->json(['status' => 'success']);
        }
    }

    /**
     * Kullanıcılar sayfasındaki arama formu
     */
    public function search(Request $request)
    {
        if($request->ajax())
        {
            $output = '';
            $roles = '';
            $query = $request->get('query');
            if($query != '')
            {
                $data = User::select('id','status', 'name', 'email')
                    ->where('type', UserType::USER)
                    ->where("name", "LIKE", "%{$query}%")
                    ->oRwhere("email", "LIKE", "%{$query}%")
                    ->get('query');
            }

            $total_row = $data->count();
            if($total_row > 0)
            {
                foreach($data as $row)
                {
                    foreach($row->getRoleNames() as $role) {
                        $roles .= '<li><span class="fw-semibold mr-2 mb-2">'.$role.'</span></li>';
                    }

                    $output .= '
                        <tr>
                            <td>
                                <span class="badge fw-normal '.UserStatus::color($row->status).'">'.UserStatus::title($row->status).'</span>
                            </td>
                            <td>'.$row->name.'</td>
                            <td>'.$row->email.'</td>
                            <td>
                                <ul class="list-unstyled list-inline m-0 p-0">'.$roles.'</ul>
                            </td>
                            <td class="text-center">
                            <div class="dropdown">
                                <a class="btn btn-text dropdown-toggle p-0" href="#"
                                    role="button" data-bs-toggle="dropdown" data-boundary="window"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="ri-menu-3-fill"></i>
                                </a>
                                <ul
                                    class="dropdown-menu dropdown-menu-end rounded-0 shadow-none bg-white">
                                    <li><a class="dropdown-item small"
                                            href="'.route('panel.user.detail', $row->id).'">Bilgiler</a>
                                    </li>
                                    <li class="dropdown-divider"></li>
                                    <li>
                                        <button id="addRole" type="button"
                                            class="btn btn-text btn-sm dropdown-item"
                                            value="'.$row->id.'" data-bs-toggle="modal"
                                            data-bs-target="#changeRole">Rol Tanımla</button>
                                    </li>
                                    <li><a class="dropdown-item small"
                                            href="'.route('panel.user.permissions', $row->id).'">Özel
                                            Yetkiler</a>
                                    </li>
                                </ul>
                            </div>
                        </td>
                    </tr>';
                }
            } else {
                $output = '<tr><td align="center" colspan="5">Bu isim veya e-mail adresi ile kayıtlı kullanıcı bulunmamaktadır</td></tr>';
            }
            $data = array(
                'table_data'  => $output,
                'total_data'  => $total_row
            );

            echo json_encode($data);
        }
    }
}
