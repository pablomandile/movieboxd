<?php

namespace App\Policies;

use App\Models\ListModel;
use App\Models\User;

class ListModelPolicy
{
    public function before(User $user): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /** Una lista privada también la ven sus colaboradores. */
    public function view(?User $user, ListModel $list): bool
    {
        return $list->is_public || $list->isOwnedBy($user) || $list->isCollaborator($user);
    }

    /**
     * Cambiar la lista en sí (nombre, descripción, visibilidad, ranqueada) o borrarla:
     * solo el dueño. Un invitado colabora con el contenido, no con la lista.
     */
    public function update(User $user, ListModel $list): bool
    {
        return $list->isOwnedBy($user);
    }

    public function delete(User $user, ListModel $list): bool
    {
        return $list->isOwnedBy($user);
    }

    /** Agregar, quitar, anotar y reordenar títulos: dueño y colaboradores invitados. */
    public function updateItems(User $user, ListModel $list): bool
    {
        return $list->isOwnedBy($user) || $list->isCollaborator($user);
    }

    /** Invitar, revocar y regenerar el enlace: potestad exclusiva del dueño. */
    public function manageCollaborators(User $user, ListModel $list): bool
    {
        return $list->isOwnedBy($user);
    }
}
