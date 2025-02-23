<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfficePrice extends Model
{
    protected $guarded = [];

    protected $table = 'office_price';

    public function importToDb()
    {
        $path = resource_path('pending-files/in-office/*.csv');

        $g = glob($path);

        foreach (array_slice($g, 0, 1) as $file) {
            
            $data = array_map('str_getcsv', file($file));
            
            foreach ($data as $row) {
                //$modifiedRow = explode(',', $row);
                //dd($data);
                self::updateOrCreate([
                    'oem' => $row[0],
                    'article' => $row[1],
                    'brand' => $row[2],
                    'name' => $row[3],
                    'price' => $row[4],
                    'qty' => $row[5]
                ]);
            }

            unlink($file);
        }
    }
}
