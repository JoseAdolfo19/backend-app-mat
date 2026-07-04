<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    /**
     * Listar notificaciones del usuario
     */
    public function index(Request $request)
    {
        $query = Notification::where('user_id', Auth::id());

        // Filtrar por leído/no leído
        if ($request->has('unread') && $request->unread) {
            $query->where('is_read', false);
        }

        // Filtrar por tipo
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($notifications);
    }

    /**
     * Contar notificaciones no leídas
     */
    public function unreadCount()
    {
        $count = Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $count
        ]);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notificación marcada como leída'
        ]);
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Todas las notificaciones marcadas como leídas'
        ]);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy($id)
    {
        $notification = Notification::where('user_id', Auth::id())
            ->where('id', $id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'message' => 'Notificación eliminada'
        ]);
    }

    /**
     * Eliminar todas las notificaciones leídas
     */
    public function deleteRead()
    {
        Notification::where('user_id', Auth::id())
            ->where('is_read', true)
            ->delete();

        return response()->json([
            'message' => 'Notificaciones leídas eliminadas'
        ]);
    }

    /**
     * Crear notificación (método auxiliar estático)
     */
    public static function createNotification($userId, $title, $message, $type = 'info', $link = null)
    {
        return Notification::create([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'is_read' => false
        ]);
    }

    /**
     * Crear notificación para múltiples usuarios
     */
    public static function createBulkNotifications($userIds, $title, $message, $type = 'info', $link = null)
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'id' => Str::uuid(),
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'link' => $link,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now()
            ];
        }

        return Notification::insert($notifications);
    }
}