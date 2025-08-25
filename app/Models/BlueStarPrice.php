<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BlueStarPrice extends Model
{
    protected $guarded = [];

    protected $table = 'blue_star_price';

    public function importToDb()
    {
        $path = resource_path('/pending-files/blue-star/*.csv');

        $g = glob($path);

        foreach (array_slice($g, 0, 1) as $file) {
            
            $data = array_map('str_getcsv', file($file));
            //dd($data);
            foreach ($data as $key => $row) {
                //dd($row);
                $modifiedRow = explode(';', $row[0]);
                //dd($modifiedRow);
                
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
