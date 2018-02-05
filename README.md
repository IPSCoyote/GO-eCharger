### IP-Symcon Modul für die GO-eCharger Wallbox

Nicht verwenden! Don't use it yet!

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Systemanforderungen](#2-systemanforderungen)
3. [Installation](#3-installation)

## 1. Funktionsumfang

Das Modul ist dazu gedacht die [GO-eCharger Wallbox](www.go-e.co) zum Laden von Elektrofahrzeugen in [IP-Symcon](www.ip-symcon.de) einzubinden. 

Es soll sowohl Zustandsdaten (Anschluss, Ladevorgang, etc.) als auch Schaltaktionen (Ladevorgang starten/stoppen, Ladeströme setzen) zur Verfügung stellen.

## 2. Systemanforderungen
- IP-Symcon ab Version 4.x

## 3. Installation

Das Modul befindet sich im Entwicklungsstadium und ist derzeit **nicht** für die Nutzung freigegeben.

## 4. Enthaltene Module

### 4.1. go-eCharger

Das Modul "go-eCharger" dient als Schnittstelle zu einem lokal installierten go-eCharger. Es liefert aktuelle Messwerte als Instanzvariablen und bietet einen Zugriff auf Funktionen des go-eChargers. Der go-eCharger muss dabei lokal über eine IP-Adresse erreichbar sein (siehe Installation).

#### 4.1.1. Messwerte
+ Seriennummer

  Die Seriennummer des angeschlossenen go-eChargers
+ Zustand
+ derzeit verfügbarer Ladestrom
+ maximal verfügbarer Ladestrom
+ verfügbare Spannung Phase 1
+ verfügbare Spannung Phase 2
+ verfügbare Spannung Phase 3
+ max. verfügbare Ladeleistung

#### 4.1.2. Funktionen

### 4.2. Load Balancer

