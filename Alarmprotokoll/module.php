<?php

/** @noinspection DuplicatedCode */
/** @noinspection PhpUnused */

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
 * @see         https://github.com/ubittner/Alarmprotokoll
 *
 * @guids       Library
 *              {C241A156-76A1-0079-DDE2-16F73D96D90A}
 *
 *              Alarmprotokoll
 *             	{BC752980-2D17-67B6-9B91-0B4113EECD83}
 */

declare(strict_types=1);

// Include
include_once __DIR__ . '/helper/autoload.php';

class Alarmprotokoll extends IPSModule
{
    // Helper
    use APRO_archive;
    use APRO_backupRestore;
    use APRO_messages;
    use APRO_protocol;

    // Constants
    private const ALARMPROTOKOLL_LIBRARY_GUID = '{C241A156-76A1-0079-DDE2-16F73D96D90A}';
    private const ALARMPROTOKOLL_MODULE_GUID = '{BC752980-2D17-67B6-9B91-0B4113EECD83}';
    private const ARCHIVE_MODULE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const SMTP_MODULE_GUID = '{375EAF21-35EF-4BC4-83B3-C780FD8BD88A}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->RegisterProperties();
        $this->RegisterVariables();
        $this->RegisterTimers();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
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
        $this->SetArchiveLogging($this->ReadPropertyBoolean('UseArchiving'));
        $this->RenameMessages();
        $this->SetTimerResetAlarmMessages();
        $this->SetTimerSendMonthlyProtocol();
        $this->SetTimerCleanUpArchiveData();
        if ($this->CheckMaintenanceMode()) {
            return;
        }
        $this->ValidateConfiguration();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->SendDebug(__FUNCTION__, $TimeStamp . ', SenderID: ' . $SenderID . ', Message: ' . $Message . ', Data: ' . print_r($Data, true), 0);
        if (!empty($Data)) {
            foreach ($Data as $key => $value) {
                $this->SendDebug(__FUNCTION__, 'Data[' . $key . '] = ' . json_encode($value), 0);
            }
        }
        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;

        }
    }

    public function GetConfigurationForm()
    {
        $formData = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $moduleInfo = [];
        $library = IPS_GetLibrary(self::ALARMPROTOKOLL_LIBRARY_GUID);
        $module = IPS_GetModule(self::ALARMPROTOKOLL_MODULE_GUID);
        $moduleInfo['name'] = $module['ModuleName'];
        $moduleInfo['version'] = $library['Version'] . '-' . $library['Build'];
        $moduleInfo['date'] = date('d.m.Y', $library['Date']);
        $moduleInfo['time'] = date('H:i', $library['Date']);
        $moduleInfo['developer'] = $library['Author'];
        $formData['elements'][0]['items'][2]['caption'] = "Instanz ID:\t\t" . $this->InstanceID;
        $formData['elements'][0]['items'][3]['caption'] = "Modul:\t\t\t" . $moduleInfo['name'];
        $formData['elements'][0]['items'][4]['caption'] = "Version:\t\t\t" . $moduleInfo['version'];
        $formData['elements'][0]['items'][5]['caption'] = "Datum:\t\t\t" . $moduleInfo['date'];
        $formData['elements'][0]['items'][6]['caption'] = "Uhrzeit:\t\t\t" . $moduleInfo['time'];
        $formData['elements'][0]['items'][7]['caption'] = "Entwickler:\t\t" . $moduleInfo['developer'];
        $formData['elements'][0]['items'][8]['caption'] = "Präfix:\t\t\tAPRO";
        return json_encode($formData);
    }

    public function ReloadConfiguration()
    {
        $this->ReloadForm();
    }

    #################### Private

    private function KernelReady()
    {
        $this->ApplyChanges();
    }

    private function RegisterProperties(): void
    {
        $this->RegisterPropertyString('Note', '');
        $this->RegisterPropertyBoolean('MaintenanceMode', false);
        // Designation
        $this->RegisterPropertyString('Designation', '');
        // Archive
        $this->RegisterPropertyInteger('Archive', 0);
        $this->RegisterPropertyBoolean('UseArchiving', false);
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
        $this->RegisterVariableString('AlarmMessages', 'Alarmmeldung', '~TextBox', 10);
        $alarmMessages = $this->GetIDForIdent('AlarmMessages');
        IPS_SetIcon($alarmMessages, 'Warning');
        // State messages
        $this->RegisterVariableString('StateMessages', 'Zustandsmeldungen', '~TextBox', 20);
        $stateMessages = $this->GetIDForIdent('StateMessages');
        IPS_SetIcon($stateMessages, 'Power');
        // Event messages
        $this->RegisterVariableString('EventMessages', 'Ereignismeldungen', '~TextBox', 30);
        $eventMessages = $this->GetIDForIdent('EventMessages');
        IPS_SetIcon($eventMessages, 'Information');
        // Message archive
        $this->RegisterVariableString('MessageArchive', 'Archivdaten', '~TextBox', 40);
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

    private function CheckMaintenanceMode(): bool
    {
        $result = false;
        $status = 102;
        if ($this->ReadPropertyBoolean('MaintenanceMode')) {
            $result = true;
            $status = 104;
            $this->SendDebug(__FUNCTION__, 'Abbruch, der Wartungsmodus ist aktiv!', 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', Abbruch, der Wartungsmodus ist aktiv!', KL_WARNING);
        }
        $this->SetStatus($status);
        IPS_SetDisabled($this->InstanceID, $result);
        return $result;
    }
}