<?

    class Benachrichtigung extends IPSModule {

        const SCRIPT_ACTION = 0;
        const PUSH_NOTIFICATION_ACTION = 1;

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

            $this->RegisterMessage($triggerID, 10603 /* VM_UPDATE */);
        }

        public function MessageSink ($TimeStamp, $SenderID, $Message, $Data) {
            $triggerID = $this->ReadPropertyInteger("InputTriggerID");
            if (($SenderID == $triggerID) && ($Message == 10603) && (boolval($Data[0])) && (GetValue($this->GetIDForIdent('NotificationLevel')) == 0)) {
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
                        'rowCount' => 5,
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
                                'width' => '100px',
                                'add' => 60,
                                'edit' => [
                                    'type' => 'NumberSpinner',
                                    'suffix' => ' seconds'
                                ]
                            ],
                            [
                                'name' => 'actions',
                                'caption' => 'Actions',
                                'width' => '100px',
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
                                                'options' => [
                                                    [
                                                        'caption' => 'Script',
                                                        'value' => self::SCRIPT_ACTION
                                                    ],
                                                    [
                                                        'caption' => 'Push',
                                                        'value' => self::PUSH_NOTIFICATION_ACTION
                                                    ]
                                                ]
                                            ]
                                        ],
                                        [
                                            'name' => 'recipient',
                                            'label' => 'Recipient',
                                            'width' => '150px',
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
                                'width' => '70px',
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
                    throw new Exception("Invalid ident");
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
                    $message = $action['message'];
                    if ($action['messageVariable'] !== 0) {
                        if ($message !== '') {
                            $message .= "\n";
                        }
                        $message .= strval(GetValue($action['messageVariable']));
                    }
                    switch ($action['actionType']) {
                        case self::SCRIPT_ACTION:
                            IPS_RunScriptEx(intval($action['recipient']), ['TITLE' => $action['title'], 'MESSAGE' => $action['message'], 'MESSAGE_VARIABLE' => $action['messageVariable']]);
                            break;

                        case self::PUSH_NOTIFICATION_ACTION:
                            WFC_PushNotification(intval($action['recipient']), $action['title'], $message, 'alarm', $this->GetIDForIdent('ResetScript')); // TODO: Jump to comfirm script
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
                // TODO: Error message
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
            switch ($actionObject['actionType']) {
                case self::SCRIPT_ACTION:
                    if (!is_numeric($actionObject['recipient']) || (intval($actionObject['recipient']) < 10000) || (intval($actionObject['recipient']) > 59999)) {
                        return $this->Translate('Invalid ID');
                    }

                    if (!IPS_ScriptExists(intval($actionObject['recipient']))) {
                        return $this->Translate('No script');
                    }
                    break;

                case self::PUSH_NOTIFICATION_ACTION:
                    if (!is_numeric($actionObject['recipient']) || (intval($actionObject['recipient']) < 10000) || (intval($actionObject['recipient']) > 59999)) {
                        return $this->Translate('Invalid ID');
                    }

                    if (!IPS_InstanceExists(intval($actionObject['recipient'])) || (IPS_GetInstance(intval($actionObject['recipient']))['ModuleInfo']['ModuleID'] != '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}')) {
                        return $this->Translate('No WebFront');
                    }

                    if ($actionObject['messageVariable'] !== 0) {
                        if (!IPS_VariableExists(intval($actionObject['messageVariable']))) {
                            return $this->Translate('Message variable does not exist');
                        }
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