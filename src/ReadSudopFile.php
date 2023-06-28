<?php

declare(strict_types=1);

namespace Przeslijmi\AgileDataSudopPlug;

use Przeslijmi\AgileData\Operations\OperationsInterface as MyInterface;
use Przeslijmi\AgileData\Operations\Reading\ReadFromCsv as MyParent;
use Przeslijmi\AgileData\Tools\ConvToDate;
use Przeslijmi\AgileDataSudopPlug\SudopHelper;
use stdClass;

/**
 * Operation that reads SUDOP data from one CSV file.
 */
class ReadSudopFile extends MyParent implements MyInterface
{

    /**
     * Operation key.
     *
     * @var string
     */
    protected static $opKey = '1fpElMDo';

    /**
     * Only those fields are accepted for this operation.
     *
     * @var array
     */
    public static $operationFields = [
        'fileUriDrive',
        'fileUriDir',
        'fileUri',
        'details',
    ];

    /**
     * Get info (mainly name and category of this operation).
     *
     * @return stdClass
     */
    public static function getInfo(): stdClass
    {

        // Lvd.
        $locSta = 'Przeslijmi.AgileDataSudopPlug.ReadSudopFile.';

        // Lvd.
        $result             = new stdClass();
        $result->name       = $_ENV['LOCALE']->get($locSta . 'title');
        $result->vendor     = 'Przeslijmi\AgileDataSudopPlug';
        $result->class      = self::class;
        $result->depr       = false;
        $result->category   = 100;
        $result->sourceName = $_ENV['LOCALE']->get($locSta . 'sourceName');

        return $result;
    }

    /**
     * Deliver fields to edit settings of this operation.
     *
     * @param string        $taskId Id of task in which edited step is present.
     * @param stdClass|null $step   Opt. Only when editing step (when creating it is null).
     *
     * @return array
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public static function getStepFormFields(string $taskId, ?stdClass $step = null): array
    {

        // Lvd.
        $fields = parent::getStepFormFields(...func_get_args());
        $loc    = $_ENV['LOCALE'];
        $locSta = 'Przeslijmi.AgileDataSudopPlug.ReadSudopFile.fields.';

        // Delete all fields except for fileChooser.
        foreach ($fields as $fieldId => $field) {
            if ($field['type'] !== 'fileChooser') {
                unset($fields[$fieldId]);
            }
        }

        // Add field that lets you choose what to read from SUDOP files.
        $fields[] = [
            'type' => 'select',
            'multiple' => 12,
            'id' => 'details',
            'noDefaultOptions' => true,
            'value' => ( $step->details ?? array_keys(SudopHelper::getColumns()) ),
            'name' => $loc->get($locSta . 'details.name'),
            'desc' => $loc->get($locSta . 'details.desc'),
            'options' => SudopHelper::getColumns(),
            'group' => $loc->get('Przeslijmi.AgileData.tabs.operation'),
        ];

        return $fields;
    }

    /**
     * Prevalidator is optional in operation class and converts step if it is needed.
     *
     * @param stdClass $step Original step.
     *
     * @return stdClass Converted step.
     */
    public function preValidation(stdClass $step): stdClass
    {

        // Define SUDOP standards.
        $step->encoding   = 'E11';
        $step->colSep     = ';';
        $step->firstRow   = null;
        $step->lastRow    = null;
        $step->mapColumns = SudopHelper::getMapColumns();

        // Call parent prevalidation.
        parent::preValidation($step);

        return $step;
    }

    /**
     * Validates plug definition.
     *
     * @return void
     */
    public function validate(): void
    {

        // All parent validations.
        parent::validate();

        // Test nodes.
        $this->testNodes($this->getStepPathInPlug(), $this->getStep(), [
            'details' => '!array',
        ]);
    }

    /**
     * Reads data from CSV file into task memory.
     *
     * @return void
     */
    public function perform(): void
    {

        // Read from files.
        parent::perform();

        // Get data.
        $data = (array) $this->getCallingTask()->getRecords();
        $data = SudopHelper::parseData($data, $this->getStep()->details);

        // Prepare dataTypes.
        $dataTypes = array_combine(
            array_column($this->getStep()->mapColumns, 'destinationProp'),
            array_column($this->getStep()->mapColumns, 'dataType'),
        );

        // Force `dateDmy` on `data_udzielenia`.
        $dataTypes['data_udzielenia'] = 'dateDmy';

        // Save new records.
        $this->getCallingTask()->replaceRecords($data, $dataTypes);
    }

    /**
     * Delivers simple list of props that is available after this operation finishes work.
     *
     * @param array $inputProps Properties available in previous operation.
     *
     * @return array[]
     *
     * @phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
     */
    public function getPropsAvailableAfter(array $inputProps): array
    {

        // Lvd.
        $columns = SudopHelper::getAllColumns();
        $result  = $this->getParamAsAvailableProp($this->getTask());

        // Calc result.
        foreach ($this->getStep()->details as $columnKey) {
            $result[$columns[$columnKey]['name']] = [
                'dataType' => $columns[$columnKey]['dataType'],
            ];
        }

        return $result;
    }
}
