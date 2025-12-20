<?php

namespace App\Http\Controllers;

use App\Models\InterkomPrice;
use Illuminate\Http\Request;

class InterkomPriceController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt'
        ]);

        $file = file($request->file->getRealPath());
        //dd($file);
        $data = array_slice($file, 1);

        $parts = (array_chunk($data, 5000));
        //dd($parts);
        foreach ($parts as $index => $part) {
            $fileName = resource_path('/pending-files/interkom/' .date('y-m-d-H-i-s'). $index . '.csv');

            file_put_contents($fileName, $part);

            session()->flash('status', 'queued for importing');
        }
        //(new InterkomPrice())->importToDb();

        return redirect('admin_panel');
    }
}
