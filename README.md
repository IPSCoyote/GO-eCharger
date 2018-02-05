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
+ Seriennummer
+ Zustand
+ derzeit verfügbarer Ladestrom 
+ maximal verfügbarer Ladestrom
+ verfügbare Spannung Phase 1 / 2 / 3 (die Spannungsversorgung des go-eChargers)
+ max. verfügbare Ladeleistung (berechnet aus der aktuellen Spannungsversorgung sowie dem verfügbaren Ladestrom)

#### 4.1.2. Funktionen

##### 4.1.2.1. Update()
Aktualisiert die Messwerte (IPS Variablen) des go-eChargers. Diese Funktion wird auch in Abhängigkeit der eingestellten Aktualisierungsfrequenzen in den Moduleinstellungender ausgeführt, so dass normalerweise ein manueller Aufruf unnötig sein sollte.
```
GOeCharger_Update(); // Aktualisiert die Messwerte (IPS Variablen) des go-eChargers
```
Die Funktion liefert true oder false als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.2. SetMaximumChargingAmperage(int $Ampere)
Mit dieser Funktion kann der maximal verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. 
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang. Der maximale Ladestrom sollte an die verfügbare Hausinstallation angepasst sein. Die über IPS maximal einstellbare Ladestrom kann über die Moduleinstellungen beschränkt werden!
Sollte der maximal verfügbare Ladestrom reduziert werden, so wird ggf. auch der aktuell eingestellte Ladestrom entsprechend verringert, sofern er das neue Maximum überschreiten würde.
```
GOeCharger_SetMaximumChargingAmperage( 16 ); // Setze den maximal verfügbaren Ladestrom auf 16 Ampere
```
Die Funktion liefert *true* oder *false* als Rückgabewert und aktualisiert die Messwerte

##### 4.1.2.3. SetCurrentChargingAmperage(int $Ampere)
Mit dieser Funktion kann der aktuell verfügbare Ladestrom des go-eChargers gesetzt werden. Es sind Werte zwischen 6 und 32 Ampere möglich. Der Wert darf jedoch den derzeitigen, maximal verfügbaren Ladestrom nicht überschreiten!
Diese Funktion hat direkte Auswirkung auf die Einstellungen des go-eChargers sowie einen ggf. aktuell stattfindenden Ladevorgang.
```
GOeCharger_SetCurrentChargingAmperage( 8 ); // Setze den aktuellen Ladestrom auf 8 Ampere
```
Die Funktion liefert *true* oder *false* als Rückgabewert und aktualisiert die Messwerte

### 4.2. go-eCharger Load Balancer

