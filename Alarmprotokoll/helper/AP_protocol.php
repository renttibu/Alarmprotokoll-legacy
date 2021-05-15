<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmprotokoll/tree/master/Alarmprotokoll
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

trait AP_protocol
{
    public function SendMonthlyProtocol(bool $CheckDay, int $ProtocolPeriod): void
    {
        /*
         * $CheckDay
         * false    = don't check day
         * true     = check day
         *
         * $ProtocolPeriod
         * 0        = actual month
         * 1        = last month
         */
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if (!$this->ReadPropertyBoolean('UseMonthlyProtocol')) {
            return;
        }
        $mailer = $this->ReadPropertyInteger('MonthlyMailer');
        if ($mailer != 0 && @IPS_ObjectExists($mailer)) {
            // Check if it is the first day of the month
            $day = date('j');
            if ($day == '1' || !$CheckDay) {
                // Prepare data
                $archive = $this->ReadPropertyInteger('Archive');
                if ($archive != 0) {
                    // This month
                    $startTime = strtotime('first day of this month midnight');
                    $endTime = strtotime('first day of next month midnight') - 1;
                    // Last month
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
                    // Send mail
                    $mailSubject = $this->ReadPropertyString('MonthlyProtocolSubject') . ' ' . $monthName[$month] . ' ' . $year . ', ' . $designation;
                    $scriptText = 'MA_SendMessage(' . $mailer . ', "' . $mailSubject . '", "' . $text . '");';
                    IPS_RunScriptText($scriptText);
                }
            }
        }
        $this->SetTimerSendMonthlyProtocol();
    }

    public function SendArchiveProtocol(): void
    {
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        if (!$this->ReadPropertyBoolean('UseArchiveProtocol')) {
            return;
        }
        $mailer = $this->ReadPropertyInteger('ArchiveMailer');
        if ($mailer != 0 && @IPS_ObjectExists($mailer)) {
            // Prepare data
            // Set start time to 2000-01-01 12:00 am
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
            // Send mail
            $mailSubject = $this->ReadPropertyString('ArchiveProtocolSubject') . ' ' . $designation;
            $scriptText = 'MA_SendMessage(' . $mailer . ', "' . $mailSubject . '", "' . $text . '");';
            IPS_RunScriptText($scriptText);
        }
    }

    #################### Private

    private function SetTimerSendMonthlyProtocol(): void
    {
        $archiveRetentionTime = $this->ReadPropertyInteger('ArchiveRetentionTime');
        if ($archiveRetentionTime > 0) {
            // Set timer for monthly journal
            $instanceStatus = @IPS_GetInstance($this->InstanceID)['InstanceStatus'];
            if ($this->ReadPropertyInteger('Archive') != 0 && $instanceStatus == 102) {
                // Set timer to next date
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