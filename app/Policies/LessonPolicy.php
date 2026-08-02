<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Lesson;

class LessonPolicy
{
    public function viewAny(User $user)
    {
        return true;
    }

    public function view(User $user, Lesson $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isTeacher() && $lesson->teacher_id === $user->id) {
            return true;
        }

        if ($user->isStudent() && $lesson->is_published) {
            return true;
        }

        return false;
    }

    public function create(User $user)
    {
        return $user->isAdmin() || $user->isTeacher();
    }

    public function update(User $user, Lesson $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $lesson->teacher_id === $user->id;
    }

    public function delete(User $user, Lesson $lesson)
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isTeacher() && $lesson->teacher_id === $user->id;
    }

    public function publish(User $user, Lesson $lesson)
    {
        return $this->update($user, $lesson);
    }
}
