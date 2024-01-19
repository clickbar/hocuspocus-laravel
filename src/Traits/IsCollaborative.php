<?php

namespace Hocuspocus\Traits;

/** @mixin \Illuminate\Database\Eloquent\Model */
trait IsCollaborative
{
    public function getCollaborativeAttributes(): array
    {
        return $this->collaborativeAttributes ?? [];
    }

    public function getCollaborationDocumentName(): string
    {
        return urlencode(get_called_class().":".$this->getKey());
    }
}
