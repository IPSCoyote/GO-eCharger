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
Derzeit bietet das GIT nur das Modul "go-eCharger" für die direkte Steuerung eines einzelnen go-eChargers. 

### 4.1. go-eCharger

Das Modul "go-eCharger" dient als Schnittstelle zu einem lokal installierten go-eCharger. Es liefert die Daten des go-eChargers als Statusvariablen und bietet einen Zugriff auf Funktionen des go-eChargers. Der go-eCharger muss dabei lokal über eine IP-Adresse erreichbar sein (siehe Installation).

#### 4.1.1 Status Variablen
Im folgenden werden die verfügbaren Statusvariablen mit ihren Eigenschaften, Werten und Zugriffsmöglichkeiten aufgelistet. Wenn Funktionen verfügbar sind, sind diese im Anschluss aufgelistet. 

- RO = **R**ead **O**nly<br>
- RW = **R**ead **W**rite enabled<br>
- WF = **W**eb**f**rond change enabled (die Variablen können zwecks Anzeige natürlich alle ins Webfront eingebunden werden)

#### Grundfunktionen zum Laden

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`Wallbox aktiv` | Boolean | RW, WF | Kann an der Wallbox geladen werden? | [Get](#412101-isactiveint-instanz) / [Set](#412102-setactiveint-instanz-bool-aktiv)
`Status` | Integer | RO | Allgemeiner Ladestatus des go-eChargers<br>1: Ladestation bereit, kein Fahrzeug<br>2: Fahrzeug lädt<br>3:Warte auf Fahrzeug<br>4: Ladung beendet, Fahrzeug verbunden | [Get](#4123-getstatusint-instanz)
`Ladeende nach x kwh` | Float | RW, WF | Ladung nach abgabe von X kwh beenden<br>0 = kein automatischer Ladestop<br>0.1-100.0 kwh | [Get](#41281-getautomaticchargestopint-instanz) / [Set](#41282-setautomaticchargestopint-instanz-float-kw)
`Ladeende nach Energie für x km` | Integer | RW, WF | Ladung nach Abgabe von Energie für X Kilomenter beenden<br>0 = kein automatischer Ladestop<br>**Parameter funktioniert nur, wenn ein Verbrauch in den Einstellungen gepflegt ist!** | Nein

#### Informationen zum aktuellen Ladevorgang

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`Aktuelle Leistung zum Fahrzeug` | Float | RO | Gesamte Ladeleistung zum Fahrzeug in kwh | Nein
`abgegebene Energie im Ladezyklus` | Float | RO | abgegebene Energie im aktuellen Ladezyklus in kwh<br>*Beispiel: 5,3 kwh* | Nein
`entsperrt durch RFID` | Integer | RO | Wurde der go-eCharger durch RFID Token X entsperrt | [Get](#41214-getunlockrfidint-instanz)

#### Verbrauchsinformationen

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`bisher abgegebene Energie` | Float | RO | Bisher insgesamt vom go-eCharger abgegebene Energie in kwh<br>*Beispiel: 379,0 kwh* | Nein
`geladene Energie Karte X` | Float | RO | Geladene Energiemenge pro Karte in kwh | [Get](#41217-getenergychargedbycardint-instanz-int-cardid)

#### Einstellungen

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`max. verfügbarer Ladestrom` | Integer | RW, WF | Maximal verfügbarer Ladestrom des go-eChargers | [Get](#41241-getmaximumchargingamperageint-instanz-) / [Set](#41242-setmaximumchargingamperageint-instanz-int-ampere)
`aktuell verfügbarer Ladestrom` | Integer | RW, WF | Der aktuell verfügbare Ladestrom zum laden eines Fahrzeugs<br>*Beispiel: 16 A* | [Get](#41251-getcurrentchargingamperageint-instanz) / [Set](#41252-setcurrentchargingamperageint-instanz-int-ampere)
`Kabel-Verriegelungsmodus` | Integer | RW, WF | Verriegelungsmodus für das Kabel<br>0: Verriegeln, solange Auto angesteckt<br>1: Nach Ladevorgang entriegeln<br>2: Kabel immer verriegelt | [Get](#41291-getcableunlockmodeint-instanz) / [Set](#41292-setcableunlockmodeint-instanz-int-unlockmode)
`Zugangskontrolle via RFID/APP` | Integer | RW, WF | Zugangskontrolle<br>0: frei zugänglich<br>1: RFID Identifizierung<br>2: Strompreis/automatisch | [Get](#41271-isaccesscontrolactiveint-instanz) / [Set](#41272-setaccesscontrolint-instanz-bool-aktiv)
`LED Helligkeit` | Integer | RW, WF | Helligkeit der LEDs<br>0: LED aus<br>1 - 255: LED Helligkeit | [Get](#412171-getledbrightnessint-instanz) / [Set](#412172-setledbrightnessint-instanz-int-brightness)

#### Technische Informationen

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`Seriennummer` | String | RO | Seriennummer des go-eChargers<br>*Beispiel: "000815"* | Nein
`Fehler` | Integer | RO | Liegt ein Fehler am go-eCharger vor<br>0: kein Fehler<br>1: FI Schutzschalter<br>3: Fehler an Phase<br>8: Keine Erdung<br>10: Interner Fehler | [Get](#4122-geterrorint-instanz)
`angeschlossener Adapter` | Integer | RO | verwendeter Adapter für den go-eCharger<br>0: kein Adapter<br>1: 16A Adapter | Nein
`Kabel-Leistungsfähigkeit` | Integer | RO | Leistungsfähigkeit des angeschlossenen Kabels<br>0: kein Kabel<br>13-32: Ampere | [Get](#41211-getcablecapabilityint-instanz)
`Erdungsprüfung` | Boolean | RO | Ist die Erdungsprüfung (Norwegen Modus) aktiv | [Get](#4126-iselectricallygroundedcheckint-instanz)
`Mainboard Temperatur` | Float | RO | Mainboard Temperatur in °C | [Get](#41213-getmainboardtemperatureint-instanz)
`verfügbare Phasen` | String | RO | verfügbare Phasen<br>*Beispiel: "Phase 1,2 und 3 ist vorhanden"* | Nein
`Spannungsversorgung X` | Integer | RO | Spannung an L1, L2, L3 und N in Volt | [Get](#41215-getsupplylinevoltagelxint-instanz) 
`Leistung zum Fahrzeug X` | Float | RO | Ladeleistung zum Fahrzeug auf L1-3 und N kwh | Nein
`Ampere zum Fahrzeug Lx` | Float | RO | Ampre zum Fahrzeug auf L1-3 und N in A | Nein
`max. verfügbare Ladeleistung` | Float | RO | Berechnete max. verfügbare Ladeleistung in kw | [Get](#41216-getsupplylineenergyint-instanz)
`Leistungsfaktor X` | Float | RO | Leistungsfaktor auf L1-3 und N in % | Nein

#### 4.1.2. Funktionen

#### Grundfunktionen zum Laden

#### Update(int $Instanz)
Aktualisiert die Messwerte (IPS Variablen) des go-eChargers. Diese Funktion wird auch in Abhängigkeit der eingestellten Aktualisierungsfrequenzen in den Moduleinstellungender ausgeführt, so dass normalerweise ein manueller Aufruf unnötig sein sollte.
```
GOeCharger_Update( $Instanz ); // Aktualisiert die Messwerte (IPS Variablen) des go-eChargers
```
Die Funktion liefert true oder false als Rückgabewert und aktualisiert die Messwerte

##### IsActive(int $Instanz)
```
$ChargingActivated = GOeCharger_SetActivation( $Instanz ); // Ermittlung, ob Laden möglich ist
```

##### SetActive(int $Instanz, bool $aktiv)
```
GOeCharger_SetActivation( $Instanz, false ); // deaktiviert den go-eCharger 
```
















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

##### 4.1.2.11 GetCableCapability(int $Instanz)
Liefert die Kabel-Codierung
+ 0: kein Kabel
+ 13-32: Ampere Codierung
```
$CableCapability = GOeCharger_GetCableCapability( $Instanz ); // Ermittlung der Kabel-Codierung
```

##### 4.1.2.12 GetAvailablePhases(int $Instanz)
Liefert die verfügbaren Phasen.
Phasen vor und nach dem Schütz als binäre Zahl zu interpretieren: 0b00ABCDEF
A... phase 3, vor dem Schütz
B... phase 2 vor dem Schütz
C... phase 1 vor dem Schütz 
D... phase 3 vor dem Schütz 
E... phase 2 vor dem Schütz 
F... phase 1 vor dem Schütz
pha | 0b00001000: Phase 1 ist vorhanden pha | 0b00111000: Phase1-3 ist vorhanden
```
$availablePhases = GOeCharger_GetAvailablePhases( $Instanz ); // Ermittlung der verfügbaren Phasen
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
