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

#### 4.1.1. Messwerte
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

##### 4.1.2.1. Update(int $Instanz)
Aktualisiert die Messwerte (IPS Variablen) des go-eChargers. Diese Funktion wird auch in Abhängigkeit der eingestellten Aktualisierungsfrequenzen in den Moduleinstellungender ausgeführt, so dass normalerweise ein manueller Aufruf unnötig sein sollte.
```
GOeCharger_Update( $Instanz ); // Aktualisiert die Messwerte (IPS Variablen) des go-eChargers
```
Die Funktion liefert true oder false als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.2 Maximum Charging Amperage
Mit der "Maximum Charging Amperage" ist der maximal verfügbare Ladestrom gemeint, den der go-eCharger zur Verfügung stellen kann. Der aktuell eingestellte Ladestrom ("CurrentChargingAmperage") ist kleiner oder gleich.

###### 4.1.2.2.1 GetMaximumChargingAmperage(int $Instanz )
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers abgefragt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 

###### 4.1.2.2.2 SetMaximumChargingAmperage(int $Instanz, int $Ampere)
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang. Der maximale Ladestrom sollte an die verfügbare Hausinstallation angepasst sein. Die über IPS maximal einstellbare Ladestrom kann über die Moduleinstellungen beschränkt werden!
Sollte der maximal verfügbare Ladestrom reduziert werden, so wird ggf. auch der aktuell eingestellte Ladestrom entsprechend verringert, sofern er das neue Maximum überschreiten würde.
```
GOeCharger_SetMaximumChargingAmperage( $Instanz, 16 ); // Setze den maximal verfügbaren Ladestrom auf 16 Ampere
```
Die Funktion liefert *true* oder *false* als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.3. SetCurrentChargingAmperage(int $Instanz, int $Ampere)
Mit dieser Funktion kann der aktuell verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. Der Wert darf jedoch den derzeitigen, maximal verfügbaren Ladestrom nicht überschreiten!
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang.
```
GOeCharger_SetCurrentChargingAmperage( $Instanz, 8 ); // Setze den aktuellen Ladestrom auf 8 Ampere
```
Die Funktion liefert *true* oder *false* als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.4. SetAccessControl(int $Instanz, bool $aktiv)
Mit dieser Funktion kann die Zugangssteuerung via RFID oder App des go-eChargers aktiviert oder deaktiviert werden.
```
GOeCharger_SetAccessControl( $Instanz, true ); // aktiviert die Zugangskontrolle 
```

##### 4.1.2.5. SetAutomaticChargeStop(int $Instanz, float $kw)
Mit dieser Funktion kann der automatische Ladestop des go-eChargers aktiviert werden. Während der Wert '0' den automatischen Ladestop deaktivert, können höhere Werte bis 100 (Maximum) als Ladegrenze in kw angegeben werden. 
```
GOeCharger_SetAutomaticChargeStop( $Instanz, 10.5 ); // aktiviert den automatischen Ladestop bei 10,5 kw
```

##### 4.1.2.6. SetCableUnlockMode(int $Instanz, int $unlockMode)
Mit dieser Funktion kann der Verriegelungsmodus des Kabels am go-eCharger eingestellt werden. Dabei gelten folgende Werte:
+ 0 = normaler Modus - Das Kabel bleibt am go-eCharger verriegelt, solange ein Fahrzeug angeschlossen ist
+ 1 = automatischer Modus - Das Kabel wird nach dem Ladeende automatisch entriegelt
+ 2 = verriegelt - Das Kabel kann nur durch Änderung des Verriegelungsmodus entriegelt werden
```
GOeCharger_SetCableUnlockMode( $Instanz, 1 ); // setzt den automatischen Entriegelungsmodus
```

##### 4.1.2.7. SetActivation(int $Instanz, bool $aktiv)
Mit dieser Funktion der go-eChargers aktiviert oder deaktiviert werden. Im deaktivierten Zustand ist kein Laden möglich!
```
GOeCharger_SetActivation( $Instanz, false ); // deaktiviert den go-eCharger 
```
