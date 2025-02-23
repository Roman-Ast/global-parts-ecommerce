<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class gm_pricelist_from_adil extends Model
{
    protected $guarded = [];

    public function importToDb()
    {
        $path = resource_path('pending-files/*.csv');

        $g = glob($path);

        foreach (array_slice($g, 0, 1) as $file) {
            
            $data = array_map('str_getcsv', file($file));
            
            foreach ($data as $row) {
                $modifiedRow = explode(';', $row[0]);
                //dd($modifiedRow);
                self::updateOrCreate([
                    'oem' => $modifiedRow[0],
                    'article' => $modifiedRow[1],
                    'brand' => $modifiedRow[2],
                    'name' => $modifiedRow[3],
                    'price' => $modifiedRow[4],
                    'qty' => $modifiedRow[5]
                ]);
            }

            unlink($file);
        }
    }
}
