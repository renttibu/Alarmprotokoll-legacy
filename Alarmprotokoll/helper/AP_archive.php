<?php

/** @noinspection PhpUnused */

/*
 * @module      Alarmprotokoll
 *
 * @prefix      AP
 *
 * @file        AP_archive.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @see         https://github.com/ubittner/Alarmprotokoll
 *
 */

declare(strict_types=1);

trait AP_archive
{
    /**
     * Enables and disables the logging to the archive instance.
     *
     * @param bool $State
     * false    = don't archive data
     * true     = archive data
     */
    public function SetArchiveLogging(bool $State): void
    {
        $archive = $this->ReadPropertyInteger('Archive');
        if ($archive != 0 && @IPS_ObjectExists($archive)) {
            @AC_SetLoggingStatus($archive, $this->GetIDForIdent('MessageArchive'), $State);
            @IPS_ApplyChanges($archive);
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

    /**
     * Shows the logging of archive state.
     */
    public function ShowArchiveState(): void
    {
        $archive = $this->ReadPropertyInteger('Archive');
        if ($archive != 0 && @IPS_ObjectExists($archive)) {
            $variables = @AC_GetAggregationVariables($archive, false);
            $state = false;
            if (!empty($variables)) {
                foreach ($variables as $variable) {
                    $variableID = $variable['VariableID'];
                    if ($variableID == $this->GetIDForIdent('MessageArchive')) {
                        $state = @AC_GetLoggingStatus($archive, $variableID);
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

    /**
     * Deletes archive data older then the retention time.
     */
    public function CleanUpArchiveData(): void
    {
        $archive = $this->ReadPropertyInteger('Archive');
        $instanceStatus = @IPS_GetInstance($this->InstanceID)['InstanceStatus'];
        if ($archive != 0 && @IPS_ObjectExists($archive) && $instanceStatus == 102) {
            //Set start time to 2000-01-01 12:00 am
            $startTime = 946684800;
            //Calculate end time
            $retentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
            $endTime = strtotime('-' . $retentionTime . ' days');
            @AC_DeleteVariableData($archive, $this->GetIDForIdent('MessageArchive'), $startTime, $endTime);
            //Set timer to next 24 hours
            $this->SetTimerCleanUpArchiveData();
        }
    }

    ################### Private

    /**
     * Sets the timer for deleting archive data.
     */
    private function SetTimerCleanUpArchiveData(): void
    {
        $archiveRetentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        if ($archiveRetentionTime > 0) {
            //Set timer for deleting archive data
            $instanceStatus = @IPS_GetInstance($this->InstanceID)['InstanceStatus'];
            $archive = $this->ReadPropertyInteger('Archive');
            if ($archive != 0 && @IPS_ObjectExists($archive) && $instanceStatus == 102) {
                //Set timer to next date
                $timestamp = mktime(2, 00, 0, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
                $now = time();
                $timerInterval = ($timestamp - $now) * 1000;
                $this->SetTimerInterval('CleanUpArchiveData', $timerInterval);
            }
        }
        if ($archiveRetentionTime <= 0) {
            $this->SetTimerInterval('CleanUpArchiveData', 0);
        }
    }
}