<?php

namespace App\Http\Controllers;

use App\Models\ListModel;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ListCollaboratorController extends Controller
{
    /**
     * Genera el enlace de invitación (o devuelve el vigente). El token se crea
     * perezosamente: una lista que nunca se compartió no tiene ninguno.
     */
    public function invite(ListModel $list): RedirectResponse
    {
        Gate::authorize('manageCollaborators', $list);

        if (blank($list->invite_token)) {
            $list->invite_token = Str::random(40);
            $list->save();
        }

        return back()->with('inviteUrl', route('lists.invite.accept', $list->invite_token));
    }

    /**
     * Rota el token: los enlaces repartidos antes dejan de servir.
     * No expulsa a quien ya entró — para eso está revoke().
     */
    public function regenerate(ListModel $list): RedirectResponse
    {
        Gate::authorize('manageCollaborators', $list);

        $list->invite_token = Str::random(40);
        $list->save();

        return back()->with('inviteUrl', route('lists.invite.accept', $list->invite_token));
    }

    /**
     * Alguien abre el enlace. Sumarse exige sesión: el permiso se otorga a una
     * cuenta, no al navegador que tenga el link.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $list = ListModel::where('invite_token', $token)->firstOr(function () {
            abort(404);
        });

        $user = $request->user();
        // lists.show resuelve el modelo por id (getRouteKeyName por defecto),
        // no por slug: pasarle el slug daría 404.
        $target = route('lists.show', [$list->user->username, $list->id]);

        if ($list->isOwnedBy($user)) {
            return redirect($target);
        }

        if (! $list->isCollaborator($user)) {
            $list->collaborators()->attach($user->id);
        }

        return redirect($target)->with('status', __('app.list_collaborator_joined', ['list' => $list->name]));
    }

    /** El dueño le quita la edición a un miembro. */
    public function revoke(ListModel $list, User $user): RedirectResponse
    {
        Gate::authorize('manageCollaborators', $list);

        $list->collaborators()->detach($user->id);

        return back()->with('status', __('app.list_collaborator_revoked', ['name' => $user->name]));
    }
}
