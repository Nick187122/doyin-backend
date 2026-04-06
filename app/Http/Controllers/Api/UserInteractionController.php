<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserInteraction;
use Illuminate\Http\Request;

class UserInteractionController extends Controller
{
    public function index()
    {
        $interactions = UserInteraction::orderBy('created_at', 'desc')->get();
        return response()->json($interactions);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type'    => 'required|in:message,issue',
            'name'    => 'nullable|string|max:255',
            'email'   => 'nullable|email|max:255',
            'content' => 'required|string|max:5000',
        ]);

        $interaction = UserInteraction::create([
            'type' => $data['type'],
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'content' => $data['content'],
            'is_read' => false,
        ]);

        // Enforce max 100 queue cleanup
        $count = UserInteraction::count();
        if ($count > 100) {
            $toDelete = $count - 100;
            // Delete the oldest ones
            $oldestIds = UserInteraction::orderBy('created_at', 'asc')->take($toDelete)->pluck('id');
            UserInteraction::whereIn('id', $oldestIds)->delete();
        }

        return response()->json(['message' => 'Submitted successfully', 'interaction' => $interaction], 201);
    }

    public function markAsRead(UserInteraction $interaction)
    {
        $interaction->is_read = true;
        $interaction->save();
        return response()->json(['message' => 'Marked as read', 'interaction' => $interaction]);
    }

    public function destroy(UserInteraction $interaction)
    {
        $interaction->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }

    public function clearAll(Request $request)
    {
        // Add a slot to clear all
        UserInteraction::query()->delete();
        return response()->json(['message' => 'All interactons cleared successfully']);
    }
}
