<?php

/** @noinspection PhpUnused */

/*
 * @module      Alarmprotokoll
 *
 * @prefix      AP
 *
 * @file        AP_protocol.php
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

trait AP_protocol
{
    /**
     * Sends the monthly journal.
     *
     * @param bool $CheckDay
     * false    = don't check day
     * true     = check day
     *
     * @param int $ProtocolPeriod
     * 0        = actual month
     * 1        = last month
     */
    public function SendMonthlyProtocol(bool $CheckDay, int $ProtocolPeriod): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $recipients = json_decode($this->ReadPropertyString('Recipients'));
        if (!empty($recipients)) {
            //Check if it is the first day of the month
            $day = date('j');
            if ($day == '1' || !$CheckDay) {
                //Prepare data
                $archive = $this->ReadPropertyInteger('Archive');
                if ($archive != 0) {
                    //This month
                    $startTime = strtotime('first day of this month midnight');
                    $endTime = strtotime('first day of next month midnight') - 1;
                    //Last month
                    if ($ProtocolPeriod == 1) {
                        $startTime = strtotime('first day of previous month midnight');
                        $endTime = strtotime('first day of this month midnight') - 1;
                    }
                    $designation = $this->ReadPropertyString('Designation');
                    $month = date('n', $startTime);
                    $monthName = [
                        1           => 'Januar',
                        2           => 'Februar',
                        3           => 'März',
                        4           => 'April',
                        5           => 'Mai',
                        6           => 'Juni',
                        7           => 'Juli',
                        8           => 'August',
                        9           => 'September',
                        10          => 'Oktober',
                        11          => 'November',
                        12          => 'Dezember'];
                    $year = date('Y', $startTime);
                    $text = 'Monatsprotokoll ' . $monthName[$month] . ' ' . $year . ', ' . $designation . ":\n\n\n";
                    $messages = AC_GetLoggedValues($archive, $this->GetIDForIdent('MessageArchive'), $startTime, $endTime, 0);
                    if (empty($messages)) {
                        $text .= 'Es sind keine Ereignisse vorhanden.';
                    } else {
                        foreach ($messages as $message) {
                            $text .= $message['Value'] . "\n";
                        }
                    }
                    //Send mail to recipients
                    foreach ($recipients as $recipient) {
                        if ($recipient->MonthlyProtocol && $recipient->ID != 0 && !empty($recipient->Address) && $recipient->Use) {
                            $mailSubject = $this->ReadPropertyString('MonthlyProtocolSubject') . ' ' . $monthName[$month] . ' ' . $year . ', ' . $designation;
                            @SMTP_SendMailEx($recipient->ID, $recipient->Address, $mailSubject, $text);
                        }
                    }
                }
            }
        }
        $this->SetTimerSendMonthlyProtocol();
    }

    /**
     * Sends the archive journal.
     */
    public function SendArchiveProtocol(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        //Get email recipients
        $recipients = json_decode($this->ReadPropertyString('Recipients'));
        if (!empty($recipients)) {
            //Prepare data
            //Set start time to 2000-01-01 12:00 am
            $startTime = 946684800;
            $endTime = time();
            $designation = $this->ReadPropertyString('Designation');
            $text = 'Archivprotokoll' . ' ' . $designation . ":\n\n\n";
            $messages = AC_GetLoggedValues($this->ReadPropertyInteger('Archive'), $this->GetIDForIdent('MessageArchive'), $startTime, $endTime, 0);
            if (empty($messages)) {
                $text .= 'Es sind keine Ereignisse vorhanden.';
            } else {
                foreach ($messages as $message) {
                    $text .= $message['Value'] . "\n";
                }
            }
            //Send mail to defined recipients
            foreach ($recipients as $recipient) {
                if ($recipient->ArchiveProtocol && $recipient->ID != 0 && !empty($recipient->Address) && $recipient->Use) {
                    $mailSubject = $this->ReadPropertyString('ArchiveProtocolSubject') . ' ' . $designation;
                    SMTP_SendMailEx($recipient->ID, $recipient->Address, $mailSubject, $text);
                }
            }
        }
    }

    #################### Private

    /**
     * Sets the timer for the monthly journal dispatch.
     */
    private function SetTimerSendMonthlyProtocol(): void
    {
        $archiveRetentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        if ($archiveRetentionTime > 0) {
            //Set timer for monthly journal
            $instanceStatus = @IPS_GetInstance($this->InstanceID)['InstanceStatus'];
            if ($this->ReadPropertyInteger('Archive') != 0 && $instanceStatus == 102) {
                //Set timer to next date
                $timestamp = strtotime('next day noon');
                $now = time();
                $interval = ($timestamp - $now) * 1000;
                $this->SetTimerInterval('SendMonthlyProtocol', $interval);
            }
        }
        if ($archiveRetentionTime <= 0) {
            $this->SetTimerInterval('SendMonthlyProtocol', 0);
        }
    }
}