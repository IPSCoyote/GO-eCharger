### IP-Symcon Modul für die GO-eCharger Wallbox

PHP-Modul zur Einbindung einer [GO-eCharger Wallbox](www.go-e.co) in IPSymcon. 

Nutzung auf eigene Gefahr ohne Gewähr. Das Modul kann jederzeit überarbeitet werden, so daß Anpassungen für eine weitere Nutzung notwendig sein können. Bei der Weiterentwicklung wird möglichst auf Kompatibilität geachtet. 

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

### Vorbereitung des go-eChargers
Vor der Installation des Moduls in IPSymcon muss der go-eCharger vollständig eingerichtet sein. Da dieses Modul lokal auf den go-eCharger zugreift, muss im lokalen WLAN (nicht dem WLAN des go-eChargers!) dem go-eCharger eine statische IP zugewiesen sein. Zusätzlich muss das HTTP-API des go-eChargers in den erweiterten Einstellungen (nur über das WLAN des go-eChargers erreichbar!) eingerichtet sein.

<p align="center">
  <img width="447" height="416" src="./imgs/Erweiterte%20Einstellungen%20-%20HTTP%20API%20aktivieren.jpg">
</p>

Die Cloud des go-eChargers wird nicht verwendet. Wer möchte kann diese durch das blockieren aller Ports ausser des HTTP Ports 80 aushebeln. Die wesentlichen Einstellungen stehen auch über dieses Modul zur Verfügung

### Installation des Moduls
Um eine Instanz des go-eCharger Moduls anlegen zu können muss das Modul IPSymcon bekannt gemacht werden. Hierzu wird es unter den Kern-Instanzen bei "Modules" mit dem Pfad 
```
git://github.com/IPSCoyote/GO-eCharger
```
hinzugefügt. 

Anschließend kann eine Instanz des Moduls angelegt werden.

<p align="center">
  <img width="496" height="431" src="./imgs/Instanz%20Anlegen.jpg">
</p>

### Einrichten der Modul-Instanz
Nachdem eine Instanz des Moduls angelegt wurde, muss diese eingerichtet werden.

<p align="center">
  <img width="1009" height="670" src="./imgs/Modul%20einrichten.jpg">
</p>

* **IP-Adresse**<br>
Statische IP Adresse unter der der go-eCharger im lokalen WLAN erreichbar ist.

* **el. Absicherung**<br>
Die maximale el. Absicherung, die für den go-eCharger vorhanden ist. Wenn dieser an einer 16A abgesicherten Zuleitung abgesichert ist, wären dies 16A. Andere Werte entsprechend.

* **Update Intervalle**<br>
Hier werden die Update-Intervalle für die Instanz in Sekunden hinterlegt. Gute Werte dürften 10/10 Sekunden sein. Werte unter 5 Sekunden können zu Laufzeitproblemen holen, da ggf. das abholen der Werte länger dauern könnte. 
Ohne Intervalle muss die Update() Funktion für die Instanz manuell aufgerufen werden (siehe unten).

* **Komfortfunktion - automatische Aktivierung bei Anschluss**<br> 
Der go-eCharger deaktiviert sich nach einem automatischen Ladeende (siehe unten). Auch kann er manuell deaktiviert worden sein. Mit dieser Option wird das Laden automatsich re-aktiviert, wenn erneut ein Fahrzeug angeschlossen wird.

* **Komfortfunktion - automatische Aktivierung bei setzen Ladeende**<br>
Wenn der go-eCharger deaktiv ist und man ein Ladeende setzt, muss er anschließend noch aktiviert werden. Mit dieser Option entfällt dies und das Modul übernimmt die Aktivierung des Ladevorgangs, wenn ein automatisches Ladeende gesetzt wird.

* **Fahrzeugdaten - Verbrauch**<br>
Um den automatischen Ladestop anhand von Kilomenter-Angaben setzen zu können, muss das Modul den Durchschnittsverbrauch des angeschlossenen Fahrzeugs wissen, um die benötigten kwh berechnen zu können.

* **Fahrzeugdaten - Batteriegröße**<br>
Die Batteriegrösse wird genutzt, um nicht unnötig viele Optionen für die 5km-Schritte der Km-Ladestop-Option anzubieten. 

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
`Wallbox aktiv` | Boolean | RW, WF | Kann an der Wallbox geladen werden? | [Get](#isactiveint-instanz) / [Set](#setactiveint-instanz-bool-aktiv)
`Status` | Integer | RO | Allgemeiner Ladestatus des go-eChargers<br>1: Ladestation bereit, kein Fahrzeug<br>2: Fahrzeug lädt<br>3:Warte auf Fahrzeug<br>4: Ladung beendet, Fahrzeug verbunden | [Get](#getstatusint-instanz)
`Ladeende nach x kwh` | Float | RW, WF | Ladung nach abgabe von X kwh beenden<br>0 = kein automatischer Ladestop<br>0.1-100.0 kwh | [Get](#getautomaticchargestopint-instanz) / [Set](#setautomaticchargestopint-instanz-float-kwh)
`Ladeende nach Energie für x km` | Integer | RW, WF | Ladung nach Abgabe von Energie für X Kilomenter beenden<br>0 = kein automatischer Ladestop<br>**Parameter funktioniert nur, wenn ein Verbrauch in den Einstellungen gepflegt ist!** | [Get](#getautomaticchargestopkmint-instanz) / [Set](#setautomaticchargestopkmint-instanz-float-km)

#### Informationen zum aktuellen Ladevorgang

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`Aktuelle Leistung zum Fahrzeug` | Float | RO | Gesamte Ladeleistung zum Fahrzeug in kwh | [Get](#getpowertocarint-instanz)
`abgegebene Energie im Ladezyklus` | Float | RO | abgegebene Energie im aktuellen Ladezyklus in kwh<br>*Beispiel: 5,3 kwh* | [Get](#getcurrentloadingcycleconsumptionint-instanz)
`entsperrt durch RFID` | Integer | RO | Wurde der go-eCharger durch RFID Token X entsperrt | [Get](#getunlockrfidint-instanz)

#### Verbrauchsinformationen

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`bisher abgegebene Energie` | Float | RO | Bisher insgesamt vom go-eCharger abgegebene Energie in kwh<br>*Beispiel: 379,0 kwh* | [Get](#getenergychargedintotalint-instanz)
`geladene Energie Karte X` | Float | RO | Geladene Energiemenge pro Karte in kwh | [Get](#getenergychargedbycardint-instanz-int-cardid)

#### Einstellungen

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`max. verfügbarer Ladestrom` | Integer | RW, WF | Maximal verfügbarer Ladestrom des go-eChargers | [Get](#getmaximumchargingamperageint-instanz-) / [Set](#setmaximumchargingamperageint-instanz-int-ampere)
`aktuell verfügbarer Ladestrom` | Integer | RW, WF | Der aktuell verfügbare Ladestrom zum laden eines Fahrzeugs<br>*Beispiel: 16 A* | [Get](#getcurrentchargingamperageint-instanz) / [Set](#setcurrentchargingamperageint-instanz-int-ampere)
`Kabel-Verriegelungsmodus` | Integer | RW, WF | Verriegelungsmodus für das Kabel<br>0: Verriegeln, solange Auto angesteckt<br>1: Nach Ladevorgang entriegeln<br>2: Kabel immer verriegelt | [Get](#getcableunlockmodeint-instanz) / [Set](#setcableunlockmodeint-instanz-int-unlockmode)
`Zugangskontrolle via RFID/APP` | Integer | RW, WF | Zugangskontrolle<br>0: frei zugänglich<br>1: RFID Identifizierung<br>2: Strompreis/automatisch | [Get](#getaccesscontrolint-instanz) / [Set](#setaccesscontrolint-instanz-int-mode)
`LED Helligkeit` | Integer | RW, WF | Helligkeit der LEDs<br>0: LED aus<br>1 - 255: LED Helligkeit | [Get](#getledbrightnessint-instanz) / [Set](#setledbrightnessint-instanz-int-brightness)

#### Technische Informationen

Name | Type | Optionen | Werte | Funktionen
:--- | :---: |  :---:  | :---  | :---:
`Seriennummer` | String | RO | Seriennummer des go-eChargers<br>*Beispiel: "000815"* | Nein
`Fehler` | Integer | RO | Liegt ein Fehler am go-eCharger vor<br>0: kein Fehler<br>1: FI Schutzschalter<br>3: Fehler an Phase<br>8: Keine Erdung<br>10: Interner Fehler | Nein
`angeschlossener Adapter` | Integer | RO | verwendeter Adapter für den go-eCharger<br>0: kein Adapter<br>1: 16A Adapter | Nein
`Kabel-Leistungsfähigkeit` | Integer | RO | Leistungsfähigkeit des angeschlossenen Kabels<br>0: kein Kabel<br>13-32: Ampere | Nein
`Erdungsprüfung` | Boolean | RO | Ist die Erdungsprüfung (Norwegen Modus) aktiv | Nein
`Mainboard Temperatur` | Float | RO | Mainboard Temperatur in °C | Nein
`verfügbare Phasen` | String | RO | verfügbare Phasen<br>*Beispiel: "Phase 1,2 und 3 ist vorhanden"* | Nein
`Spannungsversorgung X` | Integer | RO | Spannung an L1, L2, L3 und N in Volt | Nein
`Leistung zum Fahrzeug X` | Float | RO | Ladeleistung zum Fahrzeug auf L1-3 und N kwh | Nein
`Ampere zum Fahrzeug Lx` | Float | RO | Ampre zum Fahrzeug auf L1-3 und N in A | Nein
`max. verfügbare Ladeleistung` | Float | RO | Berechnete max. verfügbare Ladeleistung in kw | Nein
`Leistungsfaktor X` | Float | RO | Leistungsfaktor auf L1-3 und N in % | Nein

#### 4.1.2. Funktionen

#### Update(int $Instanz)
Aktualisiert die Messwerte (IPS Variablen) des go-eChargers. Diese Funktion wird auch in Abhängigkeit der eingestellten Aktualisierungsintervall in den Moduleinstellungen ausgeführt, so dass normalerweise ein manueller Aufruf unnötig sein sollte.
```
GOeCharger_Update( $Instanz ); // Aktualisiert die Messwerte (IPS Variablen) des go-eChargers
```
Die Funktion liefert true oder false als Rückgabewert und aktualisiert die Messwerte

#### IsActive(int $Instanz)
Prüft, ob die Wallbox aktuell zum laden freigegeben ist. 
```
$ChargingActivated = GOeCharger_IsActive( $Instanz ); // Ermittlung, ob Laden möglich ist
```

#### SetActive(int $Instanz, bool $aktiv)
Mit dieser Funktion kann das Laden an der Wallbox freigegeben oder abgebrochen werden. 
```
GOeCharger_SetActive( $Instanz, false ); // deaktiviert den go-eCharger 
```

#### GetStatus(int $Instanz)
Ermittlung des aktuellen Status des go-eChargers. Rückgabewerte sind
+ 1: Ladestation bereit, kein Fahrzeug
+ 2: Fahrzeug lädt
+ 3: Warte auf Fahrzeug
+ 4: Ladung beendet, Fahrzeug noch verbunden
```
$Status = GOeCharger_GetStatus( $Instanz ); // Ermittlung des Status
```

#### GetAutomaticChargeStop(int $Instanz)
Der Go-eCharger kann die Ladung automatisch nach x kwh beenden. Mit diese Funktion kann der aktuell eingestellte Wert abgefragt werden. Der Wert 0 zeigt an, das kein automatischer Ladestop eingestellt ist.
```
$AutomaticChargeStopAt = GOeCharger_SetAutomaticChargeStop( $Instanz ); // liest den automatischen Ladestop 
```

#### SetAutomaticChargeStop(int $Instanz, float $kwh)
Mit dieser Funktion kann der automatische Ladestop des go-eChargers aktiviert werden. Während der Wert '0' den automatischen Ladestop deaktivert, können höhere Werte bis 100 (Maximum) als Ladegrenze in kwh angegeben werden. 
```
GOeCharger_SetAutomaticChargeStop( $Instanz, 10.5 ); // aktiviert den automatischen Ladestop bei 10,5 kwh
```

#### GetAutomaticChargeStopKm(int $Instanz)
Diese Funktion benötigt die Angabe des Durchschnittsverbrauchs in den Instanz-Einstellungen für einen PKW, der typischerweise an diesem Go-eCharger lädt!
Mit diese Funktion kann analog zur Funktion GetAutomaticChargeStop() der aktuell eingestellte Wert für den Ladestop umgerechnet in Km abgefragt werden. Der Wert 0 zeigt an, das kein automatischer Ladestop eingestellt ist.
```
$AutomaticChargeStopAtKm = GOeCharger_SetAutomaticChargeStopKm( $Instanz ); // liest den automatischen Ladestop in Km
```

#### SetAutomaticChargeStopKm(int $Instanz, float $km)
Diese Funktion benötigt die Angabe des Durchschnittsverbrauchs in den Instanz-Einstellungen für einen PKW, der typischerweise an diesem Go-eCharger lädt!
Mit dieser Funktion kann der automatische Ladestop des go-eChargers aktiviert werden. Während der Wert '0' den automatischen Ladestop deaktivert, können höhere Werte als Ladegrenze in km angegeben werden. 
```
GOeCharger_SetAutomaticChargeStopKm( $Instanz, 5 ); // aktiviert den automatischen Ladestop nach einer Ladung für 5km Reichweite
```

#### GetPowerToCar(int $Instanz)
Ermittlung der aktuellen Leistung in kw, die zum ladenden Fahrzeug geliefert wird
```
GOeCharger_GetPowerToCar( $Instanz ); // Ermittlung der Ladeleistung zum angeschlossenen Fahrzeug
```

#### GetCurrentLoadingCycleConsumption(int $Instanz)
Ermittlung der im aktuellen Ladezyklus abgegebenen kwh
```
GOeCharger_GetCurrentLoadingCycleConsumption( $Instanz ); // Ermittlung der im Ladezyklus abgegebenen kwh
```

#### GetUnlockRFID(int $Instanz)
Ermittlung der RFID (integer), die zum entsperren genutzt wurde.
```
$MainboardTemperature = GOeCharger_GetMainboardTemperature( $Instanz ); // Ermittlung der Mainboard Temperatur
```

#### GetEnergyChargedInTotal(int $Instanz)
Liefert die komplette bisher über den go-eCharger geladene Energie in kwh.
```
$EnergyInTotal = GOeCharger_GetEnergyChargedInTotal( $Instanz ); // Ermittlung der bisherigen Ladeenergie des go-eChargers
```

#### GetEnergyChargedByCard(int $Instanz, int $cardID)
Liefert die mit einer RFID Karte geladene Energie zurück.
```
$EnergyByCard2 = GOeCharger_GetEnergyChargedByCard( $Instanz, 2 ); // Ermittlung der Ladeenergie für Karte 2
```

#### GetMaximumChargingAmperage(int $Instanz )
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers abgefragt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
```
$MaximumChargingAmperage = GOeCharger_GetMaximumChargingAmperage( $Instanz ); // Liest den maximal verfügbaren Ladestrom
```

#### SetMaximumChargingAmperage(int $Instanz, int $Ampere)
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers (Button!) sowie einen ggf. aktuell stattfindenden Ladevorgang. Der maximale Ladestrom sollte an die verfügbare Hausinstallation angepasst sein. Die über IPS maximal einstellbare Ladestrom kann über die Instanzeinstellungen beschränkt werden!
Sollte der maximal verfügbare Ladestrom reduziert werden, so wird ggf. auch der aktuell eingestellte Ladestrom entsprechend verringert, sofern er das neue Maximum überschreiten würde.
```
GOeCharger_SetMaximumChargingAmperage( $Instanz, 16 ); // Setze den maximal verfügbaren Ladestrom auf 16 Ampere
```
Die Funktion liefert den eingestellten Wert oder *false* als Rückgabewert zurück und aktualisiert die Messwerte

#### GetCurrentChargingAmperage(int $Instanz)
Mit dieser Funktion kann der aktuell verfügbare Ladestrom des go-eChargers abgefragt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
```
$CurrentChargingAmperage = GOeCharger_GetCurrentChargingAmperage( $Instanz ); // Liest den aktuellen Ladestrom 
```

#### SetCurrentChargingAmperage(int $Instanz, int $Ampere)
Mit dieser Funktion kann der aktuell verfügbare (abgegebene) Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. Der Wert darf jedoch den derzeitigen, maximal verfügbaren Ladestrom nicht überschreiten! Sollte dies der Fall sein, so wird der maximal mögliche Wert (aktuelles Maximum) gesetzt.
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang.
```
GOeCharger_SetCurrentChargingAmperage( $Instanz, 8 ); // Setze den aktuellen Ladestrom auf 8 Ampere
```
Die Funktion liefert den gesetzten Wert oder *false* als Rückgabewert und aktualisiert die Messwerte

#### GetCableUnlockMode(int $Instanz)
Auslesen des aktuellen CableUnlockModes. Dabei gelten folgende Werte:
+ 0 = normaler Modus - Das Kabel bleibt am go-eCharger verriegelt, solange ein Fahrzeug angeschlossen ist
+ 1 = automatischer Modus - Das Kabel wird nach dem Ladeende automatisch entriegelt
+ 2 = verriegelt - Das Kabel kann nur durch Änderung des Verriegelungsmodus entriegelt werden
```
$CableUnlockMode = GOeCharger_SetCableUnlockMode( $Instanz ); // liest den aktuellen Entriegelungsmodus
```

#### SetCableUnlockMode(int $Instanz, int $unlockMode)
Einstellen des CableUnlockModes. Dabei gelten folgende Werte:
+ 0 = normaler Modus - Das Kabel bleibt am go-eCharger verriegelt, solange ein Fahrzeug angeschlossen ist
+ 1 = automatischer Modus - Das Kabel wird nach dem Ladeende automatisch entriegelt
+ 2 = verriegelt - Das Kabel kann nur durch Änderung des Verriegelungsmodus entriegelt werden
```
GOeCharger_SetCableUnlockMode( $Instanz, 1 ); // setzt den automatischen Entriegelungsmodus
```

#### GetAccessControl(int $Instanz)
Mit dieser Funktion kann der Zustand der Zugangssteuerung (ist eine Nutzung eines RFID notwendig) abgefragt werden. Mögliche Werte sind
+ 0 = Offen
+ 1 = RFID / App benötigt
+ 2 = Strompreis / automatisch
```
$RFIDneeded = GOeCharger_GetAccessControl( $Instanz ); // Liest die Einstellung der Zugangskontrolle 
```

#### SetAccessControl(int $Instanz, int $mode)
Mit dieser Funktion kann die Zugangssteuerung via RFID oder App bzw. die Stromautomatik des go-eChargers aktiviert oder deaktiviert werden. Mögliche Werte sind
+ 0 = Offen
+ 1 = RFID / App benötigt
+ 2 = Strompreis / automatisch
```
GOeCharger_SetAccessControl( $Instanz, 1 ); // aktiviert die Zugangskontrolle per RFID
```

#### GetLEDBrightness(int $Instanz)
Ermittlung der Helligkeit der LEDs
```
$LEDBrightness = GOeCharger_GetLEDBrightness( $Instanz ); // Ermittlung Seriennummer
```

#### SetLEDBrightness(int $Instanz, int $Brightness)
Setzen der Helligkeit der LEDs
```
GOeCharger_SetLEDBrightness( $Instanz, 255 ); // Setzen der LED Helligkeit auf Maximum
```
