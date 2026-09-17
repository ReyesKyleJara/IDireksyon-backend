<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AdminAccountController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(array_keys(User::CMS_ROLES))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
        $accounts = User::whereIn('role', array_keys(User::CMS_ROLES))
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->where(fn ($q) => $q
                ->where('name', 'like', '%'.$search.'%')->orWhere('username', 'like', '%'.$search.'%')))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.accounts.index', ['accounts' => $accounts, 'roles' => User::CMS_ROLES]);
    }

    public function create()
    {
        return view('admin.accounts.form', ['account' => (new User)->forceFill(['role' => 'researcher', 'is_active' => true]), 'roles' => User::CMS_ROLES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $account = new User;
        $this->assign($account, $data);
        $account->save();

        return redirect()->route('admin.accounts.index')->with('success', 'Admin account created.');
    }

    public function edit(User $account)
    {
        abort_unless(array_key_exists($account->role, User::CMS_ROLES), 404);

        return view('admin.accounts.form', ['account' => $account, 'roles' => User::CMS_ROLES]);
    }

    public function update(Request $request, User $account)
    {
        abort_unless(array_key_exists($account->role, User::CMS_ROLES), 404);
        $data = $this->validated($request, $account);

        DB::transaction(function () use ($request, $account, $data) {
            // Serialize access changes so simultaneous edits cannot remove all active super admins.
            $admins = User::whereIn('role', array_keys(User::CMS_ROLES))->orderBy('id')->lockForUpdate()->get();
            abort_unless($admins->find($request->user()->id)?->canManageAdmins(), 403);
            $current = $admins->find($account->id);
            abort_unless($current, 404);
            $losesSuperAccess = $data['role'] !== 'super_admin' || ! $data['is_active'];
            if ($current->canManageAdmins() && $losesSuperAccess && $admins->filter->canManageAdmins()->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active Super Admin account.']);
            }
            if ($current->id === $request->user()->id && ($data['role'] !== $current->role || ! $data['is_active'])) {
                throw ValidationException::withMessages(['role' => 'Ask another Super Admin to change your own access.']);
            }
            $this->assign($current, $data);
            if ($current->isDirty(['role', 'is_active', 'password'])) {
                $current->remember_token = Str::random(60);
            }
            $current->save();
        });

        return redirect()->route('admin.accounts.index')->with('success', 'Admin account updated.');
    }

    private function validated(Request $request, ?User $account = null): array
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => strtolower(trim($request->input('username')))]);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_.-]+$/', Rule::unique('users')->ignore($account?->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($account?->id)],
            'role' => ['required', Rule::in(array_keys(User::CMS_ROLES))],
            'is_active' => ['required', 'boolean'],
            'password' => [$account ? 'nullable' : 'required', 'string', 'confirmed', Password::min(12)],
        ]);
    }

    private function assign(User $account, array $data): void
    {
        $account->name = $data['name'];
        $account->username = $data['username'];
        if ($account->email !== $data['email']) {
            $account->email_verified_at = null;
        }
        $account->email = $data['email'];
        $account->role = $data['role'];
        $account->is_active = $data['is_active'];
        if (! empty($data['password'])) {
            $account->password = $data['password'];
        }
    }
}
