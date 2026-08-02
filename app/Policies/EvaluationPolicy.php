<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Evaluation;

class EvaluationPolicy
{
    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Evaluation $evaluation)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $evaluation->teacher_id === $user->id) {
            return true;
        }

        if ($user->isStudent() && $evaluation->is_published) {
            return true;
        }

        return false;
    }

    public function create(User $user)
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function update(User $user, Evaluation $evaluation)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $evaluation->teacher_id === $user->id;
    }

    public function delete(User $user, Evaluation $evaluation)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $evaluation->teacher_id === $user->id;
    }

    public function publish(User $user, Evaluation $evaluation)
    {
        return $this->update($user, $evaluation);
    }

    public function submit(User $user, Evaluation $evaluation)
    {
        return $user->isStudent() && $evaluation->is_published;
    }
}
