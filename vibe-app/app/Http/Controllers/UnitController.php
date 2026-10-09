<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnitController extends Controller
{
    public function index()
    {
        return view('procurement-units', [
            'defaultUnits' => Unit::DEFAULTS,
            'customUnits' => Unit::query()->orderBy('name')->get(),
            'isMasterUser' => request()->user()->role === 'master_user',
            'activeProcurementArea' => 'units',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:50']]);
        $name = trim(preg_replace('/\s+/', ' ', $data['name']));
        $exists = collect(Unit::options())->contains(fn (string $unit) => mb_strtolower($unit) === mb_strtolower($name));
        if ($exists) {
            throw ValidationException::withMessages(['name' => '"'.$name.'" is already in the list.']);
        }

        Unit::create(['name' => $name]);

        return redirect()->route('units.index')->with('success', 'Unit "'.$name.'" added.');
    }

    public function destroy(Unit $unit): RedirectResponse
    {
        $name = $unit->name;
        $unit->delete();

        return redirect()->route('units.index')->with('success', 'Unit "'.$name.'" removed. Existing requests keep the unit they already use.');
    }
}
