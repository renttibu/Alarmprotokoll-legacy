<?php

/** @noinspection PhpUnused */

/*
 * @module      Alarmprotokoll (20201120-1007)
 *
 * @prefix      AP
 *
 * @file        module.php
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

include_once __DIR__ . '/helper/autoload.php';

class Alarmprotokoll extends IPSModule
{
    //Helper
    use AP_archive;
    use AP_backupRestore;
    use AP_messages;
    use AP_protocol;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterVariables();
        $this->RegisterTimers();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Wait until IP-Symcon is started
        $this->RegisterMessage(0, IPS_KERNELSTARTED);
        //Never delete this line!
        parent::ApplyChanges();
        //Check runlevel
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetArchiveLogging($this->ReadPropertyBoolean('UseArchiving'));
        $this->RenameMessages();
        $this->SetTimerResetAlarmMessages();
        $this->SetTimerSendMonthlyProtocol();
        $this->SetTimerCleanUpArchiveData();
        $this->ValidateConfiguration();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Private

    private function KernelReady(): void
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        //Function
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        //Designation
        $this->RegisterPropertyString('Designation', '');
        //Archive
        $this->RegisterPropertyInteger('Archive', 0);
        $this->RegisterPropertyBoolean('UseArchiving', false);
        $this->RegisterPropertyInteger('ArchiveRetentionTime', 90);
        //Messages
        $this->RegisterPropertyBoolean('EnableAlarmMessages', true);
        $this->RegisterPropertyBoolean('EnableStateMessages', true);
        $this->RegisterPropertyBoolean('EnableEventMessages', true);
        $this->RegisterPropertyInteger('AlarmMessagesRetentionTime', 2);
        $this->RegisterPropertyInteger('AmountStateMessages', 8);
        $this->RegisterPropertyInteger('EventMessagesRetentionTime', 7);
        //E-Mail dispatch
        $this->RegisterPropertyString('MonthlyProtocolSubject', 'Monatsprotokoll');
        $this->RegisterPropertyString('ArchiveProtocolSubject', 'Archivprotokoll');
        $this->RegisterPropertyString('Recipients', '[]');
    }

    private function RegisterVariables(): void
    {
        //Alarm messages
        $this->RegisterVariableString('AlarmMessages', 'Alarmmeldung', '~TextBox', 10);
        $alarmMessages = $this->GetIDForIdent('AlarmMessages');
        IPS_SetIcon($alarmMessages, 'Warning');
        //State messages
        $this->RegisterVariableString('StateMessages', 'Zustandsmeldungen', '~TextBox', 20);
        $stateMessages = $this->GetIDForIdent('StateMessages');
        IPS_SetIcon($stateMessages, 'Power');
        //Event messages
        $this->RegisterVariableString('EventMessages', 'Ereignismeldungen', '~TextBox', 30);
        $eventMessages = $this->GetIDForIdent('EventMessages');
        IPS_SetIcon($eventMessages, 'Information');
        //Message archive
        $this->RegisterVariableString('MessageArchive', 'Archivdaten', '~TextBox', 40);
        $messageArchive = $this->GetIDForIdent('MessageArchive');
        IPS_SetHidden($messageArchive, true);
    }

    private function RegisterTimers(): void
    {
        $this->RegisterTimer('ResetAlarmMessages', 0, 'AP_ResetAlarmMessages(' . $this->InstanceID . ');');
        $this->RegisterTimer('SendMonthlyProtocol', 0, 'AP_SendMonthlyProtocol(' . $this->InstanceID . ', true, 1);');
        $this->RegisterTimer('CleanUpArchiveData', 0, 'AP_CleanUpArchiveData(' . $this->InstanceID . ');');
    }

    private function RenameMessages(): void
    {
        //Rename alarm messages
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
        //Rename state messages
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
        //Rename message archive
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

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        //Archive
        $id = $this->ReadPropertyInteger('Archive');
        if (@!IPS_ObjectExists($id)) {
            $status = 200;
            $text = 'Bitte das ausgewählte Archiv überprüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        //E-Mail recipients
        $recipients = json_decode($this->ReadPropertyString('Recipients'));
        if (!empty($recipients)) {
            foreach ($recipients as $recipient) {
                if ($recipient->Use) {
                    $id = $recipient->ID;
                    if ($id == 0 || !@IPS_ObjectExists($id)) {
                        $status = 200;
                        $text = 'Bitte die zugewiesene SMTP Instanz überprüfen!';
                        $this->SendDebug(__FUNCTION__, $text, 0);
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
                    }
                    $address = $recipient->Address;
                    if (empty($address) || strlen($address) < 3) {
                        $status = 200;
                        $text = 'Bitte die zugewiesene E-Mail Adresse überprüfen!';
                        $this->SendDebug(__FUNCTION__, $text, 0);
                        $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
                    }
                }
            }
        }
        //Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
        if ($status != 102) {
            $result = false;
        }
        return $result;
    }

    private function CheckMaintenanceMode(): bool
    {
        $result = $this->ReadPropertyBoolean('MaintenanceMode');
        if ($result) {
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        return $result;
    }
}