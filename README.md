### IP-Symcon Modul für die GO-eCharger Wallbox

Nicht verwenden! Don't use it yet!

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)
4. [Module](#4-module)

## 1. Funktionsumfang

Das Modul ist dazu gedacht die [GO-eCharger Wallbox](www.go-e.co) zum Laden von Elektrofahrzeugen in [IP-Symcon](www.ip-symcon.de) einzubinden. 

Es soll sowohl Zustandsdaten (Anschluss, Ladevorgang, etc.) als auch Schaltaktionen (Ladevorgang starten/stoppen, Ladeströme setzen) zur Verfügung stellen.

## 2. Systemanforderungen
- IP-Symcon ab Version 4.x

## 3. Installation

Das Modul befindet sich im Entwicklungsstadium und ist derzeit **nicht** für die Nutzung freigegeben.

## 4. Module

### 4.1. go-eCharger

Das Modul "go-eCharger" dient als Schnittstelle zu einem lokal installierten go-eCharger. Es liefert aktuelle Messwerte als Instanzvariablen und bietet einen Zugriff auf Funktionen des go-eChargers. Der go-eCharger muss dabei lokal über eine IP-Adresse erreichbar sein (siehe Installation).

#### 4.1.1 Status Variablen
Im folgenden werden die verfügbaren Statusvariablen mit ihren Eigenschaften, Werten und Zugriffsmöglichkeiten aufgelistet.

RO = **R**ead **O**nly
RW = **R**ead **W**rite enabled
WF = **W**eb**f**rond change enabled

Name | Type | Optionen | Werte | Zugriffsfunktionen
:--- | :---: |  :---:  | :---  | :---
`Seriennummer` | String | RO | Seriennummer des go-eChargers | keine
`Wallbox aktiv` | Integer | RW, WF | Kann an der Wallbox geladen werden?<br>Beispiel: 000815 | IsActive<br>SetActive 




#### 4.1.1. Status
+ **Seriennummer**
+ **Zustand**
+ **derzeit verfügbarer Ladestrom** (Strom, den ein eFahrzeug bei Anschluss max. bekommt)
+ **maximal verfügbarer Ladestrom** (Strom, der an eCharger maximal für das Laden gewählt werden kann)
+ **verfügbare Spannung an Phase 1 / 2 / 3** (die Spannungsversorgung des go-eChargers je Phase)
+ **max. verfügbare Ladeleistung** (berechnet aus der aktuellen Spannungsversorgung sowie dem verfügbaren Ladestrom)
+ **Kabel-Verriegelungsmodus** (0 = Verriegelt, wenn Auto angeschlossen; 1 = Am Ladeende entriegeln; 2 = Immer verriegelt)
+ **Ladeende bei Akkustand** (0.0 kw - 100.0 kw; 0.0 = deaktiviert)
+ **Zugangskontrolle via RFID/APP** (0 = aus; 1 = an)
+ **Charger aktiviert** (0 = deaktiviert; 1 = aktiviert)

#### 4.1.2. Funktionen

##### 4.1.2.1 Update(int $Instanz)
Aktualisiert die Messwerte (IPS Variablen) des go-eChargers. Diese Funktion wird auch in Abhängigkeit der eingestellten Aktualisierungsfrequenzen in den Moduleinstellungender ausgeführt, so dass normalerweise ein manueller Aufruf unnötig sein sollte.
```
GOeCharger_Update( $Instanz ); // Aktualisiert die Messwerte (IPS Variablen) des go-eChargers
```
Die Funktion liefert true oder false als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.2 GetError(int $Instanz)
Ermittlung ob der go-eCharger einen Fehlercode meldet.
+ 1: RCCB (Fehlerstromschutzschalter) 
+ 3: PHASE (Phasenstörung)
+ 8: NO_GROUND (Erdungserkennung) 
+ 10, default: INTERNAL (sonstiges)
```
$Error = GOeCharger_GetError( $Instanz ); // Ermittlung des Fehlerwerts
```

##### 4.1.2.3 GetStatus(int $Instanz)
Ermittlung des aktuellen Status des go-eChargers.
+ 1: Ladestation bereit, kein Fahrzeug
+ 2: Fahrzeug lädt
+ 3: Warte auf Fahrzeug
+ 4: Ladung beendet, Fahrzeug noch verbunden
```
$Status = GOeCharger_GetStatus( $Instanz ); // Ermittlung des Status
```

##### 4.1.2.4 Maximal verfügbarer Ladestrom
Mit diesen Funktionen kann der maximal verfügbare Ladestrom kontrolliert werden, den der go-eCharger zur Verfügung stellen kann. Der aktuell eingestellte Ladestrom ("CurrentChargingAmperage") ist kleiner oder gleich.

###### 4.1.2.4.1 GetMaximumChargingAmperage(int $Instanz )
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers abgefragt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
```
$MaximumChargingAmperage = GOeCharger_GetMaximumChargingAmperage( $Instanz ); // Liest den maximal verfügbaren Ladestrom
```

###### 4.1.2.4.2 SetMaximumChargingAmperage(int $Instanz, int $Ampere)
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang. Der maximale Ladestrom sollte an die verfügbare Hausinstallation angepasst sein. Die über IPS maximal einstellbare Ladestrom kann über die Moduleinstellungen beschränkt werden!
Sollte der maximal verfügbare Ladestrom reduziert werden, so wird ggf. auch der aktuell eingestellte Ladestrom entsprechend verringert, sofern er das neue Maximum überschreiten würde.
```
GOeCharger_SetMaximumChargingAmperage( $Instanz, 16 ); // Setze den maximal verfügbaren Ladestrom auf 16 Ampere
```
Die Funktion liefert *true* oder *false* als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.5 Aktuell verfügbarer Ladestrom
Mit diesen Funktionen ist der aktuell verfügbare Ladestrom, den der go-eCharger zur Verfügung stellt, kontrollierbar. Wenn der go-eCharger z.B. maximal 32A zur Verfügung stellen kann (siehe "Maximal verfügbarer Ladestrom"), dann kann ggf. der go-eCharger aktuell auf 10A eingestellt sein. 
Das angeschlossene Fahrzeug kann maximal diesen Ladestrom abrufen, kann aber auch je nach Fahrzeugkonfiguration oder Einstellung weniger nutzen.

###### 4.1.2.5.1 GetCurrentChargingAmperage(int $Instanz)
Mit dieser Funktion kann der aktuell verfügbare Ladestrom des go-eChargers abgefragt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
```
$CurrentChargingAmperage = GOeCharger_GetCurrentChargingAmperage( $Instanz ); // Liest den aktuellen Ladestrom 
```

###### 4.1.2.5.2 SetCurrentChargingAmperage(int $Instanz, int $Ampere)
Mit dieser Funktion kann der aktuell verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. Der Wert darf jedoch den derzeitigen, maximal verfügbaren Ladestrom nicht überschreiten!
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang.
```
GOeCharger_SetCurrentChargingAmperage( $Instanz, 8 ); // Setze den aktuellen Ladestrom auf 8 Ampere
```
Die Funktion liefert *true* oder *false* als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.6 IsElectricallyGroundedCheck(int $Instanz)
In einigen Ländern wie z.B. in Norwegen kann es sein, das die Erdung nicht vorhanden ist. Die Erdung wird allerdings vom go-eCharger überprüft. Mit dieser Funktionen kann abgefragt werden, ob der "Norwegen Modus" aktiv ist.
Da dieser Modus von der Elektrischen Installation abhängig ist, bietet dieses Modul keine Möglichkeit an, die Einstellung zu  verändert werden.
```
$GroundedCheck = GOeCharger_IsElectricallyGroundedCheck( $Instanz ); // Ermittlung, ob der 'Norwegen Modus' aktiv ist
```

##### 4.1.2.7 Zugriffskontrolle
Funktionen bzgl. der Zugriffskontrolle

###### 4.1.2.7.1 IsAccessControlActive(int $Instanz)
Mit dieser Funktion kann der Zustand der Zugangssteuerung (ist eine Nutzung eines RFID notwendig) abgefragt werden.
```
$RFIDneeded = GOeCharger_GetAccessControl( $Instanz ); // Liest die Einstellung der Zugangskontrolle 
```

###### 4.1.2.7.2 SetAccessControl(int $Instanz, bool $aktiv)
Mit dieser Funktion kann die Zugangssteuerung via RFID oder App des go-eChargers aktiviert oder deaktiviert werden.
```
GOeCharger_SetAccessControl( $Instanz, true ); // aktiviert die Zugangskontrolle 
```

##### 4.1.2.8 Automatische Beendigung des Ladens
Mit dieser Funktion des go-eChargers kann ein "Ladestopp" gesetzt werden, so dass nur z.B. 5kw geladen werden können (danach wird das Laden beendet). Ein Wert von "0" entspricht der Deaktivierung der Funktion. Zudem entspricht der eingestellte Wert **nicht** dem Ladestand des Fahrzeugs sondern der maximal ladbaren Energie! Wenn das Fahrzeug also mit 20% Akkustand angeschlossen wird, dann können maximal z.B. 5kw geladen werden.

###### 4.1.2.8.1 GetAutomaticChargeStop(int $Instanz)
Auslesen des aktuell eingestellten Ladestopp-Werts.
```
$AutomaticChargeStopAt = GOeCharger_SetAutomaticChargeStop( $Instanz ); // liest den automatischen Ladestop 
```

###### 4.1.2.8.2 SetAutomaticChargeStop(int $Instanz, float $kw)
Mit dieser Funktion kann der automatische Ladestop des go-eChargers aktiviert werden. Während der Wert '0' den automatischen Ladestop deaktivert, können höhere Werte bis 100 (Maximum) als Ladegrenze in kw angegeben werden. 
```
GOeCharger_SetAutomaticChargeStop( $Instanz, 10.5 ); // aktiviert den automatischen Ladestop bei 10,5 kw
```
##### 4.1.2.9 Verriegelungsmodus des Kabels
Mit dieser Funktion kann der Verriegelungsmodus des Kabels am go-eCharger kontrolliert werden. Dabei gelten folgende Werte:
+ 0 = normaler Modus - Das Kabel bleibt am go-eCharger verriegelt, solange ein Fahrzeug angeschlossen ist
+ 1 = automatischer Modus - Das Kabel wird nach dem Ladeende automatisch entriegelt
+ 2 = verriegelt - Das Kabel kann nur durch Änderung des Verriegelungsmodus entriegelt werden

###### 4.1.2.9.1 GetCableUnlockMode(int $Instanz)
Auslesen des aktuellen CableUnlockModes
```
$CableUnlockMode = GOeCharger_SetCableUnlockMode( $Instanz ); // liest den aktuellen Entriegelungsmodus
```

###### 4.1.2.9.2 SetCableUnlockMode(int $Instanz, int $unlockMode)
Einstellen des CableUnlockModes
```
GOeCharger_SetCableUnlockMode( $Instanz, 1 ); // setzt den automatischen Entriegelungsmodus
```

##### 4.1.2.10 Ladekontrolle
Mit dieser Funktionen kann das Laden am go-eChargers aktiviert oder deaktiviert werden. Im deaktivierten Zustand ist kein Laden möglich!

###### 4.1.2.10.1 IsActive(int $Instanz)
```
$ChargingActivated = GOeCharger_SetActivation( $Instanz ); // Ermittlung, ob Laden möglich ist
```

###### 4.1.2.10.2 SetActive(int $Instanz, bool $aktiv)
```
GOeCharger_SetActivation( $Instanz, false ); // deaktiviert den go-eCharger 
```

##### 4.1.2.11 GetCableCapability(int $Instanz)
Liefert die Kabel-Codierung
+ 0: kein Kabel
+ 13-32: Ampere Codierung
```
$CableCapability = GOeCharger_GetCableCapability( $Instanz ); // Ermittlung der Kabel-Codierung
```

##### 4.1.2.12 GetNumberOfPhases(int $Instanz)
Liefert die Kabel-Codierung
+ 0: kein Kabel
+ 13-32: Ampere Codierung
```
$CableCapability = GOeCharger_GetCableCapability( $Instanz ); // Ermittlung der Kabel-Codierung
```

##### 4.1.2.13 GetMainboardTemperature(int $Instanz)
Mainboard Temperatur in Celsius
```
$MainboardTemperature = GOeCharger_GetMainboardTemperature( $Instanz ); // Ermittlung der Mainboard Temperatur
```

##### 4.1.2.14 GetUnlockRFID(int $Instanz)
Ermittlung der RFID (integer), die zum entsperren genutzt wurde.
```
$MainboardTemperature = GOeCharger_GetMainboardTemperature( $Instanz ); // Ermittlung der Mainboard Temperatur
```

##### 4.1.2.15 GetSupplyLineVoltageLx(int $Instanz)
Ermittlung der Spannung der angeschlossenen Phasen.
```
$VoltageL1 = GOeCharger_GetSupplyLineVoltageL1( $Instanz ); // Ermittlung der Spannung von Phase 1
$VoltageL2 = GOeCharger_GetSupplyLineVoltageL2( $Instanz ); // Ermittlung der Spannung von Phase 2
$VoltageL3 = GOeCharger_GetSupplyLineVoltageL3( $Instanz ); // Ermittlung der Spannung von Phase 3
```

##### 4.1.2.16 GetSupplyLineEnergy(int $Instanz)
Ermittlung der durchschnittlichen Spannung der angeschlossenen Phasen.
```
$Engergy = GOeCharger_GetSupplyLineEnergy( $Instanz ); // Ermittlung der Durchschnittsspannung von L1-3
```

##### 4.1.2.17 GetSerialID(int $Instanz)
Ermittlung der Seriennummer des go-eChargers.
```
$SerialID = GOeCharger_GetSerialID( $Instanz ); // Ermittlung Seriennummer
```

##### 4.1.2.17 LED Helligkeit

###### 4.1.2.17.1 GetLEDBrightness(int $Instanz)
Ermittlung der Helligkeit der LEDs
```
$LEDBrightness = GOeCharger_GetLEDBrightness( $Instanz ); // Ermittlung Seriennummer
```

###### 4.1.2.17.2 SetLEDBrightness(int $Instanz, int $Brightness)
Setzen der Helligkeit der LEDs
```
GOeCharger_SetLEDBrightness( $Instanz, 255 ); // Setzen der LED Helligkeit auf Maximum
```

##### 4.1.2.17 GetEnergyChargedByCard(int $Instanz, int $cardID)
Liefert die mit einer RFID Karte geladene Energie zurück.
```
$EnergyByCard2 = GOeCharger_GetEnergyChargedByCard( $Instanz, 2 ); // Ermittlung der Ladeenergie für Karte 2
```
