<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSuperAdminRequest;
use App\Http\Requests\Admin\UpdateSuperAdminRequest;
use App\Models\User;
use App\Services\Admin\SuperAdminUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSuperAdminController extends Controller
{
    public function __construct(
        private readonly SuperAdminUserService $superAdmins,
    ) {}

    public function index(): View
    {
        return view('admin.super-admins.index', [
            'superAdmins' => $this->superAdmins->list(),
        ]);
    }

    public function create(): View
    {
        return view('admin.super-admins.create');
    }

    public function store(StoreSuperAdminRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->superAdmins->create(
            $request->user(),
            $data['name'],
            $data['email'],
            $data['password'],
        );

        return redirect()
            ->route('admin.super-admins.index')
            ->with('status', 'Super-admin korisnik je dodan.');
    }

    public function edit(User $superAdmin): View
    {
        $this->superAdmins->assertSuperAdmin($superAdmin);

        return view('admin.super-admins.edit', [
            'superAdmin' => $superAdmin,
        ]);
    }

    public function update(UpdateSuperAdminRequest $request, User $superAdmin): RedirectResponse
    {
        $this->superAdmins->assertSuperAdmin($superAdmin);

        $data = $request->validated();

        $this->superAdmins->update(
            $request->user(),
            $superAdmin,
            $data['name'],
            $data['email'],
            $data['password'] ?? null,
        );

        return redirect()
            ->route('admin.super-admins.index')
            ->with('status', 'Super-admin korisnik je ažuriran.');
    }

    public function destroy(Request $request, User $superAdmin): RedirectResponse
    {
        $this->superAdmins->assertSuperAdmin($superAdmin);

        try {
            $this->superAdmins->delete($request->user(), $superAdmin);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()
                ->route('admin.super-admins.index')
                ->with('warning', $exception->validator->errors()->first('super_admin'));
        }

        return redirect()
            ->route('admin.super-admins.index')
            ->with('status', 'Super-admin korisnik je obrisan.');
    }
}
