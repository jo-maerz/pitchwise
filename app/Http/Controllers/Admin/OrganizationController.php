<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveOrganizationRequest;
use App\Models\Organization;
use App\Services\OrganizationService;
use Illuminate\Http\RedirectResponse;

class OrganizationController extends Controller
{
    public function store(SaveOrganizationRequest $request): RedirectResponse
    {
        Organization::create($request->validated());

        return redirect()->route('admin.index')->with('status', 'Organization created.');
    }

    public function update(SaveOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        return redirect()->route('admin.index')->with('status', 'Organization renamed.');
    }

    public function destroy(Organization $organization, OrganizationService $service): RedirectResponse
    {
        $service->delete($organization);

        return redirect()->route('admin.index')->with('status', 'Organization deleted.');
    }
}
