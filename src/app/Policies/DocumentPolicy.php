<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Solo el propietario puede consultar el documento.
     *
     * Los administradores no acceden al contenido: la privacidad documental
     * prevalece sobre la comodidad operativa.
     */
    public function view(User $user, Document $document): bool
    {
        return $user->getKey() === $document->user_id;
    }

    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function reprocess(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
