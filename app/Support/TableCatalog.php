<?php

namespace App\Support;

class TableCatalog
{
  /** @return array<int, array{slug: string, number: string, code: string}> */
  public static function getMasterCatalog(): array
  {
    return [
      ['slug' => 'mesa-01', 'number' => '01', 'code' => 'Mesa 1'],
      ['slug' => 'mesa-02', 'number' => '02', 'code' => 'Mesa 2'],
      ['slug' => 'mesa-03', 'number' => '03', 'code' => 'Mesa 3'],
      ['slug' => 'mesa-04', 'number' => '04', 'code' => 'Mesa 4'],
      ['slug' => 'mesa-05', 'number' => '05', 'code' => 'Mesa 5'],
      ['slug' => 'mesa-06', 'number' => '06', 'code' => 'Mesa 6'],
      ['slug' => 'mesa-07', 'number' => '07', 'code' => 'Mesa 7'],
      ['slug' => 'mesa-08', 'number' => '08', 'code' => 'Mesa 8'],
      ['slug' => 'mesa-09', 'number' => '09', 'code' => 'Mesa 9'],
      ['slug' => 'mesa-10', 'number' => '10', 'code' => 'Mesa 10'],
      ['slug' => 'mesa-11', 'number' => '11', 'code' => 'Mesa 11'],
      ['slug' => 'mesa-12', 'number' => '12', 'code' => 'Mesa 12'],
      ['slug' => 'mesa-13', 'number' => '13', 'code' => 'Mesa 13'],
      ['slug' => 'mesa-14', 'number' => '14', 'code' => 'Mesa 14'],
      ['slug' => 'mesa-15', 'number' => '15', 'code' => 'Mesa 15'],
      ['slug' => 'mesa-16', 'number' => '16', 'code' => 'Mesa 16'],
      ['slug' => 'mesa-17', 'number' => '17', 'code' => 'Mesa 17'],
      ['slug' => 'mesa-18', 'number' => '18', 'code' => 'Mesa 18'],
      ['slug' => 'mesa-19', 'number' => '19', 'code' => 'Mesa 19'],
      ['slug' => 'mesa-20', 'number' => '20', 'code' => 'Mesa 20'],
    ];
  }

  /** @return array<int, array{slug: string, number: string, code: string}> */
  public static function getSlice(int $count): array
  {
    $clamped = max(1, min(20, $count));
    return array_slice(self::getMasterCatalog(), 0, $clamped);
  }
}
