<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class AlarmprotokollValidationTest extends TestCaseSymconValidation
{
    public function testValidateAlarmprotokoll(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateAlarmprotokollModule(): void
    {
        $this->validateModule(__DIR__ . '/../Alarmprotokoll');
    }
}