<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmprotokoll/tree/master/Alarmprotokoll
 */

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

declare(strict_types=1);

trait AP_messages
{
    public function UpdateMessages(string $Message, int $Type): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        // Write to archive variable first
        $archiveRetentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        if ($archiveRetentionTime > 0) {
            $this->SetValue('MessageArchive', $Message);
        }
        switch ($Type) {
            case 0: # Event
                $eventMessagesRetentionTime = $this->ReadPropertyInteger('EventMessagesRetentionTime');
                if ($eventMessagesRetentionTime > 0) {
                    $this->UpdateEventMessages($Message);
                }
                break;

            case 1: # State
                $amountStateMessages = $this->ReadPropertyInteger('AmountStateMessages');
                if ($amountStateMessages > 0) {
                    $this->UpdateEventMessages($Message);
                    $this->UpdateStateMessages($Message);
                }
                break;

            case 2: # Alarm
                $alarmMessagesRetentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
                if ($alarmMessagesRetentionTime > 0) {
                    $this->UpdateEventMessages($Message);
                    $this->UpdateAlarmMessages($Message);
                }
                break;

        }
    }

    public function DeleteAllMessages(): void
    {
        $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');
        $this->SetValue('StateMessages', 'Keine Zustandsmeldungen vorhanden!');
        $this->SetValue('EventMessages', 'Keine Ereignismeldungen vorhanden!');
    }

    public function DeleteEventMessages(): void
    {
        $this->SetValue('EventMessages', 'Keine Ereignismeldungen vorhanden!');
    }

    public function DeleteStateMessages(): void
    {
        $this->SetValue('StateMessages', 'Keine Zustandsmeldungen vorhanden!');
    }

    public function DeleteAlarmMessages(): void
    {
        $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');
    }

    public function CleanUpMessages(): void
    {
        // Event messages
        if ($this->ReadPropertyInteger('EventMessagesRetentionTime') > 0) {
            $content = array_merge(array_filter(explode("\n", $this->GetValue('EventMessages'))));
            foreach ($content as $key => $message) {
                if (!empty($content)) {
                    $year = (int) substr($message, 6, 4);
                    $month = (int) substr($message, 3, 2);
                    $day = (int) substr($message, 0, 2);
                    $timestamp = mktime(0, 0, 0, $month, $day, $year);
                    $dateNow = date('d.m.Y');
                    $yearNow = (int) substr($dateNow, 6, 4);
                    $monthNow = (int) substr($dateNow, 3, 2);
                    $dayNow = (int) substr($dateNow, 0, 2);
                    $timeNow = mktime(0, 0, 0, $monthNow, $dayNow, $yearNow);
                    $difference = ($timestamp - $timeNow) / 86400;
                    $days = abs($difference);
                    if ($days >= $this->ReadPropertyInteger('EventMessagesRetentionTime')) {
                        unset($content[$key]);
                    }
                }
            }
            if (empty($content)) {
                $this->SetValue('EventMessages', 'Keine Ereignismeldungen vorhanden!');
            } else {
                $this->SetValue('EventMessages', $content);
            }
        }

        // Alarm messages
        if ($this->ReadPropertyInteger('AlarmMessagesRetentionTime') > 0) {
            $content = array_merge(array_filter(explode("\n", $this->GetValue('AlarmMessages'))));
            foreach ($content as $key => $message) {
                $year = (int) substr($message, 6, 4);
                $month = (int) substr($message, 3, 2);
                $day = (int) substr($message, 0, 2);
                $timestamp = mktime(0, 0, 0, $month, $day, $year);
                $dateNow = date('d.m.Y');
                $yearNow = (int) substr($dateNow, 6, 4);
                $monthNow = (int) substr($dateNow, 3, 2);
                $dayNow = (int) substr($dateNow, 0, 2);
                $timeNow = mktime(0, 0, 0, $monthNow, $dayNow, $yearNow);
                $difference = ($timestamp - $timeNow) / 86400;
                $days = abs($difference);
                if ($days >= $this->ReadPropertyInteger('AlarmMessagesRetentionTime')) {
                    unset($content[$key]);
                }
            }
            if (empty($content)) {
                $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');
            } else {
                $this->SetValue('AlarmMessages', $content);
            }
        }

        // Archive
        $retentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        if ($retentionTime > 0) {
            $archive = $this->ReadPropertyInteger('Archive');
            $instanceStatus = @IPS_GetInstance($this->InstanceID)['InstanceStatus'];
            if ($archive != 0 && @IPS_ObjectExists($archive) && $instanceStatus == 102) {
                // Set start time to 2000-01-01 12:00 am
                $startTime = 946684800;
                // Calculate end time
                $endTime = strtotime('-' . $retentionTime . ' days');
                @AC_DeleteVariableData($archive, $this->GetIDForIdent('MessageArchive'), $startTime, $endTime);
            }
        }

        $this->SetCleanUpMessagesTimer();
    }

    #################### Private

    private function RenameMessages(): void
    {
        // Rename alarm messages
        $alarmMessagesRetentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
        switch ($alarmMessagesRetentionTime) {
            case 0:
                $name = 'Alarmmeldungen (deaktiviert)';
                $this->SetValue('AlarmMessages', '');
                break;

            case 1:
                $name = 'Alarmmeldungen (' . $alarmMessagesRetentionTime . ' Tag)';
                break;

            case $alarmMessagesRetentionTime > 1:
                $name = 'Alarmmeldungen (' . $alarmMessagesRetentionTime . ' Tage)';
                break;

            default:
                $name = 'Alarmmeldungen';
        }
        $id = $this->GetIDForIdent('AlarmMessages');
        IPS_SetName($id, $name);
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableAlarmMessages'));

        // Rename state messages
        $amountStateMessages = $this->ReadPropertyInteger('AmountStateMessages');
        switch ($amountStateMessages) {
            case 0:
                $name = 'Zustandsmeldungen (deaktiviert)';
                $this->SetValue('StateMessages', '');
                break;

            case 1:
                $name = 'Zustandsmeldung (letzte)';
                break;

            case $amountStateMessages > 1:
                $name = 'Zustandsmeldungen (letzten ' . $amountStateMessages . ')';
                break;

            default:
                $name = 'Zustandsmeldung(en)';
        }
        $id = $this->GetIDForIdent('StateMessages');
        IPS_SetName($id, $name);
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableStateMessages'));

        // Rename event messages
        $eventMessagesRetentionTime = $this->ReadPropertyInteger('EventMessagesRetentionTime');
        switch ($eventMessagesRetentionTime) {
            case 0:
                $name = 'Ereignismeldungen (deaktiviert)';
                $this->SetValue('EventMessages', '');
                break;

            case 1:
                $name = 'Ereignismeldungen (' . $eventMessagesRetentionTime . ' Tag)';
                break;

            case $eventMessagesRetentionTime > 1:
                $name = 'Ereignismeldungen (' . $eventMessagesRetentionTime . ' Tage)';
                break;

            default:
                $name = 'Ereignismeldungen';
        }
        $id = $this->GetIDForIdent('EventMessages');
        IPS_SetName($id, $name);
        IPS_SetHidden($id, !$this->ReadPropertyBoolean('EnableEventMessages'));

        // Rename message archive
        $archiveRetentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        switch ($archiveRetentionTime) {
            case 0:
                $name = 'Archivdaten (deaktiviert)';
                break;

            case 1:
                $name = 'Archivdaten (' . $archiveRetentionTime . ' Tag)';
                break;

            case $archiveRetentionTime > 1:
                $name = 'Archivdaten (' . $archiveRetentionTime . ' Tage)';
                break;

            default:
                $name = 'Archivdaten';
        }
        IPS_SetName($this->GetIDForIdent('MessageArchive'), $name);
    }

    private function UpdateEventMessages(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->SendDebug(__FUNCTION__, $Message, 0);
        if ($this->ReadPropertyInteger('EventMessagesRetentionTime') > 0) {
            $content = array_merge(array_filter(explode("\n", $this->GetValue('EventMessages'))));
            foreach ($content as $key => $message) {
                // Delete empty message hint
                if (strpos($message, 'Keine Ereignismeldungen vorhanden!') !== false) {
                    unset($content[$key]);
                }
            }
            // Add new message at beginning
            array_unshift($content, $Message);
            $newContent = implode("\n", $content);
            $this->SetValue('EventMessages', $newContent);
        }
    }

    private function UpdateStateMessages(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        // Check amount of messages to display
        $amountStateMessages = $this->ReadPropertyInteger('AmountStateMessages');
        if ($amountStateMessages > 0) {
            if ($amountStateMessages == 1) {
                $this->SetValue('AlarmMessages', $Message);
            } else {
                $content = array_merge(array_filter(explode("\n", $this->GetValue('StateMessages'))));
                foreach ($content as $key => $message) {
                    // Delete empty message hint
                    if (strpos($message, 'Keine Zustandsmeldungen vorhanden!') !== false) {
                        unset($content[$key]);
                    }
                }
                $entries = $amountStateMessages - 1;
                array_splice($content, $entries);
                array_unshift($content, $Message);
                $newContent = implode("\n", $content);
                $this->SetValue('StateMessages', $newContent);
            }
        }
    }

    private function UpdateAlarmMessages(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $retentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
        if ($retentionTime > 0) {
            $content = array_merge(array_filter(explode("\n", $this->GetValue('AlarmMessages'))));
            foreach ($content as $key => $message) {
                // Delete empty message hint
                if (strpos($message, 'Keine Alarmmeldungen vorhanden!') !== false) {
                    unset($content[$key]);
                }
            }
            // Add new message at beginning
            array_unshift($content, $Message);
            $newContent = implode("\n", $content);
            $this->SetValue('AlarmMessages', $newContent);
        }
    }

    private function SetCleanUpMessagesTimer(): void
    {
        // Set timer to next day
        $timestamp = mktime(0, 05, 0, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        $timerInterval = ($timestamp - time()) * 1000;
        $this->SetTimerInterval('CleanUpMessages', $timerInterval);
    }
}