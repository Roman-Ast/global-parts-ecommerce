<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InterkomPrice extends Model
{
    protected $guarded = [];

    protected $table = 'interkom_price';

    public function importToDb()
    {
        $path = resource_path('pending-files/interkom/*.csv');

        $g = glob($path);
        
        foreach ($g as $file) {
            
            $data = array_map('str_getcsv', file($file));
            //dd($data);
            foreach ($data as $key => $row) {
                //$modifiedRow = explode(';', $row[0]);
                //dd($modifiedRow);
                //var_dump([$key, $row]);
                
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
