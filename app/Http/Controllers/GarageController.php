<?php

namespace App\Http\Controllers;

use App\Models\GaraGE;
use Illuminate\Http\Request;

class GarageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = auth()->user();
        $cars_in_garage = $user->garage;
        //dd($cars_in_garage);
        return view('garage.index', [
            'cars_in_garage' => $cars_in_garage
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('garage.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //dd($request);
        $car_in_garage = GaraGE::create([
            'user_id' => $request->user_id,
            'model' => $request->model,
            'year' => $request->year,
            'vincode' => $request->vincode,
            'licence' => $request->licence,
            'owner_name' => $request->owner_name,
            'owner_phone' => $request->owner_phone,
            'note' => $request->note,
        ]);

        return view('garage.index')
            ->with('success_message', '!')
            ->with('class', 'alert-success');
    }

    /**
     * Display the specified resource.
     */
    public function show(GaraGE $garaGE)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(GaraGE $garaGE)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GaraGE $garaGE)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GaraGE $garaGE)
    {
        //
    }
}
