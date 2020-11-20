<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

/*
 * @module      Alarmprotokoll
 *
 * @prefix      AP
 *
 * @file        AP_messages.php
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

trait AP_messages
{
    /**
     * Updates the logging messages in the alarm protocol.
     *
     * @param string $Message
     * @param int $Type
     * 0    = Event
     * 1    = State
     * 2    = Alarm
     */
    public function UpdateMessages(string $Message, int $Type): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        //Write to archive variable first
        $archiveRetentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        if ($archiveRetentionTime > 0) {
            $this->SetValue('MessageArchive', $Message);
        }
        switch ($Type) {
            //0: Event
            case 0:
                $eventMessagesRetentionTime = $this->ReadPropertyInteger('EventMessagesRetentionTime');
                if ($eventMessagesRetentionTime > 0) {
                    $this->UpdateEventMessages($Message);
                }
                break;

            //1: State
            case 1:
                $amountStateMessages = $this->ReadPropertyInteger('AmountStateMessages');
                if ($amountStateMessages > 0) {
                    $this->UpdateStateMessages($Message);
                    $this->UpdateEventMessages($Message);
                }
                break;

            //2: Alarm
            case 2:
                $alarmMessagesRetentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
                if ($alarmMessagesRetentionTime > 0) {
                    $this->UpdateAlarmMessages($Message);
                    $this->UpdateEventMessages($Message);
                }
                break;

        }
    }

    /**
     * Deletes the messages.
     */
    public function DeleteMessages(): void
    {
        //Alarm messages
        $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');

        //State messages
        $this->SetValue('StateMessages', 'Keine Zustandsmeldungen vorhanden!');

        //Event messages
        $this->SetValue('EventMessages', 'Keine Ereignismeldungen vorhanden!');
    }

    /**
     * Deletes the event messages.
     */
    public function DeleteEventMessages(): void
    {
        $this->SetValue('EventMessages', 'Keine Ereignismeldungen vorhanden!');
    }

    /**
     * Deletes the state messages.
     */
    public function DeleteStateMessages(): void
    {
        $this->SetValue('StateMessages', 'Keine Zustandsmeldungen vorhanden!');
    }

    /**
     * Deletes the alarm messages.
     */
    public function DeleteAlarmMessages(): void
    {
        $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');
    }

    /**
     * Resets the alarm messages.
     */
    public function ResetAlarmMessages(): void
    {
        $retentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
        if ($retentionTime > 0) {
            //Get messages
            $content = array_merge(array_filter(explode("\n", $this->GetValue('AlarmMessages'))));
            //Check retention time
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
                $retentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
                if ($days >= $retentionTime) {
                    unset($content[$key]);
                }
            }
            if (empty($content)) {
                $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');
            }
        }
    }

    #################### Private

    /**
     * Updates the event messages.
     *
     * @param string $Message
     */
    private function UpdateEventMessages(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $retentionTime = $this->ReadPropertyInteger('EventMessagesRetentionTime');
        if ($retentionTime > 0) {
            //Get messages
            $content = array_merge(array_filter(explode("\n", $this->GetValue('EventMessages'))));
            //Check retention time
            foreach ($content as $key => $message) {
                //Delete empty message hint
                if (strpos($message, 'Keine Ereignismeldungen vorhanden!') !== false) {
                    unset($content[$key]);
                }
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
                    if ($days >= $retentionTime) {
                        unset($content[$key]);
                    }
                }
            }
            //Add new message at beginning
            array_unshift($content, $Message);
            $newContent = implode("\n", $content);
            $this->SetValue('EventMessages', $newContent);
        }
    }

    /**
     * Updates the state messages.
     *
     * @param string $Message
     */
    private function UpdateStateMessages(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        //Check amount of messages to display
        $amountStateMessages = $this->ReadPropertyInteger('AmountStateMessages');
        if ($amountStateMessages > 0) {
            if ($amountStateMessages == 1) {
                $this->SetValue('AlarmMessages', $Message);
            } else {
                //Get messages
                $content = array_merge(array_filter(explode("\n", $this->GetValue('StateMessages'))));
                //Delete empty message hint
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

    /**
     * Updates the alarm messages.
     *
     * @param string $Message
     */
    private function UpdateAlarmMessages(string $Message): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $retentionTime = $this->ReadPropertyInteger('AlarmMessagesRetentionTime');
        if ($retentionTime > 0) {
            //Get messages
            $content = array_merge(array_filter(explode("\n", $this->GetValue('AlarmMessages'))));
            //Check retention time
            foreach ($content as $key => $message) {
                //Delete empty message hint
                if (strpos($message, 'Keine Alarmmeldungen vorhanden!') !== false) {
                    unset($content[$key]);
                }
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
                    if ($days >= $retentionTime) {
                        unset($content[$key]);
                    }
                }
            }
            //Add new message at beginning
            array_unshift($content, $Message);
            $newContent = implode("\n", $content);
            $this->SetValue('AlarmMessages', $newContent);
        }
        if (empty($newContent)) {
            $this->SetValue('AlarmMessages', 'Keine Alarmmeldungen vorhanden!');
        }
    }

    /**
     * Sets the timer for resetting the alarm messages.
     */
    private function SetTimerResetAlarmMessages(): void
    {
        //Set timer to next day
        $timestamp = mktime(0, 05, 0, (int) date('n'), (int) date('j') + 1, (int) date('Y'));
        $now = time();
        $timerInterval = ($timestamp - $now) * 1000;
        $this->SetTimerInterval('ResetAlarmMessages', $timerInterval);
    }
}