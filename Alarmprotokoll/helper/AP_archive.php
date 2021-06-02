<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmprotokoll/tree/master/Alarmprotokoll
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait AP_archive
{
    public function SetArchiveLogging(bool $State): void
    {
        $id = $this->ReadPropertyInteger('Archive');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            @AC_SetLoggingStatus($id, $this->GetIDForIdent('MessageArchive'), $State);
            @IPS_ApplyChanges($id);
            $text = 'Es ist ein Fehler aufgetreten!';
            switch ($State) {
                case false:
                    $text = 'Es werden keine Daten mehr archiviert!';
                    break;

                case true:
                    $text = 'Die Daten werden archiviert!';
                    break;

            }
            $this->SendDebug(__FUNCTION__, $text, 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'Es ist kein Archiv ausgewählt!', 0);
        }
    }

    public function ShowArchiveLoggingState(): void
    {
        $id = $this->ReadPropertyInteger('Archive');
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $variables = @AC_GetAggregationVariables($id, false);
            $state = false;
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    $variableID = $variable['VariableID'];
                    if ($variableID == $this->GetIDForIdent('MessageArchive')) {
                        $state = @AC_GetLoggingStatus($id, $variableID);
                    }
                }
            }
            $text = 'Es ist ein Fehler aufgetreten!';
            switch ($state) {
                case false:
                    $text = 'Es werden keine Daten archiviert!';
                    break;

                case true:
                    $text = 'Die Daten werden archiviert!';
                    break;

            }
            echo $text;
        } else {
            echo 'Es ist kein Archiv ausgewählt!';
        }
    }
}