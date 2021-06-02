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
    private const LIBRARY_GUID = '{C241A156-76A1-0079-DDE2-16F73D96D90A}';
    private const MODULE_NAME = 'Alarmprotokoll';
    private const MODULE_PREFIX = 'AP';
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
        // Messages
        $this->RegisterPropertyBoolean('EnableAlarmMessages', true);
        $this->RegisterPropertyBoolean('EnableStateMessages', true);
        $this->RegisterPropertyBoolean('EnableEventMessages', true);
        $this->RegisterPropertyInteger('AlarmMessagesRetentionTime', 2);
        $this->RegisterPropertyInteger('AmountStateMessages', 8);
        $this->RegisterPropertyInteger('EventMessagesRetentionTime', 7);
        // Archive
        $this->RegisterPropertyInteger('Archive', 0);
        $this->RegisterPropertyBoolean('UseArchiving', false);
        $this->RegisterPropertyInteger('ArchiveRetentionTime', 90);
        // Protocols
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
        $this->RegisterTimer('CleanUpMessages', 0, 'AP_CleanUpMessages(' . $this->InstanceID . ');');
        $this->RegisterTimer('SendMonthlyProtocol', 0, 'AP_SendMonthlyProtocol(' . $this->InstanceID . ', true, 1);');
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

        $this->RenameMessages();
        $this->SetArchiveLogging($this->ReadPropertyBoolean('UseArchiving'));
        $this->SetCleanUpMessagesTimer();
        $this->SetTimerSendMonthlyProtocol();

        // Delete all references
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }

        // Delete all registrations
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if ($message == VM_UPDATE) {
                    $this->UnregisterMessage($senderID, VM_UPDATE);
                }
            }
        }

        // Validation
        if ($this->ValidateConfiguration()) {
            // Register references
            $this->SendDebug(__FUNCTION__, 'Referenzen und Nachrichten werden registriert.', 0);
            $propertyNames = [
                ['name' => 'Archive', 'use' => 'UseArchiving'],
                ['name' => 'MonthlyMailer', 'use' => 'UseMonthlyProtocol'],
                ['name' => 'ArchiveMailer', 'use' => 'UseArchiveProtocol']
            ];
            foreach ($propertyNames as $propertyName) {
                $id = $this->ReadPropertyInteger($propertyName['name']);
                if ($id != 0 && @IPS_ObjectExists($id)) {
                    if ($this->ReadPropertyBoolean($propertyName['use'])) {
                        $this->RegisterReference($id);
                    }
                }
            }
        }
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
        $form = [];

        #################### Elements

        ########## Functions

        ##### Functions panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Wartungsmodus',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'MaintenanceMode',
                    'caption' => 'Wartungsmodus'
                ]
            ]
        ];

        ########## Designation

        ##### Designation panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Bezeichnung',
            'items'   => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'Designation',
                    'caption' => 'Bezeichnung (z.B. Standortbezeichnung)',
                    'width'   => '600px'
                ]
            ]
        ];

        ########## Messages

        ##### Messages panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Meldungen',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableAlarmMessages',
                    'caption' => 'Alarmmeldungen'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'AlarmMessagesRetentionTime',
                    'caption' => 'Anzeigedauer',
                    'suffix'  => 'Tage'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableStateMessages',
                    'caption' => 'Zustandsmeldungen'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'AmountStateMessages',
                    'caption' => 'Anzeige',
                    'suffix'  => 'Meldungen'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'EnableEventMessages',
                    'caption' => 'Ereignismeldungen'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'EventMessagesRetentionTime',
                    'caption' => 'Anzeigedauer',
                    'suffix'  => 'Tage'
                ],
            ]
        ];

        ########## Archive

        $id = $this->ReadPropertyInteger('Archive');
        $enabled = false;
        if ($id != 0 && @IPS_ObjectExists($id)) {
            $enabled = true;
        }

        ##### Archive panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Archivierung',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'UseArchiving',
                    'caption' => 'Archivierung'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'Archive',
                            'caption'  => 'Archiv',
                            'moduleID' => self::ARCHIVE_MODULE_GUID
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ',
                            'visible' => $enabled
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $id . ' konfigurieren',
                            'visible'  => $enabled,
                            'objectID' => $id
                        ]
                    ]
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'ArchiveRetentionTime',
                    'caption' => 'Datenspeicherung',
                    'minimum' => 7,
                    'suffix'  => 'Tage'
                ]
            ]
        ];

        ########## Protocols

        $monthlyMailer = $this->ReadPropertyInteger('MonthlyMailer');
        $monthlyMailerVisibility = false;
        if ($monthlyMailer != 0 && @IPS_ObjectExists($monthlyMailer)) {
            $monthlyMailerVisibility = true;
        }

        $archiveMailer = $this->ReadPropertyInteger('ArchiveMailer');
        $archiveMailerVisibility = false;
        if ($archiveMailer != 0 && @IPS_ObjectExists($archiveMailer)) {
            $archiveMailerVisibility = true;
        }

        ##### Protocols panel

        $form['elements'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Protokolle',
            'items'   => [
                [
                    'type'    => 'CheckBox',
                    'name'    => 'UseMonthlyProtocol',
                    'caption' => 'Monatsprotokoll'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'MonthlyProtocolSubject',
                    'caption' => 'Betreff'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'MonthlyMailer',
                            'caption'  => 'Mailer (E-Mail)',
                            'moduleID' => self::MAILER_MODULE_GUID
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ',
                            'visible' => $monthlyMailerVisibility
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $monthlyMailer . ' konfigurieren',
                            'visible'  => $monthlyMailerVisibility,
                            'objectID' => $monthlyMailer
                        ]
                    ]
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'CheckBox',
                    'name'    => 'UseArchiveProtocol',
                    'caption' => 'Archivprotokoll'
                ],
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'ArchiveProtocolSubject',
                    'caption' => 'Betreff'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'     => 'SelectModule',
                            'name'     => 'ArchiveMailer',
                            'caption'  => 'Mailer (E-Mail)',
                            'moduleID' => self::MAILER_MODULE_GUID
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' ',
                            'visible' => $archiveMailerVisibility
                        ],
                        [
                            'type'     => 'OpenObjectButton',
                            'caption'  => 'ID ' . $archiveMailer . ' konfigurieren',
                            'visible'  => $archiveMailerVisibility,
                            'objectID' => $archiveMailer
                        ]
                    ]
                ]
            ]
        ];

        #################### Actions

        ##### Configuration panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Konfiguration',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Neu einlesen',
                    'onClick' => self::MODULE_PREFIX . '_ReloadConfiguration($id);'
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectCategory',
                            'name'    => 'BackupCategory',
                            'caption' => 'Kategorie',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'Button',
                            'caption' => 'Sichern',
                            'onClick' => self::MODULE_PREFIX . '_CreateBackup($id, $BackupCategory);'
                        ]
                    ]
                ],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'    => 'SelectScript',
                            'name'    => 'ConfigurationScript',
                            'caption' => 'Konfiguration',
                            'width'   => '600px'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => ' '
                        ],
                        [
                            'type'    => 'PopupButton',
                            'caption' => 'Wiederherstellen',
                            'popup'   => [
                                'caption' => 'Konfiguration wirklich wiederherstellen?',
                                'items'   => [
                                    [
                                        'type'    => 'Button',
                                        'caption' => 'Wiederherstellen',
                                        'onClick' => self::MODULE_PREFIX . '_RestoreConfiguration($id, $ConfigurationScript);'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        ##### Archiving panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Archivierung',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Status anzeigen',
                    'onClick' => self::MODULE_PREFIX . '_ShowArchiveLoggingState($id);'
                ]
            ]
        ];

        ##### Messages panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Meldungen',
            'items'   => [
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Alle Meldungen löschen',
                    'popup'   => [
                        'caption' => 'Wirklich alle Meldungen der Anzeige löschen?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Löschen',
                                'onClick' => self::MODULE_PREFIX . '_DeleteAllMessages($id);'
                            ]
                        ]
                    ]
                ],
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Alarmmeldungen löschen',
                    'popup'   => [
                        'caption' => 'Wirklich alle Alarmmeldungen der Anzeige löschen?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Löschen',
                                'onClick' => self::MODULE_PREFIX . '_DeleteAlarmMessages($id);'
                            ]
                        ]
                    ]
                ],
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Zustandsmeldungen löschen',
                    'popup'   => [
                        'caption' => 'Wirklich alle Zustandsmeldungen der Anzeige löschen?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Löschen',
                                'onClick' => self::MODULE_PREFIX . '_DeleteStateMessages($id);'
                            ]
                        ]
                    ]
                ],
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Ereignismeldungen löschen',
                    'popup'   => [
                        'caption' => 'Wirklich alle Ereignismeldungen der Anzeige löschen?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Löschen',
                                'onClick' => self::MODULE_PREFIX . '_DeleteEventMessages($id);'
                            ]
                        ]
                    ]
                ],
                [
                    'type'    => 'PopupButton',
                    'caption' => 'Daten bereinigen',
                    'popup'   => [
                        'caption' => 'Wirklich alle Daten bereinigen?',
                        'items'   => [
                            [
                                'type'    => 'Button',
                                'caption' => 'Bereinigen',
                                'onClick' => self::MODULE_PREFIX . '_CleanUpMessages($id);'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        ##### Protocol panel

        $form['actions'][] = [
            'type'    => 'ExpansionPanel',
            'caption' => 'Protokolle',
            'items'   => [
                [
                    'type'    => 'Button',
                    'caption' => 'Vormonat versenden',
                    'onClick' => self::MODULE_PREFIX . '_SendMonthlyProtocol($id, false, 1);'
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Aktueller Monat versenden',
                    'onClick' => self::MODULE_PREFIX . '_SendMonthlyProtocol($id, false, 0);'
                ],
                [
                    'type'    => 'Button',
                    'caption' => 'Archivprotokoll versenden',
                    'onClick' => self::MODULE_PREFIX . '_SendArchiveProtocol($id);'
                ]
            ]
        ];

        #################### Status

        $library = IPS_GetLibrary(self::LIBRARY_GUID);
        $version = '[Version ' . $library['Version'] . '-' . $library['Build'] . ' vom ' . date('d.m.Y', $library['Date']) . ']';

        $form['status'] = [
            [
                'code'    => 101,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird erstellt',
            ],
            [
                'code'    => 102,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' ist aktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 103,
                'icon'    => 'active',
                'caption' => self::MODULE_NAME . ' wird gelöscht (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 104,
                'icon'    => 'inactive',
                'caption' => self::MODULE_NAME . ' ist inaktiv (ID ' . $this->InstanceID . ') ' . $version,
            ],
            [
                'code'    => 200,
                'icon'    => 'inactive',
                'caption' => 'Es ist Fehler aufgetreten, weitere Informationen unter Meldungen, im Log oder Debug! (ID ' . $this->InstanceID . ') ' . $version
            ]
        ];
        return json_encode($form);
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

    private function ValidateConfiguration(): bool
    {
        $result = true;
        $status = 102;
        // Maintenance mode
        $maintenance = $this->CheckMaintenanceMode();
        if ($maintenance) {
            $result = false;
            $status = 104;
        }
        IPS_SetDisabled($this->InstanceID, $maintenance);
        $this->SetStatus($status);
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