<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Video;

class VideoPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Video $video): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Video $video): bool
    {
        return true;
    }

    public function delete(User $user, Video $video): bool
    {
        return true;
    }
}
