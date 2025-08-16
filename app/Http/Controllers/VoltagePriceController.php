<?php

namespace App\Http\Controllers;

use App\Models\VoltagePrice;
use Illuminate\Http\Request;

class VoltagePriceController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt'
        ]);

        $file = file($request->file->getRealPath());
        //dd($file);
        $data = array_slice($file, 1);

        $parts = (array_chunk($data, 9000));

        foreach ($parts as $index => $part) {
            $fileName = resource_path('/pending-files/voltage/' .date('y-m-d-H-i-s'). $index . '.csv');

            file_put_contents($fileName, $part);

            session()->flash('status', 'queued for importing');
        }
        (new VoltagePrice())->importToDb();

        return redirect('admin_panel');
    }
}
