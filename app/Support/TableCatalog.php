<?php

namespace App\Support;

class TableCatalog
{
  /** @return array<int, array{slug: string, number: string, code: string, zone: string, centerpiece: string}> */
  public static function getMasterCatalog(): array
  {
    return [
      ['slug' => 'mesa-01', 'number' => '01', 'code' => 'Mesa 01 · Principal', 'zone' => 'Tarima de Honor · Frente a Pista', 'centerpiece' => 'Rosas blancas e iluminación cálida'],
      ['slug' => 'mesa-02', 'number' => '02', 'code' => 'Mesa 02 · Orquídea', 'zone' => 'Ala Norte · Jardín Interior', 'centerpiece' => 'Orquídeas imperiales y cristal'],
      ['slug' => 'mesa-03', 'number' => '03', 'code' => 'Mesa 03 · Magnolia', 'zone' => 'Ala Norte · Jardín Interior', 'centerpiece' => 'Magnolias silvestres y velas altas'],
      ['slug' => 'mesa-04', 'number' => '04', 'code' => 'Mesa 04 · Esmeralda', 'zone' => 'Sector Central · Pista de Vals', 'centerpiece' => 'Follaje esmeralda y candelabro'],
      ['slug' => 'mesa-05', 'number' => '05', 'code' => 'Mesa 05 · Laurel', 'zone' => 'Sector Central · Pista de Vals', 'centerpiece' => 'Ramas de laurel y rosas marfil'],
      ['slug' => 'mesa-06', 'number' => '06', 'code' => 'Mesa 06 · Jazmín', 'zone' => 'Terraza Cubierta · Lounge', 'centerpiece' => 'Jazmines aromáticos y piedra oscura'],
      ['slug' => 'mesa-07', 'number' => '07', 'code' => 'Mesa 07 · Zafiro', 'zone' => 'Sector Central · Pista de Vals', 'centerpiece' => 'Zafiros luminosos y velas flotantes'],
      ['slug' => 'mesa-08', 'number' => '08', 'code' => 'Mesa 08 · Diamante', 'zone' => 'Sector VIP · Lateral Tarima', 'centerpiece' => 'Cristales de diamante y orquídeas blancas'],
      ['slug' => 'mesa-09', 'number' => '09', 'code' => 'Mesa 09 · Rubí', 'zone' => 'Ala Sur · Salón de Cristal', 'centerpiece' => 'Rosas rubí y candelabro de plata'],
      ['slug' => 'mesa-10', 'number' => '10', 'code' => 'Mesa 10 · Ámbar', 'zone' => 'Ala Sur · Salón de Cristal', 'centerpiece' => 'Luces cálidas de ámbar y follaje dorado'],
      ['slug' => 'mesa-11', 'number' => '11', 'code' => 'Mesa 11 · Platino', 'zone' => 'Sector VIP · Lateral Tarima', 'centerpiece' => 'Arreglo imperial platino y lirios'],
      ['slug' => 'mesa-12', 'number' => '12', 'code' => 'Mesa 12 · Cristal', 'zone' => 'Ala Norte · Mirador Interior', 'centerpiece' => 'Esferas de cristal y rosas marfil'],
      ['slug' => 'mesa-13', 'number' => '13', 'code' => 'Mesa 13 · Perla', 'zone' => 'Terraza Cubierta · Lounge', 'centerpiece' => 'Perlas nacaradas y hortensias'],
      ['slug' => 'mesa-14', 'number' => '14', 'code' => 'Mesa 14 · Topacio', 'zone' => 'Ala Sur · Salón de Cristal', 'centerpiece' => 'Topacios brillantes y ramas secas decoradas'],
      ['slug' => 'mesa-15', 'number' => '15', 'code' => 'Mesa 15 · Cuarzo', 'zone' => 'Sector Central · Lateral Pista', 'centerpiece' => 'Cuarzos rosa y rosas pastel'],
      ['slug' => 'mesa-16', 'number' => '16', 'code' => 'Mesa 16 · Lavanda', 'zone' => 'Ala Norte · Mirador Interior', 'centerpiece' => 'Espigas de lavanda y velas aromáticas'],
      ['slug' => 'mesa-17', 'number' => '17', 'code' => 'Mesa 17 · Camelia', 'zone' => 'Terraza Cubierta · Lounge', 'centerpiece' => 'Camelias de seda y faroles de cobre'],
      ['slug' => 'mesa-18', 'number' => '18', 'code' => 'Mesa 18 · Ópalo', 'zone' => 'Ala Sur · Jardín Imperial', 'centerpiece' => 'Ópalos reflectantes y follaje verde'],
      ['slug' => 'mesa-19', 'number' => '19', 'code' => 'Mesa 19 · Lirio', 'zone' => 'Ala Norte · Jardín Interior', 'centerpiece' => 'Lirios reales y copas de cristal'],
      ['slug' => 'mesa-20', 'number' => '20', 'code' => 'Mesa 20 · Violeta', 'zone' => 'Terraza Cubierta · Lounge', 'centerpiece' => 'Violetas imperiales y detalles dorados'],
    ];
  }

  /** @return array<int, array{slug: string, number: string, code: string, zone: string, centerpiece: string}> */
  public static function getSlice(int $count): array
  {
    $clamped = max(1, min(20, $count));
    return array_slice(self::getMasterCatalog(), 0, $clamped);
  }
}
