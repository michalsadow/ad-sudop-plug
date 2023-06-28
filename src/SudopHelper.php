<?php

declare(strict_types=1);

namespace Przeslijmi\AgileDataSudopPlug;

use Przeslijmi\AgileData\Steps\Helpers\DataTypes;

/**
 * Operation that reads data from many CSV files at one time.
 */
class SudopHelper
{

    /**
     * Columns set in SUDOP information.
     *
     * @var array
     */
    public const COLUMNS = [
        'S01' => [
            'name' => 'nazwa_firmy',
            'csvSource' => 'A',
            'dataType' => 'txt',
        ],
        'S02' => [
            'name' => 'nip_firmy',
            'csvSource' => 'A',
            'dataType' => 'txt',
        ],
        'S03' => [
            'name' => 'inf_2a',
            'csvSource' => 'A',
            'dataType' => 'txt',
        ],
        'S04' => [
            'name' => 'inf_2b',
            'csvSource' => 'B',
            'dataType' => 'txt',
        ],
        'S05' => [
            'name' => 'inf_2c',
            'csvSource' => 'C',
            'dataType' => 'txt',
        ],
        'S06' => [
            'name' => 'inf_3a',
            'csvSource' => 'D',
            'dataType' => 'txt',
        ],
        'S07' => [
            'name' => 'inf_3b',
            'csvSource' => 'E',
            'dataType' => 'txt',
        ],
        'S08' => [
            'name' => 'inf_3c',
            'csvSource' => 'F',
            'dataType' => 'txt',
        ],
        'S09' => [
            'name' => 'numer_srodka_pomocowego',
            'csvSource' => 'G',
            'dataType' => 'txt',
        ],
        'S10' => [
            'name' => 'data_udzielenia',
            'csvSource' => 'H',
            'dataType' => 'txt',
            'lateDataType' => 'dateDmy'
        ],
        'S11' => [
            'name' => 'nazwa_udzielajacego',
            'csvSource' => 'I',
            'dataType' => 'txt',
        ],
        'S12' => [
            'name' => 'nip_udzielajacego',
            'csvSource' => 'J',
            'dataType' => 'txt',
        ],
        'S13' => [
            'name' => 'wartosc_nominalna_pomocy',
            'csvSource' => 'K',
            'dataType' => 'currPLN',
        ],
        'S14' => [
            'name' => 'wartosc_pomocy_brutto_PLN',
            'csvSource' => 'L',
            'dataType' => 'currPLN',
        ],
        'S15' => [
            'name' => 'wartosc_pomocy_brutto_EUR',
            'csvSource' => 'M',
            'dataType' => 'currEUR',
        ],
        'S16' => [
            'name' => 'forma_pomocy',
            'csvSource' => 'N',
            'dataType' => 'txt',
        ],
        'S17' => [
            'name' => 'przeznaczenie_pomocy',
            'csvSource' => 'O',
            'dataType' => 'txt',
        ],
    ];

    /**
     * Getter for all columns set in SUDOP information.
     *
     * @return array
     */
    public static function getAllColumns(): array
    {

        return self::COLUMNS;
    }

    /**
     * Deliver ready data for map columns for CSV reader.
     *
     * @return array
     */
    public static function getMapColumns(): array
    {

        // Lvd.
        $result = [];

        // Prepare result.
        foreach (self::COLUMNS as $id => $def) {
            $result[] = (object) [
                'name' => 'object',
                'sourceColumn' => $def['csvSource'],
                'destinationProp' => $def['name'],
                'dataType' => $def['dataType'],
            ];
        }

        return $result;
    }

    /**
     * Delivers name of SUDOP columns.
     *
     * @return array
     */
    public static function getColumns(): array
    {

        // Lvd.
        $result = [];

        // Prepare result.
        foreach (self::COLUMNS as $id => $def) {
            $result[$id] = $def['name'];
        }

        return $result;
    }

    /**
     * Get data from CSV reader and convert it to SUDOP real data.
     *
     * @param array $data    Data from CSV reader.
     * @param array $details Which SUDOP columns have to be included.
     *
     * @return array
     */
    public static function parseData(array $data, array $details): array
    {

        // Define empty data.
        $companyName = null;
        $companyNip  = null;
        $defCompNext = false;
        $mapColumns  = self::getMapColumns();

        // Prepare full proper data.
        foreach ($data as $key => $record) {

            // This is a row with company name and nip - read it but then delete it and move data.
            if ($defCompNext === true) {
                $companyName = $record->properties->{'inf_2a'};
                $companyNip  = $record->properties->{'inf_2b'};
                $defCompNext = false;
                unset($data[$key]);
                continue;
            }

            // This is a row preceeding company name and nip.
            if ($record->properties->{'inf_2a'} === 'Nazwa beneficjenta pomocy') {
                $defCompNext = true;
                unset($data[$key]);
                continue;
            }

            // This is a row preceeding details - is not needed at all.
            if ($record->properties->{'inf_2a'} === 'Podstawa prawna - informacje podstawowe 2a') {
                unset($data[$key]);
                continue;
            }

            // Define proper.
            $data[$key]->properties->{'nazwa_firmy'} = $companyName;
            $data[$key]->properties->{'nip_firmy'}   = $companyNip;
        }//end foreach

        // Find columns to be deleted.
        $columnsToDelete = array_diff(array_keys(self::COLUMNS), $details);

        // Now delete details that are not needed.
        foreach ($columnsToDelete as $id) {

            // Lvd.
            $name = self::COLUMNS[$id]['name'];

            // Delete this column in every row.
            foreach ($data as $key => $record) {
                unset($data[$key]->properties->{$name});
            }
        }

        // Find data types for columns.
        $dataTypes = [];
        foreach (self::COLUMNS as $column) {
            $dataTypes[$column['name']] = ( $column['lateDataType'] ?? $column['dataType'] );
        }

        // Add data types.
        foreach ($data as $key => $record) {
            foreach ((array) $record->properties as $property => $value) {
                $data[$key]->properties->{$property} = DataTypes::conv((string) $value, $dataTypes[$property]);
            }
        }

        return $data;
    }
}
