<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('q'));

        return view('admin.index', [
            'organizations' => Organization::withCount(['users', 'folders', 'pieces'])->orderBy('name')->get(),
            'users' => User::with('organization')
                ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
                ->orderBy('name')
                ->paginate(50)
                ->withQueryString(),
            'roles' => Role::cases(),
            'search' => $search,
        ]);
    }
}
