<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kid;
use Illuminate\Http\Request;

class KidController extends Controller
{
    /**
     * Display a listing of the user's kids.
     */
    public function index(Request $request)
    {
        // Policy automatically checked via middleware
        $this->authorize('viewAny', Kid::class);

        $kids = $request->user()
            ->kids()
            ->withCount('moments')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($kid) {
                return [
                    'id' => $kid->id,
                    'name' => $kid->name,
                    'date_of_birth' => $kid->date_of_birth->format('Y-m-d'),
                    'avatar_color' => $kid->avatar_color,
                    'age' => $kid->age,
                    'age_detailed' => $kid->age_detailed,
                    'moments_count' => $kid->moments_count,
                    'created_at' => $kid->created_at->toIso8601String(),
                    'updated_at' => $kid->updated_at->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $kids,
        ]);
    }

    /**
     * Store a newly created kid.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Kid::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:today',
            'avatar_color' => 'nullable|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $kid = $request->user()->kids()->create([
            'name' => $validated['name'],
            'date_of_birth' => $validated['date_of_birth'],
            'avatar_color' => $validated['avatar_color'] ?? Kid::randomAvatarColor(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Kid created successfully',
            'data' => [
                'id' => $kid->id,
                'name' => $kid->name,
                'date_of_birth' => $kid->date_of_birth->format('Y-m-d'),
                'avatar_color' => $kid->avatar_color,
                'age' => $kid->age,
                'age_detailed' => $kid->age_detailed,
                'moments_count' => 0,
                'created_at' => $kid->created_at->toIso8601String(),
                'updated_at' => $kid->updated_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Display the specified kid.
     */
    public function show(Kid $kid)
    {
        $this->authorize('view', $kid);

        $kid->loadCount('moments');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $kid->id,
                'name' => $kid->name,
                'date_of_birth' => $kid->date_of_birth->format('Y-m-d'),
                'avatar_color' => $kid->avatar_color,
                'age' => $kid->age,
                'age_detailed' => $kid->age_detailed,
                'age_in_months' => $kid->age_in_months,
                'moments_count' => $kid->moments_count,
                'first_moment' => $kid->getFirstMoment()?->display_date?->toIso8601String(),
                'latest_moment' => $kid->getLatestMoment()?->display_date?->toIso8601String(),
                'created_at' => $kid->created_at->toIso8601String(),
                'updated_at' => $kid->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Update the specified kid.
     */
    public function update(Request $request, Kid $kid)
    {
        $this->authorize('update', $kid);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'date_of_birth' => 'sometimes|date|before:today',
            'avatar_color' => 'sometimes|string|regex:/^#[0-9A-F]{6}$/i',
        ]);

        $kid->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Kid updated successfully',
            'data' => [
                'id' => $kid->id,
                'name' => $kid->name,
                'date_of_birth' => $kid->date_of_birth->format('Y-m-d'),
                'avatar_color' => $kid->avatar_color,
                'age' => $kid->age,
                'age_detailed' => $kid->age_detailed,
                'moments_count' => $kid->getMomentsCount(),
                'created_at' => $kid->created_at->toIso8601String(),
                'updated_at' => $kid->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Remove the specified kid.
     */
    public function destroy(Kid $kid)
    {
        $this->authorize('delete', $kid);

        $kid->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kid deleted successfully',
        ]);
    }
}
