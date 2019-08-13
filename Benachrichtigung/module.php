<?

    class Benachrichtigung extends IPSModule {

        const SCRIPT_ACTION = 0;
        const PUSH_NOTIFICATION_ACTION = 1;
        const IRIS_ACTION = 2;
        const EMAIL_ACTION = 3;
        const SMS_ACTION = 4;

        public function Create()
        {
            //Never delete this line!
            parent::Create();

            $this->RegisterPropertyInteger("InputTriggerID", 0);
            $this->RegisterPropertyString("NotificationLevels", "[]");
            $this->RegisterVariableInteger("NotificationLevel", $this->Translate("Notification Level"), "");
            $this->RegisterVariableBoolean("Active", $this->Translate("Notifications active"), "~Switch");
            $this->RegisterScript("ResetScript", $this->Translate("Reset"), "<? BN_Reset(IPS_GetParent(\$_IPS['SELF']));");
            $this->RegisterTimer("IncreaseTimer", 0, 'BN_IncreaseLevel($_IPS[\'TARGET\']);');

            $this->EnableAction("Active");
        }

        public function ApplyChanges() {
            //Never delete this line!
            parent::ApplyChanges();

            $triggerID = $this->ReadPropertyInteger("InputTriggerID");

            $this->RegisterMessage($triggerID, VM_UPDATE);
            if (method_exists($this, 'GetReferenceList')) {
                $refs = $this->GetReferenceList();
                foreach ($refs as $ref) {
                    $this->UnregisterReference($ref);
                }
    
                $inputTriggerID = $this->ReadPropertyInteger("InputTriggerID");
                if ($inputTriggerID) {
                    $this->RegisterReference($inputTriggerID);
                }

                $notificationLevels = json_decode($this->ReadPropertyString("NotificationLevels"), true);

                foreach($notificationLevels as $notificationLevel) {
                    foreach($notificationLevel['actions'] as $action) {
                        if ($action['recipientObjectID']) {
                            $this->RegisterReference($action['recipientObjectID']);
                        }
                    }
                }
            }
        }

        public function MessageSink ($TimeStamp, $SenderID, $Message, $Data) {
            $triggerID = $this->ReadPropertyInteger("InputTriggerID");
            if (($SenderID == $triggerID) && ($Message == VM_UPDATE) && (boolval($Data[0])) && (GetValue($this->GetIDForIdent('NotificationLevel')) == 0)) {
                $this->SetNotifyLevel(1);
            }
        }

        public function GetConfigurationForm() {
            $notificationValues = [];
            $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);
            for ($i = 1; $i <= sizeof($levelTable); $i++) {
                $actionValues = [];
                foreach ($levelTable[$i - 1]['actions'] as $action) {
                    $actionValues[] = [ 'status' => $this->GetActionStatus($action) ];
                }
                $notificationValues[] = [
                    'level' => strval($i),
                    'status' => $this->GetLevelStatus($i),
                    'actions' => $actionValues
                ];
            }

            $actionTypeOptions = [
                [
                    'caption' => 'Script',
                    'value' => self::SCRIPT_ACTION
                ],
                [
                    'caption' => 'Push',
                    'value' => self::PUSH_NOTIFICATION_ACTION
                ],
                [
                    'caption' => 'E-Mail (SMTP)',
                    'value' => self::EMAIL_ACTION
                ],
                [
                    'caption' => 'SMS',
                    'value' => self::SMS_ACTION
                ]
            ];

            // Is IRiS installed?
            if (IPS_LibraryExists("{077A9478-72B4-484B-9F79-E5EA9088C52E}")) {
                $actionTypeOptions[] = [
                    'caption' => 'IRiS',
                    'value' => self::IRIS_ACTION
                ];
            }

            $form = [
                'elements' => [
                    [
                        'type' => 'SelectVariable',
                        'name' => 'InputTriggerID',
                        'caption' => 'Trigger'
                    ],
                    [
                        'type' => 'List',
                        'name' => 'NotificationLevels',
                        'caption' => 'Notification Levels',
                        'rowCount' => 8,
                        'add' => true,
                        'delete' => true,
                        'columns' => [
                            [
                                'name' => 'level',
                                'caption' => 'Level',
                                'width' => '75px',
                                'add' => ''
                            ],
                            [
                                'name' => 'duration',
                                'caption' => 'Duration',
                                'width' => '120px',
                                'add' => 60,
                                'edit' => [
                                    'type' => 'NumberSpinner',
                                    'suffix' => ' seconds'
                                ]
                            ],
                            [
                                'name' => 'actions',
                                'caption' => 'Actions',
                                'width' => '120px',
                                'add' => [],
                                'edit' => [
                                    'type' => 'List',
                                    'rowCount' => 5,
                                    'add' => true,
                                    'delete' => true,
                                    'columns' => [
                                        [
                                            'name' => 'actionType',
                                            'label' => 'Action',
                                            'width' => '150px',
                                            'add' => 0,
                                            'edit' => [
                                                'type' => 'Select',
                                                'options' => $actionTypeOptions
                                            ]
                                        ],
                                        [
                                            'name' => 'recipientObjectID',
                                            'label' => 'Recipient Object',
                                            'width' => '200px',
                                            'add' => 0,
                                            'edit' => [
                                                'type' => 'SelectObject'
                                            ]
                                        ],
                                        [
                                            'name' => 'recipientAddress',
                                            'label' => 'Recipient Address',
                                            'width' => '200px',
                                            'add' => '',
                                            'edit' => [
                                                'type' => 'ValidationTextBox'
                                            ]
                                        ],
                                        [
                                            'name' => 'title',
                                            'label' => 'Title',
                                            'width' => '100px',
                                            'add' => '',
                                            'edit' => [
                                                'type' => 'ValidationTextBox'
                                            ]
                                        ],
                                        [
                                            'name' => 'message',
                                            'label' => 'Message',
                                            'width' => '200px',
                                            'add' => '',
                                            'edit' => [
                                                'type' => 'ValidationTextBox'
                                            ]
                                        ],
                                        [
                                            'name' => 'messageVariable',
                                            'label' => 'Message Variable',
                                            'width' => '200px',
                                            'add' => 0,
                                            'edit' => [
                                                'type' => 'SelectVariable'
                                            ]
                                        ]/*, // TODO: How to show status for actions?
                                        [
                                            'name' => 'status',
                                            'label' => 'Status',
                                            'width' => '70px',
                                            'add' => ''
                                        ]*/
                                    ]
                                ]
                            ],
                            [
                                'name' => 'status',
                                'label' => 'Status',
                                'width' => '300px',
                                'add' => ''
                            ]
                        ],
                        'values' => $notificationValues
                    ]
                ]
            ];

            return json_encode($form);
        }

        public function RequestAction($Ident, $Value) {
            switch($Ident) {
                case "Active":
                    if (GetValue($this->GetIDForIdent('NotificationLevel')) != 0) {
                        echo $this->Translate('Cannot deactivate while there is an unconfirmed notification. Please reset the notification level first.');
                        return;
                    }
                    SetValue($this->GetIDForIdent('Active'), $Value);
                    break;

                default:
                    throw new Exception($this->Translate("Invalid ident"));
            }
        }

        public function SetNotifyLevel(int $Level) {
            if (!GetValue($this->GetIDForIdent('Active'))) {
                return;
            }

            SetValue($this->GetIDForIdent('NotificationLevel'), $Level);

            $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);

            if ($Level <= sizeof($levelTable)) {

                foreach ($levelTable[$Level - 1]['actions'] as $action) {
                    // Only send actions that are "OK"
                    if ($this->GetActionStatus($action) != $this->Translate("OK")) {
                        continue;
                    }

                    $message = $action['message'];
                    if ($action['messageVariable'] !== 0) {
                        $message .= strval(GetValue($action['messageVariable']));
                    }
                    switch ($action['actionType']) {
                        case self::SCRIPT_ACTION:
                            IPS_RunScriptEx($action['recipientObjectID'], ['RECIPIENT' => $action['recipientAddress'], 'TITLE' => $action['title'], 'MESSAGE' => $action['message'], 'MESSAGE_VARIABLE' => $action['messageVariable']]);
                            break;

                        case self::PUSH_NOTIFICATION_ACTION:
                            WFC_PushNotification($action['recipientObjectID'], $action['title'], $message, 'alarm', $this->GetIDForIdent('ResetScript'));
                            break;

                        case self::IRIS_ACTION:
                            IRIS_AddAlarm($action['recipientObjectID'], 'Fire');
                            break;

                        case self::EMAIL_ACTION:
                            if ($action['recipientAddress'] != '') {
                                SMTP_SendMailEx($action['recipientObjectID'], $action['recipientAddress'], $action['title'], $message);
                            }
                            else {
                                SMTP_SendMail($action['recipientObjectID'], $action['title'], $message);
                            }
                            break;

                        case self::SMS_ACTION:
                            SMS_Send($action['recipientObjectID'], $action['recipientAddress'], $action['title'] . ": " . $message);
                            break;
                    }
                }

                if ($Level < sizeof($levelTable)) {
                    $this->SetTimerInterval('IncreaseTimer', $levelTable[$Level - 1]['duration'] * 1000);
                }
                else {
                    $this->SetTimerInterval('IncreaseTimer', 0);
                }
            }
            else {
                throw new Exception($this->Translate('Selected Level is not defined'));
            }
        }

        public function IncreaseLevel() {
            $this->SetNotifyLevel(GetValue($this->GetIDForIdent('NotificationLevel')) + 1);
        }

        public function Reset() {
            $this->SetTimerInterval('IncreaseTimer', 0);
            SetValue($this->GetIDForIdent('NotificationLevel'), 0);
        }

        private function GetActionStatus($actionObject) {
            if ($actionObject['messageVariable'] !== 0) {
                if (!IPS_VariableExists(intval($actionObject['messageVariable']))) {
                    return $this->Translate('Message variable does not exist');
                }
            }

            switch ($actionObject['actionType']) {
                case self::SCRIPT_ACTION:
                    if (!IPS_ScriptExists($actionObject['recipientObjectID'])) {
                        return $this->Translate('No script');
                    }
                    break;

                case self::PUSH_NOTIFICATION_ACTION:
                    if (!IPS_InstanceExists($actionObject['recipientObjectID']) || (IPS_GetInstance($actionObject['recipientObjectID']))['ModuleInfo']['ModuleID'] != '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}') {
                        return $this->Translate('No WebFront');
                    }
                    break;

                case self::IRIS_ACTION:
                    if (!IPS_InstanceExists($actionObject['recipientObjectID']) || (IPS_GetInstance($actionObject['recipientObjectID']))['ModuleInfo']['ModuleID'] != '{DB34DEDB-D0E8-4EE7-8DF8-205AB5D5DA9C}') {
                        return $this->Translate('No IRiS');
                    }
                    break;

                case self::EMAIL_ACTION:
                    if (!IPS_InstanceExists($actionObject['recipientObjectID']) || (IPS_GetInstance($actionObject['recipientObjectID']))['ModuleInfo']['ModuleID'] != '{375EAF21-35EF-4BC4-83B3-C780FD8BD88A}') {
                        return $this->Translate('No SMTP');
                    }
                    break;

                case self::SMS_ACTION:
                    if (!IPS_InstanceExists($actionObject['recipientObjectID']) || (!in_array(IPS_GetInstance($actionObject['recipientObjectID'])['ModuleInfo']['ModuleID'], ['{96102E00-FD8C-4DD3-A3C2-376A44895AC2}', '{DB34DEDB-D0E8-4EE7-8DF8-205AB5D5DA9C}']))) {
                        return $this->Translate('No SMS');
                    }

                    if ($actionObject['recipientAddress'] == '') {
                        return $this->Translate('No recipient address');
                    }
                    break;
            }

            return $this->Translate('OK');
        }

        private function GetLevelStatus($level) {
            $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);

            if (($level < sizeof($levelTable)) && ($levelTable[$level - 1]['duration'] <= 0)) {
                return $this->Translate('No duration');
            }

            foreach ($levelTable[$level - 1]['actions'] as $index => $action) {
                $actionStatus = $this->GetActionStatus($action);
                if ($actionStatus != 'OK') {
                    return $this->Translate('Faulty action') . ' ' . strval($index + 1) . ': ' . $actionStatus;
                }
            }

            return $this->Translate('OK');
        }
    }
?>