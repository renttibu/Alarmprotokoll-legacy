<?php

/*
 * @author      Ulrich Bittner
 * @copyright   (c) 2020, 2021
 * @license    	CC BY-NC-SA 4.0
 * @see         https://github.com/ubittner/Alarmprotokoll/tree/master/Alarmprotokoll
 */

/** @noinspection PhpUnused */

declare(strict_types=1);

include_once __DIR__ . '/helper/autoload.php';

class Alarmprotokoll extends IPSModule
{
    // Helper
    use AP_archive;
    use AP_backupRestore;
    use AP_messages;
    use AP_protocol;

    // Constants
    private const ARCHIVE_MODULE_GUID = '{43192F0B-135B-4CE7-A0A7-1475603F3060}';
    private const MAILER_MODULE_GUID = '{E43C3C36-8402-6B6D-2699-D870FBC216EF}';

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        // Properties
        // Function
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
        // E-Mail
        $this->RegisterPropertyBoolean('UseMonthlyProtocol', true);
        $this->RegisterPropertyString('MonthlyProtocolSubject', 'Monatsprotokoll');
        $this->RegisterPropertyInteger('MonthlyMailer', 0);
        $this->RegisterPropertyBoolean('UseArchiveProtocol', true);
        $this->RegisterPropertyString('ArchiveProtocolSubject', 'Archivprotokoll');
        $this->RegisterPropertyInteger('ArchiveMailer', 0);

        // Variables
        // Alarm messages
        $id = @$this->GetIDForIdent('AlarmMessages');
        $this->RegisterVariableString('AlarmMessages', 'Alarmmeldung', '~TextBox', 10);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('AlarmMessages'), 'Warning');
        }
        // State messages
        $id = @$this->GetIDForIdent('StateMessages');
        $this->RegisterVariableString('StateMessages', 'Zustandsmeldungen', '~TextBox', 20);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('StateMessages'), 'Power');
        }
        // Event messages
        $id = @$this->GetIDForIdent('EventMessages');
        $this->RegisterVariableString('EventMessages', 'Ereignismeldungen', '~TextBox', 30);
        if ($id == false) {
            IPS_SetIcon($this->GetIDForIdent('EventMessages'), 'Information');
        }

        // Message archive
        $id = @$this->GetIDForIdent('MessageArchive');
        $this->RegisterVariableString('MessageArchive', 'Archivdaten', '~TextBox', 40);
        if ($id == false) {
            IPS_SetHidden($this->GetIDForIdent('MessageArchive'), true);
        }

        // Timers
        $this->RegisterTimer('ResetAlarmMessages', 0, 'AP_ResetAlarmMessages(' . $this->InstanceID . ');');
        $this->RegisterTimer('SendMonthlyProtocol', 0, 'AP_SendMonthlyProtocol(' . $this->InstanceID . ', true, 1);');
        $this->RegisterTimer('CleanUpArchiveData', 0, 'AP_CleanUpArchiveData(' . $this->InstanceID . ');');
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
        $this->ValidateConfiguration();
    }

    public function Destroy()
    {
        // Never delete this line!
        parent::Destroy();
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
        // Archive
        $id = $this->ReadPropertyInteger('Archive');
        $enabled = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $enabled = true;
        }
        $formData['elements'][2]['items'][0] = [
            'type'    => 'CheckBox',
            'name'    => 'UseArchiving',
            'caption' => 'Archivierung'
        ];
        $formData['elements'][2]['items'][1] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][2]['items'][1]['items'][0] = [
                    'type'       => 'SelectModule',
                    'name'       => 'Archive',
                    'caption'    => 'Archiv',
                    'moduleID'   => self::ARCHIVE_MODULE_GUID
                ],
                $formData['elements'][2]['items'][1]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $enabled
                ],
                $formData['elements'][2]['items'][1]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'ID ' . $id . ' konfigurieren',
                    'visible'  => $enabled,
                    'objectID' => $id
                ]
            ]
        ];
        $formData['elements'][2]['items'][2] = [
            'type'    => 'NumberSpinner',
            'name'    => 'ArchiveRetentionTime',
            'caption' => 'Datenspeicherung',
            'minimum' => 7,
            'suffix'  => 'Tage'
        ];
        // Protocols
        $formData['elements'][3]['items'][0] = [
            'type'     => 'CheckBox',
            'name'     => 'UseMonthlyProtocol',
            'caption'  => 'Monatsprotokoll'
        ];
        $formData['elements'][3]['items'][1] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'MonthlyProtocolSubject',
            'caption' => 'Betreff'
        ];
        $id = $this->ReadPropertyInteger('MonthlyMailer');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][2] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][2]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'MonthlyMailer',
                    'caption'  => 'Mailer (E-Mail)',
                    'moduleID' => self::MAILER_MODULE_GUID
                ],
                $formData['elements'][3]['items'][2]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][2]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'ID ' . $id . ' konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
            ]
        ];
        $formData['elements'][3]['items'][3] = [
            'type'    => 'Label',
            'caption' => ' '
        ];
        $formData['elements'][3]['items'][4] = [
            'type'     => 'CheckBox',
            'name'     => 'UseArchiveProtocol',
            'caption'  => 'Archivprotokoll'
        ];
        $formData['elements'][3]['items'][5] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'ArchiveProtocolSubject',
            'caption' => 'Betreff'
        ];
        $id = $this->ReadPropertyInteger('ArchiveMailer');
        $visibility = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $visibility = true;
        }
        $formData['elements'][3]['items'][6] = [
            'type'  => 'RowLayout',
            'items' => [
                $formData['elements'][3]['items'][6]['items'][0] = [
                    'type'     => 'SelectModule',
                    'name'     => 'ArchiveMailer',
                    'caption'  => 'Mailer (E-Mail)',
                    'moduleID' => self::MAILER_MODULE_GUID
                ],
                $formData['elements'][3]['items'][6]['items'][1] = [
                    'type'    => 'Label',
                    'caption' => ' ',
                    'visible' => $visibility
                ],
                $formData['elements'][3]['items'][6]['items'][2] = [
                    'type'     => 'OpenObjectButton',
                    'caption'  => 'ID ' . $id . ' konfigurieren',
                    'visible'  => $visibility,
                    'objectID' => $id
                ],
            ]
        ];
        // Status
        $formData['status'][0] = [
            'code'    => 101,
            'icon'    => 'active',
            'caption' => 'Alarmprotokoll wird erstellt',
        ];
        $formData['status'][1] = [
            'code'    => 102,
            'icon'    => 'active',
            'caption' => 'Alarmprotokoll ist aktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][2] = [
            'code'    => 103,
            'icon'    => 'active',
            'caption' => 'Alarmprotokoll wird gelöscht (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][3] = [
            'code'    => 104,
            'icon'    => 'inactive',
            'caption' => 'Alarmprotokoll ist inaktiv (ID ' . $this->InstanceID . ')',
        ];
        $formData['status'][4] = [
            'code'    => 200,
            'icon'    => 'inactive',
            'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ')',
        ];
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

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Archive
        $id = $this->ReadPropertyInteger('Archive');
        if (@!IPS_ObjectExists($id)) {
            $status = 200;
            $text = 'Bitte die Archiv Konfiguration prüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        // E-Mail
        $id = $this->ReadPropertyInteger('MonthlyMailer');
        if (@!IPS_ObjectExists($id)) {
            $status = 200;
            $text = 'Bitte die E-Mail Konfiguration prüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        $id = $this->ReadPropertyInteger('ArchiveMailer');
        if (@!IPS_ObjectExists($id)) {
            $status = 200;
            $text = 'Bitte die E-Mail Konfiguration prüfen!';
            $this->SendDebug(__FUNCTION__, $text, 0);
            $this->LogMessage('ID ' . $this->InstanceID . ', ' . __FUNCTION__ . ', ' . $text, KL_WARNING);
        }
        // Maintenance mode
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