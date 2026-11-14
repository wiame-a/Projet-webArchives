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
 *  - supprimer, partager       : propriétaire ou administrateur
 *  - archiver, restaurer       : propriétaire, administrateur ou archiviste
 *  - valider, rejeter, rouvrir : administrateur ou archiviste (document non archivé)
 *
 * Un archiviste voit tous les documents et peut les modifier (non archivés),
 * mais ne peut ni supprimer ni partager un document qui n'est pas le sien.
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
        return $this->ownerOrStaff($user, $document);
    }

    public function restore(User $user, Document $document): Response
    {
        return $this->ownerOrStaff($user, $document);
    }

    /** Historique d'activité d'un document : propriétaire, archiviste ou administrateur. */
    public function activity(User $user, Document $document): Response
    {
        return in_array($document->rightFor($user), [Document::RIGHT_ADMIN, Document::RIGHT_ARCHIVISTE, Document::RIGHT_OWNER], true)
            ? Response::allow()
            : Response::deny('L\'historique d\'activité est réservé au propriétaire et aux gestionnaires.');
    }

    public function validate(User $user, Document $document): Response
    {
        return $this->staffOnly($user, $document);
    }

    public function reject(User $user, Document $document): Response
    {
        return $this->staffOnly($user, $document);
    }

    public function reopen(User $user, Document $document): Response
    {
        return $this->staffOnly($user, $document);
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

        return in_array($right, [Document::RIGHT_ADMIN, Document::RIGHT_ARCHIVISTE, Document::RIGHT_OWNER, Document::RIGHT_EDIT], true)
            ? Response::allow()
            : Response::deny('Vous n\'avez qu\'un droit de lecture sur ce document.');
    }

    /** Décision de validation : administrateur ou archiviste, sur un document non archivé. */
    private function staffOnly(User $user, Document $document): Response
    {
        if (! $user->isStaff()) {
            return Response::deny('Seuls un administrateur ou un archiviste peuvent valider ou rejeter un document.');
        }

        return $document->isArchived()
            ? Response::deny('Document archivé : restaurez-le avant de modifier sa validation.')
            : Response::allow();
    }

    private function ownerOrStaff(User $user, Document $document): Response
    {
        $right = $document->rightFor($user);

        if ($right === null) {
            return Response::deny('Vous n\'avez pas accès à ce document.');
        }

        return in_array($right, [Document::RIGHT_ADMIN, Document::RIGHT_ARCHIVISTE, Document::RIGHT_OWNER], true)
            ? Response::allow()
            : Response::deny('Seul le propriétaire, un archiviste ou un administrateur peut effectuer cette action.');
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
