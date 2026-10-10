<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAnnotationInstrumentsRequest;
use App\Models\Organization;
use App\Models\User;
use App\Support\Instruments;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** An organization's members and the instruments each may annotate for everyone. */
class OrganizationMemberController extends Controller
{
    public function index(Organization $organization): View
    {
        Gate::authorize('manageMembers', $organization);

        return view('organizations.members', [
            'organization' => $organization,
            'members' => $organization->users()->orderBy('name')->get(),
            'instruments' => Instruments::grouped(),
        ]);
    }

    public function update(UpdateAnnotationInstrumentsRequest $request, Organization $organization, User $user): RedirectResponse
    {
        abort_unless($user->organization_id === $organization->id, 404);
        $user->update(['annotation_instruments' => $request->instruments() ?: null]);

        return back()->with('status', "Saved {$user->name}.");
    }
}
