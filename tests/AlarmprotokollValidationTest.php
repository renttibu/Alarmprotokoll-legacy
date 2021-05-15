<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class AlarmprotokollValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary_Alarmprotokoll(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateModule_Alarmprotokoll(): void
    {
        $this->validateModule(__DIR__ . '/../Alarmprotokoll');
    }
}