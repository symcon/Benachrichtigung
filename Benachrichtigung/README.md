# Benachrichtigung

Dieses Modul ermöglicht eine mehrstufige Benachrichtigung, wobei die Stufe sich nach einer definierten Zeit erhöht und bei einer Quittierung zurückgesetzt wird. Beim Erreichen einer neuen Stufe können verschiedene Aktionen ausgeführt werden.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Starten einer mehrstufigen Benachrichtigungskette durch eine Variable
* Auf jeder Stufe können individuell Aktionen festgesetzt werden:
  * Skripte ausführen
  * Push-Nachrichten, E-Mails oder SMS verschicken
  * Telefonanruf mit Ansage durchfügen (sofern installiert)
  * Durchsage über Lautsprecher durchfügen (sofern installiert)
  * Nachricht über Telegram versenden (sofern installiert)
  * Nach bestimmter Zeit auf nächste Stufe erhöhen
* Quittierung über beigefügtes Skript oder Push-Nachrichten beendet Benachrichtigungen
* Einzelne Stufen können bei Bedarf deaktiviert werden

### 2. Voraussetzungen

- IP-Symcon ab Version 5.1

### 3. Software-Installation

* Über den Modul Store das Modul Benachrichtigung installieren.
* Alternativ über das Modul Control folgende URL hinzufügen:
`https://github.com/symcon/Benachrichtigung`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" kann das 'Benachrichtigung'-Modul mithilfe des Schnellfilters gefunden werden.
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)
- Bei 'Auslöser' eine Variable auswählen
  - Nimmt diese Variable einen Wert an, der nicht neutral ist (false bei Boolean, 0 bei Integer und Float, '' bei String), wird die Benachrichtigungskette auf Stufe 1 gestartet
- Der Liste Benachrichtigungsstufen beliebig viele Stufen hinzufügen und konfigurieren
  - Dauer definiert die Zeit bis die nächste Stufe aktiviert wird
  - Aktionen werden beim Erreichen einer Stufe ausgeführt
  - Aktiv definiert, ob die Stufe ausgeführt werden soll. Stufen, welche nicht aktiv sind, werden übersprungen

__Aktionen einrichten__:

Jede Aktion besitzt Parameter für den Aktionstyp, ein Empfängerobjekt, eine Empfängeradresse, einen Titel, eine Nachricht und eine Nachrichtvariable. Die Aktion soll darstellen, dass eine Nachricht mit dem entsprechenden Titel an das genannten Empfängerobjekt geschickt wird. Der Inhalt der Nachricht enthält den Text in Nachricht, welcher mit dem Text in der Nachrichtenvariable verkettet wird. Der Inhalt der Nachricht entspricht dem im Feld Nachricht angegebenen Text, welchem mit dem Stichwort '{variable}' der Wert der Nachrichtenvariable ersetzt werden kann. Durch '\n' können Zeilenumbrüche eingefügt werden. Auf diese Weise können generische Nachrichten verschickt werden.

___Skript___: Das als Empfänger ausgewählte Skript wird ausgeführt. Während dieses Aufrufs können folgende Systemvariablen verwendet werden:

Systemvariable             | Beschreibung
-------------------------- | --------------
$_IPS['RECIPIENT']         | Der Inhalt des Tabellenfeldes Empfängeradresse
$_IPS['TITLE']             | Der Inhalt des Tabellenfeldes Titel
$_IPS['MESSAGE']           | Der Inhalt des Tabellenfeldes Nachricht
$_IPS['MESSAGE_VARIABLE'] | Die ID der Nachrichtenvariablen 

___Push___: Eine Pushnachricht wird an alle Geräte des gewählten Webfronts geschickt. Diese Nachricht verlinkt das Quittierungsskript. Durch Tippen auf die Pushnachricht kann also die Benachrichtigung quittiert werden. Die Empfängeradresse hat bei diesem Aktionstyp keinen Effekt. Die Nachricht hat eine Maximallänge von 256 Zeichen.

___E-Mail (SMTP)___: Eine E-Mail wird über die gewählte SMTP-Instanz verschickt. Ist eine Empfängeradresse angegeben, so wird die E-Mail an diese Adresse verschickt. Ist keine Empfängeradresse angegeben, so wird die E-Mail an den angegebenen Empfänger der SMTP-Instanz geschickt. Wenn die Option 'Erweiterte Antwort' aktiviert ist kann mit dem Stichwort '{actions}' ein Block mit Links eingefügt werden, über die die verfügbaren Aktionen ausgeführt werden können.

___SMS___: Eine SMS wird über die gewählte SMS-Instanz an die in der Empfängeradresse angegebene Telefonnummer geschickt. Wenn die Option 'Erweiterte Antwort' aktiviert ist kann mit dem Stichwort '{actions}' ein Link eingefügt werden, über den die verfügbaren Aktionen ausgeführt werden können. Ist eine Nachricht länger als 160 Zeichen (Begrenzung durch SMS), wird diese auf bis zu 2 weitere SMS aufgeteilt.

___Telefonansage (nur verfügbar, wenn das Modul [Telefonansage](https://github.com/symcon/Telefonansage) installiert ist)___: Die in der Empfängeradresse angegebene Telefonnummer wird angerufen und der Titel sowie die Nachricht vorgelesen. Wenn die Option 'Erweiterte Antwort' aktiviert ist kann mit den Tasten 0-9 die Dazugehörige Aktion ausgeführt werden. Wenn gewünscht können mit dem Stichwort '{actions}' die verfügbaren Aktionen in die Nachricht eingebunden werden.

___Durchsage (nur verfügbar, wenn das Modul [Durchsage](https://github.com/symcon/Durchsage) installiert ist)___: Der Titel und die Nachricht werden mithilfe der ausgewählten Instanz vorgelesen. Die Empfängeradresse hat bei diesem Aktionstyp keinen Effekt.

___Telegram (nur verfügbar, wenn das Modul [Telegram](https://github.com/symcon/Telegram) installiert ist)___: Der Titel und die Nachricht werden mithilfe der ausgewählten Instanz an den Empfänger gesendet. Die Empfängeradresse kann entweder der Name oder die UserID vom Telegram Empfänger sein. Sofern die Empfängeradresse leer gelassen wird, werden alle Empfänger, die im Telegram Bot hinterlegt sind, benachrichtigt. 

Die Spalte 'Status' der Liste 'Benachrichtigungsstufen' beinhaltet Fehlermeldungen, falls bei der Konfiguration der Stufe etwas nicht korrekt ist, ansonsten "OK"

__Erweiterte Antwort__: Wenn erweiterte Antwort aktiviert ist, können verschiedene Aktionen in der entsprechenden Liste definiert werden. Bei jeder Aktion wird standardmäßig die Benachrichtigung zurückgesetzt. Um festzulegen was bei einer Aktion zusätzlich ausgeführt wird kann ein ausgelöstes Ereignis erstellt werden. Als auslösende Variable wird die Variable 'Antwortaktion' und als Auslöser 'Bei bestimmtem Wert' gewählt. Als Wert kann nun die gewünschte Aktion ausgewählt werden.

### 5. Statusvariablen und Profile

Die Statusvariable 'Benachrichtigungsstufe' beinhaltet die aktuelle Benachrichtigungsstufe. Das Skript 'Reset' kann ausgeführt werden um die Benachrichtigungsstufe zurückzusetzen und so zu quittieren.

### 6. WebFront

Über das WebFront kann das Skript 'Reset' ausgeführt werden.
Die aktuelle Benachrichtigungsstufe wird angezeigt.

### 7. PHP-Befehlsreferenz

`boolean BN_Reset(integer $InstanzID);`
Setzt die Benachrichtigungsstufe zurück und deaktiviert die Benachrichtigungskette
`BN_Reset(12345);`

`boolean BN_SetNotifyLevel(integer $InstanzID, integer $Level);`
Schaltet das Benachrichtigungsmodul auf die vorgegebene Stufe. Die Aktionen der neuen Stufe werden direkt ausgeführt. Aktionen von übersprungenen Stufen werden NICHT ausgeführt.  
`BN_SetNotifyLevel(12345, 2);`

`boolean BN_IncreaseLevel(integer $InstanzID);`
Falls noch eine weitere Stufe existiert, wird die aktuelle Stufe erhöht. Hierbei werden die Aktionen der neuen Stufe ausgeführt.
`BN_IncreaseLevel(12345);`
