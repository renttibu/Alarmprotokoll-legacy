<?php

/*
 * @module      Alarmprotokoll
 *
 * @prefix      APRO
 *
 * @file        module.php
 *
 * @author      Ulrich Bittner
 * @copyright   (c) 2020
 * @license    	CC BY-NC-SA 4.0
 *              https://creativecommons.org/licenses/by-nc-sa/4.0/
 *
 * @version     4.00-1
 * @date        2020-01-29, 18:00, 1580317200
 * @review      2020-01-29, 18:00
 *
 * @see         https://github.com/ubittner/Alarmprotokoll/
 *
 * @guids       Library
 *              {C241A156-76A1-0079-DDE2-16F73D96D90A}
 *
 *              Alarmprotokoll
 *             	{BC752980-2D17-67B6-9B91-0B4113EECD83}
 */

// Declare
declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Alarmprotokoll extends IPSModule
{
    // Helper
    use APRO_archive;
    use APRO_messages;
    use APRO_protocol;

    // Constants
    private const ARCHIVE_MODULE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const SMTP_MODULE_GUID = '{375EAF21-35EF-4BC4-83B3-C780FD8BD88A}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Register properties
        $this->RegisterProperties();

        // Register variables
        $this->RegisterVariables();

        // Register timers
        $this->RegisterTimers();
    }

    public function ApplyChanges()
    {
        // Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        // Never delete this line!
        parent::ApplyChanges();

        // Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        // Rename messages
        $this->RenameMessages();

        // Set timers
        $this->SetTimerResetAlarmMessages();
        $this->SetTimerSendMonthlyProtocol();
        $this->SetTimerCleanUpArchiveData();

        // Validate configuration
        $this->ValidateConfiguration();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        // Send debug
        $this->SendDebug('MessageSink', 'Message from SenderID ' . $SenderID . ' with Message ' . $Message . "\r\n Data: " . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    protected function KernelReady()
    {
        $this->ApplyChanges();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
    }

    //#################### Private

    private function RegisterProperties(): void
    {
        // Designation
        $this->RegisterPropertyString('Designation', '');

        // Archive
        $this->RegisterPropertyInteger('Archive', 0);
        $this->RegisterPropertyInteger('ArchiveRetentionTime', 90);

        // Messages
        $this->RegisterPropertyBoolean('EnableAlarmMessages', true);
        $this->RegisterPropertyBoolean('EnableStateMessages', true);
        $this->RegisterPropertyBoolean('EnableEventMessages', true);
        $this->RegisterPropertyInteger('AlarmMessagesRetentionTime', 2);
        $this->RegisterPropertyInteger('AmountStateMessages', 8);
        $this->RegisterPropertyInteger('EventMessagesRetentionTime', 7);

        // E-Mail dispatch
        $this->RegisterPropertyString('MonthlyProtocolSubject', 'Monatsprotokoll');
        $this->RegisterPropertyString('ArchiveProtocolSubject', 'Archivprotokoll');
        $this->RegisterPropertyString('Recipients', '[]');
    }

    private function RegisterVariables(): void
    {
        // Alarm messages
        $this->RegisterVariableString('AlarmMessages', 'Alarmmeldung', '~TextBox', 1);
        $alarmMessages = $this->GetIDForIdent('AlarmMessages');
        IPS_SetIcon($alarmMessages, 'Warning');

        // State messages
        $this->RegisterVariableString('StateMessages', 'Zustandsmeldungen', '~TextBox', 2);
        $stateMessages = $this->GetIDForIdent('StateMessages');
        IPS_SetIcon($stateMessages, 'Power');

        // Event messages
        $this->RegisterVariableString('EventMessages', 'Ereignismeldungen', '~TextBox', 3);
        $eventMessages = $this->GetIDForIdent('EventMessages');
        IPS_SetIcon($eventMessages, 'Information');

        // Message archive
        $this->RegisterVariableString('MessageArchive', 'Archivdaten', '~TextBox', 4);
        $messageArchive = $this->GetIDForIdent('MessageArchive');
        IPS_SetHidden($messageArchive, true);
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('ResetAlarmMessages', 0, 'APRO_ResetAlarmMessages(' . $this->InstanceID . ');');
        $this->RegisterTimer('SendMonthlyProtocol', 0, 'APRO_SendMonthlyProtocol(' . $this->InstanceID . ', true, 1);');
        $this->RegisterTimer('CleanUpArchiveData', 0, 'APRO_CleanUpArchiveData(' . $this->InstanceID . ');');
    }

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
        $use = $this->ReadPropertyBoolean('EnableAlarmMessages');
        IPS_SetHidden($id, !$use);

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
        $use = $this->ReadPropertyBoolean('EnableStateMessages');
        IPS_SetHidden($id, !$use);

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
        $use = $this->ReadPropertyBoolean('EnableEventMessages');
        IPS_SetHidden($id, !$use);

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

    private function ValidateConfiguration(): void
    {
        $state = 102;
        // Archive
        $id = $this->ReadPropertyInteger('Archive');
        if ($id != 0) {
            if (!@IPS_ObjectExists($id)) {
                $this->LogMessage('Instanzkonfiguration, Archivierung, Archiv ID ungültig!', KL_ERROR);
                $state = 200;
            } else {
                $instance = IPS_GetInstance($id);
                $moduleID = $instance['ModuleInfo']['ModuleID'];
                if ($moduleID !== self::ARCHIVE_MODULE_GUID) {
                    $this->LogMessage('Instanzkonfiguration, Archivierung, Archiv GUID ungültig!', KL_ERROR);
                    $state = 200;
                }
            }
        }

        // E-Mail recipients
        $recipients = json_decode($this->ReadPropertyString('Recipients'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                if ($recipient->Use) {
                    $id = $recipient->ID;
                    if ($id != 0) {
                        if (!@IPS_ObjectExists($id)) {
                            $this->LogMessage('Instanzkonfiguration, E-Mail Versand, SMTP ID ungültig!', KL_ERROR);
                            $state = 200;
                        } else {
                            $instance = IPS_GetInstance($id);
                            $moduleID = $instance['ModuleInfo']['ModuleID'];
                            if ($moduleID !== self::SMTP_MODULE_GUID) {
                                $this->LogMessage('Instanzkonfiguration, E-Mail Versand, SMTP GUID ungültig!', KL_ERROR);
                                $state = 200;
                            }
                        }
                    } else {
                        $this->LogMessage('Instanzkonfiguration, E-Mail Versand, Keine SMTP Instanz ausgewählt!', KL_ERROR);
                        $state = 200;
                    }
                    $address = $recipient->Address;
                    if (empty($address) || strlen($address) < 3) {
                        $this->LogMessage('Instanzkonfiguration,, E-Mail Versand, Empfängeradresse zu kurz!', KL_ERROR);
                        $state = 200;
                    }
                }
            }
        }

        // Set state
        $this->SetStatus($state);
    }
}