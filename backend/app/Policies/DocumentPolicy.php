<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Règles d'accès aux documents.
 *
 *  - voir / télécharger     : propriétaire, administrateur, ou partage (lecture ou modification)
 *  - modifier / ajouter une version : propriétaire, administrateur, ou partage "modification"
 *                                      ET document non archivé
 *  - supprimer, partager, archiver, restaurer : propriétaire ou administrateur
 */
class DocumentPolicy
{
    public function view(User $user, Document $document): Response
    {
        return $document->rightFor($user) !== null
            ? Response::allow()
            : Response::deny('Vous n\'avez pas accès à ce document.');
    }

    public function update(User $user, Document $document): Response
    {
        return $this->canEdit($user, $document);
    }

    public function addVersion(User $user, Document $document): Response
    {
        return $this->canEdit($user, $document);
    }

    public function delete(User $user, Document $document): Response
    {
        return $this->ownerOrAdmin($user, $document);
    }

    public function share(User $user, Document $document): Response
    {
        return $this->ownerOrAdmin($user, $document);
    }

    public function archive(User $user, Document $document): Response
    {
        return $this->ownerOrAdmin($user, $document);
    }

    public function restore(User $user, Document $document): Response
    {
        return $this->ownerOrAdmin($user, $document);
    }

    private function canEdit(User $user, Document $document): Response
    {
        $right = $document->rightFor($user);

        if ($right === null) {
            return Response::deny('Vous n\'avez pas accès à ce document.');
        }

        if ($document->isArchived()) {
            return Response::deny('Document archivé : il est en lecture seule. Restaurez-le pour le modifier.');
        }

        return in_array($right, [Document::RIGHT_ADMIN, Document::RIGHT_OWNER, Document::RIGHT_EDIT], true)
            ? Response::allow()
            : Response::deny('Vous n\'avez qu\'un droit de lecture sur ce document.');
    }

    private function ownerOrAdmin(User $user, Document $document): Response
    {
        $right = $document->rightFor($user);

        if ($right === null) {
            return Response::deny('Vous n\'avez pas accès à ce document.');
        }

        return in_array($right, [Document::RIGHT_ADMIN, Document::RIGHT_OWNER], true)
            ? Response::allow()
            : Response::deny('Seul le propriétaire ou un administrateur peut effectuer cette action.');
    }
}
