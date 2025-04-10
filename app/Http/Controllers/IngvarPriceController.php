<?php

namespace App\Http\Controllers;

use App\Models\IngvarPrice;
use Illuminate\Http\Request;

class IngvarPriceController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt'
        ]);

        $file = file($request->file->getRealPath());
        //dd($file);
        $data = array_slice($file, 1);

        $parts = (array_chunk($data, 6000));

        foreach ($parts as $index => $part) {
            $fileName = resource_path('/pending-files/ingvar/' .date('y-m-d-H-i-s'). $index . '.csv');

            file_put_contents($fileName, $part);

            session()->flash('status', 'queued for importing');

            return redirect('admin_panel');
        }
    }
}
