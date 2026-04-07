<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Salesperson;
use Illuminate\Http\Request;

class SalespersonController extends Controller
{
    // Public: return active salespersons for the enquiry dropdown
    public function publicIndex()
    {
        $salespersons = Salesperson::active()->orderBy('name')->get(['id', 'name', 'phone_number']);
        return response()->json($salespersons);
    }

    // Admin: return all salespersons
    public function index()
    {
        return response()->json(Salesperson::orderBy('name')->get());
    }

    // Admin: create a salesperson
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'phone_number' => 'required|string|max:50',
            'is_active'    => 'boolean',
        ]);

        $salesperson = Salesperson::create($data);
        return response()->json($salesperson, 201);
    }

    // Admin: update a salesperson
    public function update(Request $request, Salesperson $salesperson)
    {
        $data = $request->validate([
            'name'         => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:50',
            'is_active'    => 'sometimes|boolean',
        ]);

        $salesperson->update($data);
        return response()->json($salesperson);
    }

    // Admin: delete a salesperson
    public function destroy(Salesperson $salesperson)
    {
        $salesperson->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
