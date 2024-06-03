<?php

declare(strict_types=1);

include_once __DIR__ . '/../libs/WebHookModule.php';

class Notification extends WebHookModuleBenachrichtigung
{
    public const SCRIPT_ACTION = 0;
    public const PUSH_NOTIFICATION_ACTION = 1;
    public const IRIS_ACTION = 2;
    public const EMAIL_ACTION = 3;
    public const SMS_ACTION = 4;
    public const PHONE_ANNOUNCEMENT_ACTION = 5;
    public const ANNOUNCEMENT_ACTION = 6;
    public const TELEGRAM_ACTION = 7;
    public function __construct($InstanceID)
    {
        parent::__construct($InstanceID, 'notification-response/' . $this->InstanceID);
    }

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        //Properties
        $this->RegisterPropertyInteger('InputTriggerID', 0);
        $this->RegisterPropertyString('NotificationLevels', '[]');
        $this->RegisterPropertyBoolean('TriggerOnChangeOnly', false);
        $this->RegisterPropertyBoolean('AdvancedResponse', false);
        $this->RegisterPropertyString('AdvancedResponseActions', '[]');

        //Profiles
        if (!IPS_VariableProfileExists('BN.Actions')) {
            IPS_CreateVariableProfile('BN.Actions', 1);
            IPS_SetVariableProfileIcon('BN.Actions', 'Information');
            IPS_SetVariableProfileValues('BN.Actions', 0, 0, 0);
        }

        //Variables
        $this->RegisterVariableInteger('NotificationLevel', $this->Translate('Notification Level'), '');
        $this->RegisterVariableBoolean('Active', $this->Translate('Notifications active'), '~Switch');
        $this->RegisterVariableInteger('ResponseAction', $this->Translate('Response Action'), 'BN.Actions');

        //Actions
        $this->EnableAction('ResponseAction');

        //Timer
        $this->RegisterTimer('IncreaseTimer', 0, 'BN_IncreaseLevel($_IPS[\'TARGET\']);');

        $this->EnableAction('Active');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        $triggerID = $this->ReadPropertyInteger('InputTriggerID');

        //Delete all registrations in order to readd them
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }
        $this->RegisterMessage($triggerID, VM_UPDATE);

        if (method_exists($this, 'GetReferenceList')) {
            $refs = $this->GetReferenceList();
            foreach ($refs as $ref) {
                $this->UnregisterReference($ref);
            }

            $inputTriggerID = $this->ReadPropertyInteger('InputTriggerID');
            if ($inputTriggerID) {
                $this->RegisterReference($inputTriggerID);
            }

            $notificationLevels = json_decode($this->ReadPropertyString('NotificationLevels'), true);

            foreach ($notificationLevels as $notificationLevel) {
                foreach ($notificationLevel['actions'] as $action) {
                    if ($action['recipientObjectID']) {
                        $this->RegisterReference($action['recipientObjectID']);
                    }
                }
            }
        }
        //Delete all associations
        $profile = IPS_GetVariableProfile('BN.Actions');
        foreach ($profile['Associations'] as $association) {
            IPS_SetVariableProfileAssociation('BN.Actions', $association['Value'], '', '', '-1');
        }
        //Setting the instance status
        $this->setInstanceStatus();

        //Set Associations
        if ($this->ReadPropertyBoolean('AdvancedResponse')) {
            //Return if action is not unique
            //Only important, if advanced response actions are used
            if ($this->GetStatus() != IS_ACTIVE) {
                return;
            }
            $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
            foreach ($responseActions as $responseAction) {
                IPS_SetVariableProfileAssociation('BN.Actions', $responseAction['Index'], $responseAction['CustomName'], '', '-1');
            }
        } else {
            IPS_SetVariableProfileAssociation('BN.Actions', 0, $this->Translate('Reset'), '', '-1');
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        //Return if variable value hasn't changed and message type is change
        if (($this->ReadPropertyBoolean('TriggerOnChangeOnly')) && !$Data[1]) {
            return;
        }
        $triggerID = $this->ReadPropertyInteger('InputTriggerID');
        if (($SenderID == $triggerID) && (boolval($Data[0])) && (GetValue($this->GetIDForIdent('NotificationLevel')) <= 0)) {
            $firstActiveLevel = $this->GetNextActiveLevel(1);
            if ($firstActiveLevel !== -1) {
                $this->SetNotifyLevel($firstActiveLevel);
            } else {
                echo $this->Translate('No active levels are defined');
            }
        }
        $notifyLevel = $this->GetValue('NotificationLevel');
        if ($notifyLevel > 0) {
            $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);
            $dtmfID = 0;
            foreach ($levelTable[$notifyLevel - 1]['actions'] as $action) {
                if ($action['actionType'] == self::PHONE_ANNOUNCEMENT_ACTION) {
                    $dtmfID = IPS_GetObjectIDByIdent('DTMF', $action['recipientObjectID']);
                    if ($dtmfID == $SenderID) {
                        break;
                    }
                }
            }
            if (($this->GetStatus() == IS_ACTIVE) && ($SenderID == $dtmfID)) {
                $indexes = [];
                $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
                foreach ($responseActions as $responseAction) {
                    $indexes[] = $responseAction['Index'];
                }
                if ((preg_match('/[0-9]/', $Data[0]) != 0) && in_array(intval($Data[0]), $indexes)) {
                    $this->RequestAction('ResponseAction', intval($Data[0]));
                }
            }
        }
    }

    public function GetConfigurationForm()
    {
        $notificationValues = [];
        $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);
        for ($i = 1; $i <= count($levelTable); $i++) {
            $actionValues = [];
            foreach ($levelTable[$i - 1]['actions'] as $action) {
                $actionValues[] = ['status' => $this->GetActionStatus($action)];
            }
            $notificationValues[] = [
                'level'   => strval($i),
                'status'  => $this->GetLevelStatus($i),
                'actions' => $actionValues
            ];
        }

        $actionTypeOptions = [
            [
                'caption' => 'Script',
                'value'   => self::SCRIPT_ACTION
            ],
            [
                'caption' => 'Push',
                'value'   => self::PUSH_NOTIFICATION_ACTION
            ],
            [
                'caption' => 'E-Mail (SMTP)',
                'value'   => self::EMAIL_ACTION
            ],
            [
                'caption' => 'SMS',
                'value'   => self::SMS_ACTION
            ]
        ];

        // Is IRiS installed?
        if (IPS_LibraryExists('{077A9478-72B4-484B-9F79-E5EA9088C52E}')) {
            $actionTypeOptions[] = [
                'caption' => 'IRiS',
                'value'   => self::IRIS_ACTION
            ];
        }

        // Is Telefonansage installed?
        if (IPS_LibraryExists('{EC529B8B-67DF-B940-A1C0-08A97C2053D9}')) {
            $actionTypeOptions[] = [
                'caption' => 'Phone Announcement',
                'value'   => self::PHONE_ANNOUNCEMENT_ACTION
            ];
        }

        // Is Durchsage installed?
        if (IPS_LibraryExists('{00D4E950-DA68-B784-B62D-E22193C711D8}')) {
            $actionTypeOptions[] = [
                'caption' => 'Durchsage',
                'value'   => self::ANNOUNCEMENT_ACTION
            ];
        }

        // Is Telegram installed?
        if (IPS_LibraryExists('{35253DF7-D0E7-8AF9-0B52-715ED9E1EA6A}')) {
            $actionTypeOptions[] = [
                'caption' => 'Telegram',
                'value'   => self::TELEGRAM_ACTION
            ];
        }

        $form = [
            'elements' => [
                [
                    'type'    => 'SelectVariable',
                    'name'    => 'InputTriggerID',
                    'caption' => 'Trigger'
                ],
                [
                    'type'     => 'List',
                    'name'     => 'NotificationLevels',
                    'caption'  => 'Notification Levels',
                    'rowCount' => 8,
                    'add'      => true,
                    'delete'   => true,
                    'columns'  => [
                        [
                            'name'    => 'level',
                            'caption' => 'Level',
                            'width'   => '75px',
                            'add'     => ''
                        ],
                        [
                            'name'    => 'active',
                            'caption' => 'Active',
                            'width'   => '60px',
                            'add'     => true,
                            'edit'    => [
                                'type' => 'CheckBox'
                            ]
                        ],
                        [
                            'name'    => 'actions',
                            'caption' => 'Actions (will be executed in parallel)',
                            'width'   => '120px',
                            'add'     => [],
                            'edit'    => [
                                'type'     => 'List',
                                'rowCount' => 5,
                                'add'      => true,
                                'delete'   => true,
                                'columns'  => [
                                    [
                                        'name'    => 'actionType',
                                        'caption' => 'Action',
                                        'width'   => '150px',
                                        'add'     => 0,
                                        'edit'    => [
                                            'type'    => 'Select',
                                            'options' => $actionTypeOptions
                                        ]
                                    ],
                                    [
                                        'name'    => 'recipientObjectID',
                                        'caption' => 'Recipient Object',
                                        'width'   => '200px',
                                        'add'     => 1,
                                        'edit'    => [
                                            'type' => 'SelectObject'
                                        ]
                                    ],
                                    [
                                        'name'    => 'recipientAddress',
                                        'caption' => 'Recipient Address',
                                        'width'   => '200px',
                                        'add'     => '',
                                        'edit'    => [
                                            'type' => 'ValidationTextBox'
                                        ]
                                    ],
                                    [
                                        'name'    => 'title',
                                        'caption' => 'Title',
                                        'width'   => '100px',
                                        'add'     => '',
                                        'edit'    => [
                                            'type' => 'ValidationTextBox'
                                        ]
                                    ],
                                    [
                                        'name'    => 'message',
                                        'caption' => 'Message',
                                        'width'   => '200px',
                                        'add'     => '',
                                        'edit'    => [
                                            'type' => 'ValidationTextBox'
                                        ]
                                    ],
                                    [
                                        'name'    => 'messageVariable',
                                        'caption' => 'Message Variable',
                                        'width'   => '200px',
                                        'add'     => 1,
                                        'edit'    => [
                                            'type' => 'SelectVariable'
                                        ]
                                    ]/*, // TODO: How to show status for actions?
                                        [
                                            'name' => 'status',
                                            'caption' => 'Status',
                                            'width' => '70px',
                                            'add' => ''
                                        ]*/
                                ]
                            ]
                        ],
                        [
                            'name'    => 'duration',
                            'caption' => 'Duration, after which the next level will be executed',
                            'width'   => '120px',
                            'add'     => 60,
                            'edit'    => [
                                'type'    => 'NumberSpinner',
                                'suffix'  => ' seconds',
                                'width'   => '350px',
                            ]
                        ],
                        [
                            'name'    => 'status',
                            'caption' => 'Status',
                            'width'   => '300px',
                            'add'     => ''
                        ]
                    ],
                    'values' => $notificationValues
                ],
                [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Advanced Settings',
                    'width'   => '775px',
                    'items'   => [
                        [
                            'name'    => 'TriggerOnChangeOnly',
                            'caption' => 'Notify on',
                            'type'    => 'Select',
                            'options' => [
                                [
                                    'caption' => 'Variable Update',
                                    'value'   => false
                                ],
                                [
                                    'caption' => 'Variable Change',
                                    'value'   => true
                                ]
                            ]
                        ]
                    ]
                ],
                [
                    'type'     => 'CheckBox',
                    'name'     => 'AdvancedResponse',
                    'caption'  => 'Advanced Response',
                    'onChange' => 'BN_ToggleAdvancedResponseActions($id, $AdvancedResponse);'
                ],
                [
                    'type'     => 'List',
                    'name'     => 'AdvancedResponseActions',
                    'caption'  => 'Response Actions',
                    'visible'  => $this->ReadPropertyBoolean('AdvancedResponse'),
                    'rowCount' => 10,
                    'add'      => true,
                    'delete'   => true,
                    'sort'     => [
                        'column'    => 'Index',
                        'direction' => 'ascending'
                    ],
                    'onAdd'    => 'BN_UpdateAdd($id, $AdvancedResponseActions);',
                    'onDelete' => 'BN_UpdateAdd($id, $AdvancedResponseActions);',
                    'onEdit'   => 'BN_UpdateAdd($id, $AdvancedResponseActions);',
                    'columns'  => $this->generateAdvancedActionColumns(json_decode($this->ReadPropertyString('AdvancedResponseActions'), true))
                ]
            ],
            'actions' => [
                [
                    'type'    => 'TestCenter'
                ]
            ],
            'status' => [
                [
                    'code'    => 200,
                    'caption' => 'Defined actions are not unique',
                    'icon'    => 'error'
                ]
            ]
        ];

        return json_encode($form);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Active':
                if (GetValue($this->GetIDForIdent('NotificationLevel')) != 0) {
                    echo $this->Translate('Cannot deactivate while there is an unconfirmed notification. Please reset the notification level first.');
                    return;
                }
                SetValue($this->GetIDForIdent('Active'), $Value);
                break;

            case 'ResponseAction':
                SetValue($this->GetIDForIdent('ResponseAction'), $Value);
                $this->Reset();
                break;
            default:
                throw new Exception($this->Translate('Invalid ident'));
        }
    }

    public function SetNotifyLevel(int $Level)
    {
        if (!GetValue($this->GetIDForIdent('Active'))) {
            return;
        }

        SetValue($this->GetIDForIdent('NotificationLevel'), $Level);

        $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);

        if ($Level >= 0 && $Level <= count($levelTable)) {
            if ($levelTable[$Level - 1]['active'] === false) { // Triple equal. If not defined, it's legacy and everything is fine and assumed active
                throw new Exception($this->Translate('Selected Level is not active'));
            }

            foreach ($levelTable[$Level - 1]['actions'] as $action) {
                // Only send actions that are "OK"
                if ($this->GetActionStatus($action) != $this->Translate('OK')) {
                    continue;
                }

                $message = $action['message'];
                $this->SendDebug('Set Notify', 'Trying to get data from message variable', 0);
                if (IPS_VariableExists($action['messageVariable'])) {
                    $message = str_replace('{variable}', strval(GetValue($action['messageVariable'])), $message);
                }

                // Support new line
                $message = str_replace('\\n', "\n", $message);

                switch ($action['actionType']) {
                    case self::SCRIPT_ACTION:
                        IPS_RunScriptEx($action['recipientObjectID'], ['RECIPIENT' => $action['recipientAddress'], 'TITLE' => $action['title'], 'MESSAGE' => $action['message'], 'MESSAGE_VARIABLE' => $action['messageVariable']]);
                        break;

                    case self::PUSH_NOTIFICATION_ACTION:
                        switch (IPS_GetInstance($action['recipientObjectID'])['ModuleInfo']['ModuleID']) {
                            case '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}':
                                //Only send 256 characters
                                WFC_PushNotification($action['recipientObjectID'], $action['title'], substr($message, 0, 256), 'alarm', $this->InstanceID);
                                break;
                            case '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}':
                                //Only send 256 characters
                                VISU_PostNotification($action['recipientObjectID'], $action['title'], substr($message, 0, 256), 'alarm', $this->InstanceID);
                                break;
                        }
                        break;

                    case self::IRIS_ACTION:
                        IRIS_AddAlarm($action['recipientObjectID'], 'Fire');
                        break;

                    case self::EMAIL_ACTION:
                        $connectUrl = CC_GetConnectURL(IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0]);
                        if ($this->ReadPropertyBoolean('AdvancedResponse') && ($this->GetStatus() == IS_ACTIVE)) {
                            $emailActions = "\n";
                            $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
                            foreach ($responseActions as $responseAction) {
                                $emailActions .= sprintf("%s: %s/hook/notification-response/%d/?action=%d\n", $responseAction['CustomName'], $connectUrl, $this->InstanceID, $responseAction['Index']);
                            }
                            $message = str_replace('{actions}', $emailActions, $message);
                        } else {
                            $message = str_replace('{actions}', "$connectUrl/hook/notification-response/$this->InstanceID/?action=0\n", $message);
                        }
                        if ($action['recipientAddress'] != '') {
                            SMTP_SendMailEx($action['recipientObjectID'], $action['recipientAddress'], $action['title'], $message);
                        } else {
                            SMTP_SendMail($action['recipientObjectID'], $action['title'], $message);
                        }
                        break;

                    case self::SMS_ACTION:
                        $smsMessage = $action['title'] . ': ' . $message;

                        // Construct and insert action link
                        $connectUrl = CC_GetConnectURL(IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0]);
                        $smsLink = "$connectUrl/hook/notification-response/$this->InstanceID/";
                        $smsMessage = str_replace('{actions}', $smsLink, $smsMessage);

                        // Seperate SMS if > 160 chars
                        $words = explode(' ', $smsMessage);
                        $messages = [$words[0]];
                        array_shift($words);
                        while (count($words) > 0) {
                            $currentMessage = &$messages[count($messages) - 1];
                            if ((strlen($currentMessage) + strlen($words[0]) + 1) < 160) {
                                $currentMessage .= ' ' . array_shift($words);
                            } else {
                                $messages[] = array_shift($words);
                            }
                        }
                        if (count($messages) > 3) {
                            $this->SendDebug('SMS', sprintf($this->Translate('Not the whole message could be sent.'), strlen($smsMessage)), 0);
                            //Add [...] to the last sent message
                            $messages[2] = substr_replace($messages[2], '...', strlen($messages[2]) - 3);
                        }
                        foreach ($messages as $index => $message) {
                            //Do not send any more messages if 3 have already been sent
                            if ($index > 2) {
                                break;
                            }
                            SMS_Send($action['recipientObjectID'], $action['recipientAddress'], $message);
                        }
                        break;

                    case self::PHONE_ANNOUNCEMENT_ACTION:
                        if ($this->ReadPropertyBoolean('AdvancedResponse') && ($this->GetStatus() == IS_ACTIVE)) {
                            $dtmfID = IPS_GetObjectIDByIdent('DTMF', $action['recipientObjectID']);
                            $this->RegisterMessage($dtmfID, VM_UPDATE);
                            $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
                            $phoneResponses = "\n";
                            foreach ($responseActions as $responseAction) {
                                if ($responseAction['Index'] >= 0 && $responseAction['Index'] <= 9) {
                                    $phoneResponses .= sprintf($this->Translate('Press %d for action %s!'), $responseAction['Index'], $responseAction['CustomName']);
                                }
                            }
                            $message = str_replace('{actions}', $phoneResponses, $message);
                        }
                        TA_StartCallEx($action['recipientObjectID'], $action['recipientAddress'], $action['title'] . ' ' . $message);
                        break;

                    case self::ANNOUNCEMENT_ACTION:
                        DS_Play($action['recipientObjectID'], $action['title'] . ' ' . $message);
                        break;

                    case self::TELEGRAM_ACTION:
                        $connectUrl = CC_GetConnectURL(IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0]);
                        if ($this->ReadPropertyBoolean('AdvancedResponse') && ($this->GetStatus() == IS_ACTIVE)) {
                            $emailActions .= "\n";
                            $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
                            foreach ($responseActions as $responseAction) {
                                $emailActions .= sprintf("%s: %s/hook/notification-response/%d/?action=%d\n", $responseAction['CustomName'], $connectUrl, $this->InstanceID, $responseAction['Index']);
                            }
                            $message = str_replace('{actions}', $emailActions, $message);
                        } else {
                            $message = str_replace('{actions}', "$connectUrl/hook/notification-response/$this->InstanceID/?action=0\n", $message);
                        }
                        if ($action['recipientAddress'] != '') {
                            TB_SendMessageEx($action['recipientObjectID'], $action['title'] . ': ' . $message, $action['recipientAddress']);
                        } else {
                            TB_SendMessage($action['recipientObjectID'], $action['title'] . ': ' . $message);
                        }
                        break;

                }
            }

            $nextActiveLevel = $this->GetNextActiveLevel($Level + 1);
            $this->SendDebug('Next Active Level', $nextActiveLevel, 0);
            if ($nextActiveLevel != -1) {
                $this->SetTimerInterval('IncreaseTimer', $levelTable[$Level - 1]['duration'] * 1000);
            } else {
                $this->SetTimerInterval('IncreaseTimer', 0);
            }
        } else {
            throw new Exception(sprintf($this->Translate('Selected Level %s is not defined'), $Level));
        }
    }

    public function IncreaseLevel()
    {
        $this->SetNotifyLevel($this->GetNextActiveLevel(GetValue($this->GetIDForIdent('NotificationLevel')) + 1));
    }

    public function Reset()
    {
        $this->SetTimerInterval('IncreaseTimer', 0);
        SetValue($this->GetIDForIdent('NotificationLevel'), 0);
    }

    public function ToggleAdvancedResponseActions(bool $visible)
    {
        $this->UpdateFormField('AdvancedResponseActions', 'visible', $visible);
    }

    public function UpdateAdd(IPSList $AdvancedResponseActions)
    {
        $this->SendDebug('IPS_List', print_r($AdvancedResponseActions, true), 0);
        $this->UpdateFormField('AdvancedResponseActions', 'columns', json_encode($this->generateAdvancedActionColumns($AdvancedResponseActions)));
    }

    protected function ProcessHookData()
    {
        $html =
        '<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
            <style>
            .btn {              
                display: block;
                margin: auto;
                padding: 10px;
                border-width: 0;
                outline: none;
                border-radius: 2px;
                box-shadow: 0 1px 4px rgba(0, 0, 0, .6);
            }
            .btn:hover, .btn:focus {
                background-color: rgb(220 220 220);
            }
            .link {              
                text-decoration: none;
            }
            .font {
                font-family: Lucida Sans Unicode, Lucida Grande, sans-serif;
                font-size: larger;
            }
            h1 {
                font-family: Lucida Sans Unicode, Lucida Grande, sans-serif;
            }
            </style>
            </script><div class"font" style="text-align:center;">';
        if ($this->GetValue('Active')) {
            $params = [];
            parse_str($_SERVER['QUERY_STRING'], $params);
            if (isset($params['action'])) {
                $this->RequestAction('ResponseAction', intval($params['action']));
                $actionName = $this->Translate('unknown action');
                if ($this->ReadPropertyBoolean('AdvancedResponse') && ($this->GetStatus() == IS_ACTIVE)) {
                    $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
                    foreach ($responseActions as $responseAction) {
                        if ($responseAction['Index'] == intval($params['action'])) {
                            if ($responseAction['ExecutionMessage'] == '') {
                                echo $html . '<h1>' . sprintf($this->Translate('\'%s\' was executed!'), $responseAction['CustomName']) . '</h1>';
                            } else {
                                echo $html . '<h1>' . $responseAction['ExecutionMessage'] . '</h1>';
                            }
                            break;
                        }
                    }
                } else {
                    echo $html . '<h1>' . $this->Translate('Reset successful') . '</h1>';
                }
            } else {
                if ($this->ReadPropertyBoolean('AdvancedResponse')) {
                    if ($this->GetStatus() == IS_ACTIVE) {
                        $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
                        foreach ($responseActions as $responseAction) {
                            $html .=
                            '<a class="link" href="/hook/notification-response/' . $this->InstanceID . '/?action=' . $responseAction['Index'] . '">
                                <button class="btn font" style="width:80%">' . $responseAction['CustomName'] . '</button>
                            </a><br/>';
                        }
                        $html .= '</div>';
                    } else {
                        echo $html . '<h1>' . $this->Translate('Defined actions are not unique') . '</h1>';
                    }
                } else {
                    $html .=
                        '<a class="link" href="/hook/notification-response/' . $this->InstanceID . '/?action=0">
                                <button class="btn font" style="width:80%">' . $this->Translate('Reset') . '</button>
                            </a><br/>';
                }

                echo $html;
            }
        } else {
            echo $html . '<h1>' . $this->Translate('Module disabled') . '</h1>';
        }
    }

    private function GetNextActiveLevel(int $targetLevel)
    {
        $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);

        while ($targetLevel > 0 && $targetLevel <= count($levelTable)) {
            // Check for !== false to handle legacy installations that do not have an active parameter
            if ($levelTable[$targetLevel - 1]['active'] !== false) {
                return $targetLevel;
            }
            $targetLevel++;
        }

        return -1;
    }

    private function GetActionStatus($actionObject)
    {
        if (($actionObject['messageVariable'] >= 10000) && !IPS_VariableExists(intval($actionObject['messageVariable']))) {
            return $this->Translate('Message variable does not exist');
        }

        switch ($actionObject['actionType']) {
            case self::SCRIPT_ACTION:
                if (!IPS_ScriptExists($actionObject['recipientObjectID'])) {
                    return $this->Translate('No script');
                }
                break;

            case self::PUSH_NOTIFICATION_ACTION:
                if (!IPS_InstanceExists($actionObject['recipientObjectID'])
                    || (
                        IPS_GetInstance($actionObject['recipientObjectID'])['ModuleInfo']['ModuleID'] != '{3565B1F2-8F7B-4311-A4B6-1BF1D868F39E}'
                        && IPS_GetInstance($actionObject['recipientObjectID'])['ModuleInfo']['ModuleID'] != '{B5B875BB-9B76-45FD-4E67-2607E45B3AC4}'
                    )
                ) {
                    return $this->Translate('No Visualization');
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

            case self::PHONE_ANNOUNCEMENT_ACTION:
                if (!IPS_InstanceExists($actionObject['recipientObjectID']) || (IPS_GetInstance($actionObject['recipientObjectID']))['ModuleInfo']['ModuleID'] != '{C44E335C-DB75-B927-A3F2-3FFD024A5053}') {
                    return $this->Translate('No Phone Announcement');
                }

                if ($actionObject['recipientAddress'] == '') {
                    return $this->Translate('No recipient address');
                }
                break;

            case self::ANNOUNCEMENT_ACTION:
                if (!IPS_InstanceExists($actionObject['recipientObjectID']) || (IPS_GetInstance($actionObject['recipientObjectID']))['ModuleInfo']['ModuleID'] != '{26F752F0-4D4E-A52C-DB3C-35DFEA979F44}') {
                    return $this->Translate('No Announcement');
                }
        }

        return $this->Translate('OK');
    }

    private function GetLevelStatus($level)
    {
        $levelTable = json_decode($this->ReadPropertyString('NotificationLevels'), true);

        if (($level < count($levelTable)) && ($levelTable[$level - 1]['duration'] <= 0)) {
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

    private function generateAdvancedActionColumns($responseActions)
    {
        $indexes = [];
        foreach ($responseActions as $responseAction) {
            $indexes[] = $responseAction['Index'];
        }
        $nextIndex = 1;
        while (in_array($nextIndex, $indexes)) {
            $nextIndex++;
        }

        $columns = [
            [
                'name'    => 'Index',
                'caption' => $this->Translate('Action'),
                'width'   => '100px',
                'add'     => $nextIndex,
                'edit'    => [
                    'type' => 'NumberSpinner'
                ]
            ],
            [
                'name'    => 'CustomName',
                'caption' => $this->Translate('Custom Name'),
                'width'   => '300px',
                'add'     => sprintf($this->Translate('Action %d'), $nextIndex),
                'edit'    => [
                    'type'     => 'ValidationTextBox',
                    'validate' => '.'
                ]
            ],
            [
                'name'    => 'ExecutionMessage',
                'caption' => 'Execution Message',
                'width'   => '300px',
                'add'     => '',
                'edit'    => [
                    'type' => 'ValidationTextBox'
                ]
            ]
        ];
        return $columns;
    }

    private function setInstanceStatus()
    {
        $getInstanceStatus = function ()
        {
            $indexes = [];
            $responseActions = json_decode($this->ReadPropertyString('AdvancedResponseActions'), true);
            foreach ($responseActions as $responseAction) {
                $indexes[] = $responseAction['Index'];
            }
            //Check if an action is defined more than once
            if (count($indexes) != count(array_unique($indexes))) {
                return 200;
            }

            return IS_ACTIVE;
        };

        $newStatus = $getInstanceStatus();
        if ($this->GetStatus() != $newStatus) {
            $this->SetStatus($newStatus);
        }
    }

    private function splitSMS($message)
    {
    }
}
